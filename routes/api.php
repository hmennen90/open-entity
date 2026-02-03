<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EntityController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\MindController;
use App\Http\Controllers\Api\MemoryController;
use App\Http\Controllers\Api\GoalController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // Entity Status & Control
    Route::get('/entity/status', [EntityController::class, 'status']);
    Route::get('/entity/state', [EntityController::class, 'state']);
    Route::post('/entity/wake', [EntityController::class, 'wake']);
    Route::post('/entity/sleep', [EntityController::class, 'sleep']);
    Route::get('/entity/personality', [EntityController::class, 'personality']);
    Route::get('/entity/mood', [EntityController::class, 'mood']);
    Route::get('/entity/tools', [EntityController::class, 'tools']);

    // Chat Conversations
    Route::get('/chat/conversations', [ChatController::class, 'index']);
    Route::post('/chat/conversations', [ChatController::class, 'store']);
    Route::get('/chat/conversations/{conversation}', [ChatController::class, 'show']);
    Route::post('/chat/conversations/{conversation}/messages', [ChatController::class, 'sendMessage']);

    // Legacy Chat Endpoints
    Route::post('/chat', [ChatController::class, 'send']);
    Route::post('/chat/retry', [ChatController::class, 'retry']);
    Route::get('/chat/history', [ChatController::class, 'history']);

    // Mind (Gedanken, Persönlichkeit)
    Route::get('/mind/thoughts', [MindController::class, 'thoughts']);
    Route::get('/mind/personality', [MindController::class, 'personality']);
    Route::get('/mind/interests', [MindController::class, 'interests']);
    Route::get('/mind/opinions', [MindController::class, 'opinions']);

    // Memory (Erinnerungen)
    Route::get('/memory', [MemoryController::class, 'index']);
    Route::get('/memory/statistics', [MemoryController::class, 'statistics']);
    Route::get('/memory/experiences', [MemoryController::class, 'experiences']);
    Route::get('/memory/conversations', [MemoryController::class, 'conversations']);
    Route::get('/memory/learned', [MemoryController::class, 'learned']);
    Route::get('/memory/{memory}', [MemoryController::class, 'show']);
    Route::get('/memory/{memory}/related', [MemoryController::class, 'related']);

    // Goals (Ziele)
    Route::get('/goals', [GoalController::class, 'index']);
    Route::get('/goals/current', [GoalController::class, 'current']);
    Route::get('/goals/completed', [GoalController::class, 'completed']);

});
