<?php

use App\Http\Controllers\DemoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Route de démo temporaire — Module 0, preuve que le service container et la façade
// résolvent la même instance singleton. À retirer une fois le Module 0 validé.
Route::get('/demo/task-number', [DemoController::class, 'taskNumber']);
