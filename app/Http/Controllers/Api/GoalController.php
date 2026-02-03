<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Goal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    /**
     * Alle Ziele.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|string|in:active,paused,completed,abandoned',
            'type' => 'nullable|string|in:curiosity,social,learning,creative,self-improvement',
        ]);

        $query = Goal::latest();

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        return response()->json([
            'goals' => $query->get(),
        ]);
    }

    /**
     * Aktive Ziele.
     */
    public function current(): JsonResponse
    {
        $goals = Goal::active()
            ->orderByDesc('priority')
            ->get();

        return response()->json([
            'goals' => $goals,
        ]);
    }

    /**
     * Abgeschlossene Ziele.
     */
    public function completed(): JsonResponse
    {
        $goals = Goal::completed()
            ->latest('completed_at')
            ->get();

        return response()->json([
            'goals' => $goals,
        ]);
    }
}
