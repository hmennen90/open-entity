<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EntityController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\MindController;
use App\Http\Controllers\Api\MemoryController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\LlmConfigurationController;

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
    Route::get('/entity/energy', [EntityController::class, 'energy']);
    Route::get('/entity/tools', [EntityController::class, 'tools']);

    // Settings
    Route::get('/settings/language', [SettingsController::class, 'getLanguage']);
    Route::post('/settings/language', [SettingsController::class, 'setLanguage']);

    // LLM Configurations
    Route::get('/llm/drivers', [LlmConfigurationController::class, 'drivers']);
    Route::get('/llm/configurations', [LlmConfigurationController::class, 'index']);
    Route::post('/llm/configurations', [LlmConfigurationController::class, 'store']);
    Route::get('/llm/configurations/{llmConfiguration}', [LlmConfigurationController::class, 'show']);
    Route::put('/llm/configurations/{llmConfiguration}', [LlmConfigurationController::class, 'update']);
    Route::delete('/llm/configurations/{llmConfiguration}', [LlmConfigurationController::class, 'destroy']);
    Route::post('/llm/configurations/{llmConfiguration}/test', [LlmConfigurationController::class, 'test']);
    Route::post('/llm/configurations/{llmConfiguration}/reset', [LlmConfigurationController::class, 'resetCircuitBreaker']);
    Route::post('/llm/configurations/{llmConfiguration}/default', [LlmConfigurationController::class, 'setDefault']);
    Route::post('/llm/configurations/reorder', [LlmConfigurationController::class, 'reorder']);

    // Chat Conversations
    Route::get('/chat/conversations', [ChatController::class, 'index']);
    Route::post('/chat/conversations', [ChatController::class, 'store']);
    Route::get('/chat/conversations/{conversation}', [ChatController::class, 'show']);
    Route::post('/chat/conversations/{conversation}/messages', [ChatController::class, 'sendMessage']);

    // Legacy Chat Endpoints
    Route::post('/chat', [ChatController::class, 'send']);
    Route::post('/chat/retry', [ChatController::class, 'retry']);
    Route::get('/chat/history', [ChatController::class, 'history']);

    // Mind (Thoughts, Personality)
    Route::get('/mind/thoughts', [MindController::class, 'thoughts']);
    Route::get('/mind/personality', [MindController::class, 'personality']);
    Route::get('/mind/interests', [MindController::class, 'interests']);
    Route::get('/mind/opinions', [MindController::class, 'opinions']);

    // Memory (Memories)
    Route::get('/memory', [MemoryController::class, 'index']);
    Route::get('/memory/statistics', [MemoryController::class, 'statistics']);
    Route::get('/memory/experiences', [MemoryController::class, 'experiences']);
    Route::get('/memory/conversations', [MemoryController::class, 'conversations']);
    Route::get('/memory/learned', [MemoryController::class, 'learned']);
    Route::get('/memory/search', [MemoryController::class, 'semanticSearch']);
    Route::get('/memory/layer/{layer}', [MemoryController::class, 'byLayer']);
    Route::get('/memory/summaries', [MemoryController::class, 'summaries']);
    Route::get('/memory/embedding-stats', [MemoryController::class, 'embeddingStats']);
    Route::get('/memory/consolidation-stats', [MemoryController::class, 'consolidationStats']);
    Route::get('/memory/{memory}', [MemoryController::class, 'show']);
    Route::get('/memory/{memory}/related', [MemoryController::class, 'related']);
    Route::get('/memory/{memory}/semantic-related', [MemoryController::class, 'semanticRelated']);

    // Goals
    Route::get('/goals', [GoalController::class, 'index']);
    Route::get('/goals/current', [GoalController::class, 'current']);
    Route::get('/goals/completed', [GoalController::class, 'completed']);

});
