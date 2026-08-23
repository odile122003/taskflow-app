<?php

use App\Enums\TeamRole;
use App\Mail\TeamInvitationMail;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

it('attaches an existing user immediately, without sending any mail', function () {
    Mail::fake();

    $team = Team::factory()->create();
    $owner = memberOf($team, TeamRole::Owner);
    $existing = User::factory()->create();

    $this->actingAs($owner)
        ->postJson("/teams/{$team->id}/members", ['email' => $existing->email, 'role' => 'member'])
        ->assertCreated();

    $this->assertDatabaseHas('team_user', ['team_id' => $team->id, 'user_id' => $existing->id, 'role' => 'member']);
    Mail::assertNothingSent();
});

it('emails a signed invitation link to an address with no account yet', function () {
    Mail::fake();

    $team = Team::factory()->create();
    $owner = memberOf($team, TeamRole::Owner);

    $this->actingAs($owner)
        ->postJson("/teams/{$team->id}/members", ['email' => 'nouvelle.personne@example.com', 'role' => 'member'])
        ->assertAccepted();

    // assertQueued, pas assertSent : TeamInvitationMail implements ShouldQueue
    // (Module 7) — MailFake distingue les deux, même envoyé via ->send().
    Mail::assertQueued(TeamInvitationMail::class, function (TeamInvitationMail $mail) use ($team) {
        return $mail->team->is($team) && str_contains($mail->signedUrl, 'nouvelle.personne%40example.com');
    });

    // Personne n'a de compte avec cette adresse : rien à rattacher tant que
    // l'invitation n'est pas acceptée. Seul l'owner (déjà membre) est présent.
    expect($team->users()->count())->toBe(1);
});

it('only an owner or admin can invite someone', function () {
    Mail::fake();

    $team = Team::factory()->create();
    $member = memberOf($team, TeamRole::Member);

    $this->actingAs($member)
        ->postJson("/teams/{$team->id}/members", ['email' => 'quelquun@example.com', 'role' => 'member'])
        ->assertForbidden();

    Mail::assertNothingSent();
});

it('rejects a tampered signed invitation link', function () {
    $team = Team::factory()->create();
    memberOf($team, TeamRole::Owner);
    $recipient = User::factory()->create(['email' => 'invitee@example.com']);

    $signedUrl = URL::temporarySignedRoute('teams.invitations.accept', now()->addDays(7), [
        'team' => $team->id,
        'email' => 'invitee@example.com',
        'role' => 'member',
    ]);

    $tampered = str_replace('role=member', 'role=owner', $signedUrl);

    $this->actingAs($recipient)->get($tampered)->assertForbidden();
});

it('accepts a valid invitation only for the matching recipient', function () {
    $team = Team::factory()->create();
    memberOf($team, TeamRole::Owner);
    $recipient = User::factory()->create(['email' => 'invitee@example.com']);
    $someoneElse = User::factory()->create();

    $signedUrl = URL::temporarySignedRoute('teams.invitations.accept', now()->addDays(7), [
        'team' => $team->id,
        'email' => 'invitee@example.com',
        'role' => 'member',
    ]);

    $this->actingAs($someoneElse)->get($signedUrl)->assertForbidden();

    $this->actingAs($recipient)->get($signedUrl)->assertRedirect(route('dashboard'));
    $this->assertDatabaseHas('team_user', ['team_id' => $team->id, 'user_id' => $recipient->id, 'role' => 'member']);
});
