<?php

use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// /api/v1 : versionner dès la première version évite d'avoir à retrofit une
// v2 en urgence le jour où un breaking change devient nécessaire.
Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'team.current'])->group(function () {
    Route::apiResource('projects', ProjectController::class);
    Route::apiResource('projects.tasks', TaskController::class)->scoped();
    Route::apiResource('projects.tasks.comments', CommentController::class)
        ->scoped()
        ->only(['index', 'store', 'destroy']);
});
