<?php

use App\Enums\TaskStatus;
use App\Events\TaskMoved;
use App\Models\Project;
use App\Models\Task;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

it('casts status to the TaskStatus enum', function () {
    $task = Task::factory()->create(['status' => 'in_progress']);

    expect($task->status)->toBeInstanceOf(TaskStatus::class)
        ->and($task->status)->toBe(TaskStatus::InProgress);
});

it('computes is_overdue from due_date and status, never stored', function () {
    $overdue = Task::factory()->create(['due_date' => now()->subDay(), 'status' => 'todo']);
    $doneButPast = Task::factory()->create(['due_date' => now()->subDay(), 'status' => 'done']);
    $noDueDate = Task::factory()->create(['due_date' => null, 'status' => 'todo']);
    $future = Task::factory()->create(['due_date' => now()->addWeek(), 'status' => 'todo']);

    expect($overdue->is_overdue)->toBeTrue()
        ->and($doneButPast->is_overdue)->toBeFalse()
        ->and($noDueDate->is_overdue)->toBeFalse()
        ->and($future->is_overdue)->toBeFalse();
});

it('sets completed_at automatically when the status becomes done, and clears it otherwise', function () {
    $task = Task::factory()->create(['status' => 'todo']);
    expect($task->completed_at)->toBeNull();

    $task->update(['status' => 'done']);
    expect($task->fresh()->completed_at)->not->toBeNull();

    $task->update(['status' => 'todo']);
    expect($task->fresh()->completed_at)->toBeNull();
});

it('dispatches a TaskMoved event when the status changes, and only then', function () {
    Event::fake([TaskMoved::class]);

    $task = Task::factory()->create(['status' => 'todo']);
    Event::assertNotDispatched(TaskMoved::class);

    $task->update(['status' => 'in_progress']);
    Event::assertDispatched(TaskMoved::class, fn (TaskMoved $event) => $event->from === TaskStatus::Todo
        && $event->to === TaskStatus::InProgress);

    Event::fake([TaskMoved::class]);
    $task->update(['title' => 'Un autre titre']);
    Event::assertNotDispatched(TaskMoved::class);
});

it('notifies the assignee when a task is created already assigned', function () {
    Notification::fake();

    $project = Project::factory()->create();
    $assignee = memberOf($project->team);

    Task::factory()->for($project)->create(['assignee_id' => $assignee->id]);

    Notification::assertSentTo($assignee, TaskAssignedNotification::class);
});

it('notifies the new assignee on reassignment, not the previous one', function () {
    $project = Project::factory()->create();
    $first = memberOf($project->team);
    $second = memberOf($project->team);
    $task = Task::factory()->for($project)->create(['assignee_id' => $first->id]);

    Notification::fake();
    $task->update(['assignee_id' => $second->id]);

    Notification::assertSentTo($second, TaskAssignedNotification::class);
    Notification::assertNotSentTo($first, TaskAssignedNotification::class);
});
