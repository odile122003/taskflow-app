<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamMemberController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/projects');

Route::middleware(['auth', 'verified', 'team.current'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('projects', ProjectController::class);
    Route::get('projects/{project}/board', [ProjectController::class, 'board'])->name('projects.board');
    Route::resource('projects.tasks', TaskController::class)->scoped();

    Route::post('projects/{project}/tasks/{task}/attachments', [AttachmentController::class, 'store'])
        ->name('tasks.attachments.store');
    Route::get('projects/{project}/tasks/{task}/attachments/{attachment}/download', [AttachmentController::class, 'download'])
        ->name('tasks.attachments.download');
    Route::delete('projects/{project}/tasks/{task}/attachments/{attachment}', [AttachmentController::class, 'destroy'])
        ->name('tasks.attachments.destroy');

    Route::post('teams/{team}/members', [TeamMemberController::class, 'store'])->name('teams.members.store');
    Route::get('teams/{team}/invitations/accept', [TeamMemberController::class, 'acceptInvitation'])
        ->middleware('signed')
        ->name('teams.invitations.accept');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
