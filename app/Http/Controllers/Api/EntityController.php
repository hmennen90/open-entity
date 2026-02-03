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
     * Aktueller Status der Entität.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'name' => config('entity.name'),
            'status' => $this->entityService->getStatus(),
            'uptime' => $this->entityService->getUptime(),
            'last_thought_at' => $this->entityService->getLastThoughtAt(),
        ]);
    }

    /**
     * Vollständiger Zustand (Mind, aktuelle Ziele, etc.).
     */
    public function state(): JsonResponse
    {
        return response()->json([
            'personality' => $this->entityService->getPersonality(),
            'current_mood' => $this->entityService->getCurrentMood(),
            'active_goals' => $this->entityService->getActiveGoals(),
            'recent_thoughts' => $this->entityService->getRecentThoughts(5),
        ]);
    }

    /**
     * Entität "aufwecken" (Think Loop starten).
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
     * Entität "schlafen legen" (Think Loop pausieren).
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
     * Persönlichkeit der Entität.
     */
    public function personality(): JsonResponse
    {
        return response()->json($this->entityService->getPersonality());
    }

    /**
     * Aktuelle Stimmung der Entität.
     */
    public function mood(): JsonResponse
    {
        return response()->json($this->entityService->getCurrentMood());
    }

    /**
     * Verfügbare Tools.
     */
    public function tools(): JsonResponse
    {
        return response()->json([
            'tools' => $this->entityService->getAvailableTools(),
            'failed_tools' => $this->entityService->getFailedTools(),
        ]);
    }
}
