<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::resource('projects', ProjectController::class);
Route::resource('projects.tasks', TaskController::class)->scoped();
