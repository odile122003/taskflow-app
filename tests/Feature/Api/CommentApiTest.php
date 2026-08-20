<?php

namespace Tests\Feature\Api;

use App\Enums\TeamRole;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommentApiTest extends TestCase
{
    use RefreshDatabase;

    private function memberOf(Team $team, TeamRole $role = TeamRole::Owner): User
    {
        $user = User::factory()->create();
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    public function test_a_member_can_post_a_comment_on_a_task(): void
    {
        $team = Team::factory()->create();
        $user = $this->memberOf($team, TeamRole::Member);
        $project = Project::factory()->create(['team_id' => $team->id]);
        $task = Task::factory()->for($project)->create();

        Sanctum::actingAs($user, ['projects:read', 'tasks:read', 'tasks:write']);

        $response = $this->postJson("/api/v1/projects/{$project->slug}/tasks/{$task->id}/comments", [
            'body' => 'Un commentaire de test.',
        ]);

        $response->assertCreated()->assertJsonStructure([
            'data' => ['id', 'body', 'user' => ['id', 'name'], 'created_at'],
        ]);
    }

    public function test_a_guest_role_cannot_comment(): void
    {
        $team = Team::factory()->create();
        $guest = $this->memberOf($team, TeamRole::Guest);
        $project = Project::factory()->create(['team_id' => $team->id]);
        $task = Task::factory()->for($project)->create();

        Sanctum::actingAs($guest, ['projects:read', 'tasks:read', 'tasks:write']);

        $response = $this->postJson("/api/v1/projects/{$project->slug}/tasks/{$task->id}/comments", [
            'body' => 'Un commentaire de test.',
        ]);

        $response->assertForbidden();
    }

    public function test_a_user_can_delete_their_own_comment_but_not_someone_elses(): void
    {
        $team = Team::factory()->create();
        $author = $this->memberOf($team, TeamRole::Member);
        $otherMember = $this->memberOf($team, TeamRole::Member);
        $project = Project::factory()->create(['team_id' => $team->id]);
        $task = Task::factory()->for($project)->create();
        $comment = Comment::factory()->on($task)->create(['user_id' => $author->id]);

        Sanctum::actingAs($otherMember, ['projects:read', 'tasks:read', 'tasks:write']);
        $this->deleteJson("/api/v1/projects/{$project->slug}/tasks/{$task->id}/comments/{$comment->id}")
            ->assertForbidden();

        Sanctum::actingAs($author, ['projects:read', 'tasks:read', 'tasks:write']);
        $this->deleteJson("/api/v1/projects/{$project->slug}/tasks/{$task->id}/comments/{$comment->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }
}
