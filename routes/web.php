<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/projects');

Route::get('/dashboard', DashboardController::class)->name('dashboard');

Route::resource('projects', ProjectController::class);
Route::get('projects/{project}/board', [ProjectController::class, 'board'])->name('projects.board');
Route::resource('projects.tasks', TaskController::class)->scoped();
