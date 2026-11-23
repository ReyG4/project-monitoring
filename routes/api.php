<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskWeightController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\ProjectBudgetController;
use App\Http\Controllers\Api\ProjectWorkflowController;
use App\Http\Controllers\Api\DashboardController;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::apiResource('projects', ProjectController::class);
    Route::apiResource('projects.tasks', TaskController::class)->shallow();
    Route::put('projects/{project}/weights', [TaskWeightController::class, 'update']);
    Route::apiResource('tasks.expenses', ExpenseController::class)->shallow()->except('show');
    Route::get('projects/{project}/budget', [ProjectBudgetController::class, 'show']);
    Route::get('projects/{project}/readiness', [ProjectWorkflowController::class, 'readiness']);
    Route::post('projects/{project}/activate', [ProjectWorkflowController::class, 'activate']);
    Route::post('projects/{project}/submit', [ProjectWorkflowController::class, 'submit']);
    Route::post('projects/{project}/review', [ProjectWorkflowController::class, 'review']);
    Route::get('projects/{project}/reviews', [ProjectWorkflowController::class, 'reviews']);
    Route::get('dashboard', DashboardController::class);
});