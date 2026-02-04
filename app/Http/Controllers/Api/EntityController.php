<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Entity\EntityService;
use Illuminate\Http\JsonResponse;

class EntityController extends Controller
{
    public function __construct(
        private EntityService $entityService
    ) {}

    /**
     * Current status of the entity.
     */
    public function status(): JsonResponse
    {
        $energyState = $this->entityService->getEnergyState();

        return response()->json([
            'name' => config('entity.name'),
            'status' => $this->entityService->getStatus(),
            'uptime' => $this->entityService->getUptime(),
            'last_thought_at' => $this->entityService->getLastThoughtAt(),
            'energy' => $energyState,
        ]);
    }

    /**
     * Energy state of the entity.
     */
    public function energy(): JsonResponse
    {
        return response()->json([
            'energy' => $this->entityService->getEnergyState(),
            'log' => $this->entityService->getEnergyLog(20),
        ]);
    }

    /**
     * Full state (Mind, current goals, etc.).
     */
    public function state(): JsonResponse
    {
        return response()->json([
            'name' => config('entity.name'),
            'status' => $this->entityService->getStatus(),
            'personality' => $this->entityService->getPersonality(),
            'current_mood' => $this->entityService->getCurrentMood(),
            'active_goals' => $this->entityService->getActiveGoals(),
            'recent_thoughts' => $this->entityService->getRecentThoughts(5),
        ]);
    }

    /**
     * Wake up the entity (start think loop).
     */
    public function wake(): JsonResponse
    {
        $this->entityService->wake();

        return response()->json([
            'success' => true,
            'status' => 'awake',
            'message' => 'Entity is now awake.',
        ]);
    }

    /**
     * Put the entity to sleep (pause think loop).
     */
    public function sleep(): JsonResponse
    {
        $this->entityService->sleep();

        return response()->json([
            'success' => true,
            'status' => 'sleeping',
            'message' => 'Entity is now sleeping.',
        ]);
    }

    /**
     * Personality of the entity.
     */
    public function personality(): JsonResponse
    {
        return response()->json($this->entityService->getPersonality());
    }

    /**
     * Current mood of the entity.
     */
    public function mood(): JsonResponse
    {
        return response()->json($this->entityService->getCurrentMood());
    }

    /**
     * Available tools.
     */
    public function tools(): JsonResponse
    {
        return response()->json([
            'tools' => $this->entityService->getAvailableTools(),
            'failed_tools' => $this->entityService->getFailedTools(),
        ]);
    }
}
