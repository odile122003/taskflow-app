<?php

use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskImportController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\TeamStatsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/projects');

Route::middleware(['auth', 'verified', 'team.current'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/team/stats', TeamStatsController::class)->name('team.stats');

    Route::resource('projects', ProjectController::class);
    Route::get('projects/{project}/board', [ProjectController::class, 'board'])->name('projects.board');
    Route::resource('projects.tasks', TaskController::class)->scoped();
    Route::patch('projects/{project}/tasks/{task}/move', [TaskController::class, 'move'])
        ->scopeBindings()
        ->name('tasks.move');

    Route::post('projects/{project}/tasks/{task}/attachments', [AttachmentController::class, 'store'])
        ->name('tasks.attachments.store');
    Route::get('projects/{project}/tasks/{task}/attachments/{attachment}/download', [AttachmentController::class, 'download'])
        ->name('tasks.attachments.download');
    Route::delete('projects/{project}/tasks/{task}/attachments/{attachment}', [AttachmentController::class, 'destroy'])
        ->name('tasks.attachments.destroy');

    Route::post('projects/{project}/tasks/import', [TaskImportController::class, 'store'])->name('tasks.import.store');
    Route::get('imports/{batch}', [TaskImportController::class, 'show'])->name('imports.show');

    Route::post('teams/{team}/members', [TeamMemberController::class, 'store'])->name('teams.members.store');
    Route::get('teams/{team}/invitations/accept', [TeamMemberController::class, 'acceptInvitation'])
        ->middleware('signed')
        ->name('teams.invitations.accept');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    Route::post('/profile/tokens', [ApiTokenController::class, 'store'])->name('tokens.store');
    Route::delete('/profile/tokens/{token}', [ApiTokenController::class, 'destroy'])->name('tokens.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
