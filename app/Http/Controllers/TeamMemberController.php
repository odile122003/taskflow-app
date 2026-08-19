<?php

namespace App\Http\Controllers;

use App\Http\Requests\InviteMemberRequest;
use App\Mail\TeamInvitationMail;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class TeamMemberController extends Controller
{
    /**
     * Ajoute un membre à l'équipe. Si l'adresse a déjà un compte, l'ajout est
     * immédiat ; sinon une invitation par URL signée (valable 7 jours) part
     * par e-mail — la personne rejoint l'équipe en cliquant dessus une fois
     * connectée avec cette même adresse.
     */
    public function store(InviteMemberRequest $request, Team $team): JsonResponse
    {
        $email = $request->validated('email');
        $role = $request->validated('role');

        $user = User::where('email', $email)->first();

        if ($user !== null) {
            $team->users()->syncWithoutDetaching([$user->id => ['role' => $role]]);

            return response()->json(['message' => 'Membre ajouté.'], 201);
        }

        $signedUrl = URL::temporarySignedRoute('teams.invitations.accept', now()->addDays(7), [
            'team' => $team->id,
            'email' => $email,
            'role' => $role,
        ]);

        Mail::to($email)->send(new TeamInvitationMail($team, $signedUrl));

        return response()->json(['message' => 'Invitation envoyée par e-mail.'], 202);
    }

    /**
     * Le middleware `signed` a déjà rejeté toute URL dont email/role/team ont
     * été modifiés depuis leur génération (signature invalide → 403).
     * Reste à vérifier que la personne connectée est bien la destinataire.
     */
    public function acceptInvitation(Request $request, Team $team): RedirectResponse
    {
        abort_unless(
            $request->user()->email === $request->string('email')->toString(),
            403,
            'Cette invitation a été envoyée à une autre adresse e-mail. Connectez-vous avec le bon compte.'
        );

        $team->users()->syncWithoutDetaching([
            $request->user()->id => ['role' => $request->query('role')],
        ]);

        return redirect()->route('dashboard')->with('success', "Vous avez rejoint l'équipe « {$team->name} ».");
    }
}
