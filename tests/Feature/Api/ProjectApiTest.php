<?php

namespace Tests\Feature\Api;

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    private function memberOf(Team $team, TeamRole $role = TeamRole::Owner): User
    {
        $user = User::factory()->create();
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    public function test_lists_only_projects_of_the_authenticated_users_team(): void
    {
        $team = Team::factory()->create();
        $otherTeam = Team::factory()->create();
        $user = $this->memberOf($team);

        Project::factory()->count(2)->create(['team_id' => $team->id]);
        Project::factory()->create(['team_id' => $otherTeam->id]);

        Sanctum::actingAs($user, ['projects:read', 'tasks:read']);

        $response = $this->getJson('/api/v1/projects');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'slug', 'color', 'is_archived', 'tasks_count', 'links' => ['self', 'board']]],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_guests_are_rejected_with_a_homogeneous_401(): void
    {
        $response = $this->getJson('/api/v1/projects');

        $response->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_creating_a_project_requires_valid_data(): void
    {
        $team = Team::factory()->create();
        $user = $this->memberOf($team);

        Sanctum::actingAs($user, ['projects:read', 'projects:write']);

        $response = $this->postJson('/api/v1/projects', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['name', 'slug']]);
    }

    public function test_owner_can_create_a_project_attached_to_their_own_team(): void
    {
        $team = Team::factory()->create();
        $user = $this->memberOf($team);

        Sanctum::actingAs($user, ['projects:read', 'projects:write']);

        $response = $this->postJson('/api/v1/projects', [
            'name' => 'Nouveau projet',
            'slug' => 'nouveau-projet',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Nouveau projet');

        $this->assertDatabaseHas('projects', [
            'slug' => 'nouveau-projet',
            'team_id' => $team->id,
        ]);
    }

    public function test_a_read_only_token_cannot_create_a_project(): void
    {
        $team = Team::factory()->create();
        $user = $this->memberOf($team);

        Sanctum::actingAs($user, ['projects:read']);

        $response = $this->postJson('/api/v1/projects', [
            'name' => 'Nouveau projet',
            'slug' => 'nouveau-projet',
        ]);

        $response->assertForbidden()
            ->assertJsonStructure(['message']);
    }

    public function test_a_member_cannot_delete_a_project_only_the_owner_can(): void
    {
        $team = Team::factory()->create();
        $owner = $this->memberOf($team, TeamRole::Owner);
        $member = $this->memberOf($team, TeamRole::Member);
        $project = Project::factory()->create(['team_id' => $team->id]);

        Sanctum::actingAs($member, ['projects:read', 'projects:write']);
        $this->deleteJson("/api/v1/projects/{$project->slug}")->assertForbidden();

        Sanctum::actingAs($owner, ['projects:read', 'projects:write']);
        $this->deleteJson("/api/v1/projects/{$project->slug}")->assertNoContent();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_a_project_from_another_team_is_not_found(): void
    {
        $team = Team::factory()->create();
        $otherTeam = Team::factory()->create();
        $user = $this->memberOf($team);
        $otherProject = Project::factory()->create(['team_id' => $otherTeam->id]);

        Sanctum::actingAs($user, ['projects:read']);

        $response = $this->getJson("/api/v1/projects/{$otherProject->slug}");

        $response->assertForbidden()
            ->assertJsonStructure(['message']);
    }
}
