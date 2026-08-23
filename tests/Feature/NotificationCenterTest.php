<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;

it('shows the unread count badge and lists notifications', function () {
    $project = Project::factory()->create();
    $user = memberOf($project->team);
    $task = Task::factory()->for($project)->create();

    $user->notify(new TaskAssignedNotification($task));

    $this->actingAs($user)->get('/notifications')
        ->assertOk()
        ->assertSee('1');
});

it('marks a single notification as read', function () {
    $project = Project::factory()->create();
    $user = memberOf($project->team);
    $task = Task::factory()->for($project)->create();
    $user->notify(new TaskAssignedNotification($task));
    $notification = $user->notifications()->first();

    $this->actingAs($user)
        ->post("/notifications/{$notification->id}/read")
        ->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('cannot mark someone elses notification as read', function () {
    $project = Project::factory()->create();
    $user = memberOf($project->team);
    $someoneElse = User::factory()->create();
    $task = Task::factory()->for($project)->create();
    $user->notify(new TaskAssignedNotification($task));
    $notification = $user->notifications()->first();

    $this->actingAs($someoneElse)
        ->post("/notifications/{$notification->id}/read")
        ->assertForbidden();

    expect($notification->fresh()->read_at)->toBeNull();
});

it('marks every unread notification as read at once', function () {
    $project = Project::factory()->create();
    $user = memberOf($project->team);
    $tasks = Task::factory()->for($project)->count(3)->create();

    foreach ($tasks as $task) {
        $user->notify(new TaskAssignedNotification($task));
    }

    $this->actingAs($user)->post('/notifications/read-all')->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
});
