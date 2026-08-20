<?php

namespace Tests\Feature\Api;

use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    private function memberOf(Team $team, TeamRole $role = TeamRole::Owner): User
    {
        $user = User::factory()->create();
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    public function test_filters_tasks_by_status_and_sorts_by_due_date(): void
    {
        $team = Team::factory()->create();
        $user = $this->memberOf($team);
        $project = Project::factory()->create(['team_id' => $team->id]);

        Task::factory()->for($project)->create(['status' => 'todo', 'due_date' => now()->addDays(5)]);
        Task::factory()->for($project)->create(['status' => 'done', 'due_date' => now()->addDays(1)]);
        Task::factory()->for($project)->create(['status' => 'done', 'due_date' => now()->addDays(3)]);

        Sanctum::actingAs($user, ['projects:read', 'tasks:read']);

        $response = $this->getJson("/api/v1/projects/{$project->slug}/tasks?filter[status]=done&sort=-due_date");

        $response->assertOk()->assertJsonCount(2, 'data');

        $dueDates = collect($response->json('data'))->pluck('due_date');
        $this->assertTrue($dueDates->first() > $dueDates->last(), 'La tâche la plus tardive doit apparaître en premier (tri -due_date).');
    }

    public function test_rejects_an_unknown_sort_field(): void
    {
        $team = Team::factory()->create();
        $user = $this->memberOf($team);
        $project = Project::factory()->create(['team_id' => $team->id]);

        Sanctum::actingAs($user, ['projects:read', 'tasks:read']);

        $response = $this->getJson("/api/v1/projects/{$project->slug}/tasks?sort=not_a_real_column");

        $response->assertStatus(400);
    }

    public function test_filters_tasks_by_tag_slug(): void
    {
        $team = Team::factory()->create();
        $user = $this->memberOf($team);
        $project = Project::factory()->create(['team_id' => $team->id]);
        $tag = Tag::factory()->create(['slug' => 'urgent']);
        $tagged = Task::factory()->for($project)->create();
        $tagged->tags()->attach($tag);
        Task::factory()->for($project)->create();

        Sanctum::actingAs($user, ['projects:read', 'tasks:read']);

        $response = $this->getJson("/api/v1/projects/{$project->slug}/tasks?filter[tag]=urgent");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tagged->id);
    }

    public function test_a_guest_role_cannot_create_a_task(): void
    {
        $team = Team::factory()->create();
        $guest = $this->memberOf($team, TeamRole::Guest);
        $project = Project::factory()->create(['team_id' => $team->id]);

        Sanctum::actingAs($guest, ['projects:read', 'tasks:read', 'tasks:write']);

        $response = $this->postJson("/api/v1/projects/{$project->slug}/tasks", [
            'title' => 'Nouvelle tâche',
        ]);

        $response->assertForbidden();
    }

    public function test_creating_a_task_returns_the_expected_structure(): void
    {
        $team = Team::factory()->create();
        $user = $this->memberOf($team);
        $project = Project::factory()->create(['team_id' => $team->id]);

        Sanctum::actingAs($user, ['projects:read', 'tasks:read', 'tasks:write']);

        $response = $this->postJson("/api/v1/projects/{$project->slug}/tasks", [
            'title' => 'Nouvelle tâche',
            'priority' => 'high',
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        $response->assertCreated()->assertJsonStructure([
            'data' => ['id', 'project_id', 'title', 'status', 'status_label', 'priority', 'due_date', 'is_overdue', 'assignee', 'tags'],
        ]);
    }
}
