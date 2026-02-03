<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Entity\MemoryService;
use App\Models\Memory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemoryController extends Controller
{
    public function __construct(
        private MemoryService $memoryService
    ) {}

    /**
     * Alle Erinnerungen (paginiert).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'type' => 'nullable|string|in:experience,conversation,learned,social,reflection',
            'search' => 'nullable|string|max:255',
            'min_importance' => 'nullable|numeric|min:0|max:1',
        ]);

        $query = Memory::latest();

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('content', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%")
                    ->orWhere('related_entity', 'like', "%{$search}%");
            });
        }

        if ($request->min_importance) {
            $query->where('importance', '>=', $request->min_importance);
        }

        $memories = $query->paginate($request->per_page ?? 20);

        return response()->json($memories);
    }

    /**
     * Einzelne Erinnerung abrufen.
     */
    public function show(Memory $memory): JsonResponse
    {
        return response()->json([
            'data' => $memory,
        ]);
    }

    /**
     * Verwandte Erinnerungen finden.
     */
    public function related(Memory $memory): JsonResponse
    {
        // Find related memories based on:
        // 1. Same related_entity
        // 2. Same type
        // 3. Similar content (basic keyword matching)

        $related = collect();

        // Same entity memories
        if ($memory->related_entity) {
            $sameEntity = Memory::where('id', '!=', $memory->id)
                ->where('related_entity', $memory->related_entity)
                ->latest()
                ->limit(3)
                ->get();
            $related = $related->merge($sameEntity);
        }

        // Same type memories (if we need more)
        if ($related->count() < 5) {
            $sameType = Memory::where('id', '!=', $memory->id)
                ->where('type', $memory->type)
                ->whereNotIn('id', $related->pluck('id'))
                ->latest()
                ->limit(5 - $related->count())
                ->get();
            $related = $related->merge($sameType);
        }

        return response()->json([
            'data' => $related->take(5)->values(),
        ]);
    }

    /**
     * Memory Statistiken.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total' => Memory::count(),
            'by_type' => Memory::selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),
            'important' => Memory::where('importance', '>=', 0.7)->count(),
            'recent' => Memory::where('created_at', '>=', now()->subDays(7))->count(),
            'average_importance' => Memory::avg('importance') ?? 0,
        ];

        return response()->json($stats);
    }

    /**
     * Erlebnisse.
     */
    public function experiences(Request $request): JsonResponse
    {
        $memories = Memory::where('type', 'experience')
            ->latest()
            ->limit($request->limit ?? 20)
            ->get();

        return response()->json([
            'experiences' => $memories,
        ]);
    }

    /**
     * Gespräche als Erinnerungen.
     */
    public function conversations(Request $request): JsonResponse
    {
        $memories = Memory::where('type', 'conversation')
            ->latest()
            ->limit($request->limit ?? 20)
            ->get();

        return response()->json([
            'conversations' => $memories,
        ]);
    }

    /**
     * Gelerntes Wissen.
     */
    public function learned(Request $request): JsonResponse
    {
        $memories = Memory::where('type', 'learned')
            ->latest()
            ->limit($request->limit ?? 20)
            ->get();

        return response()->json([
            'learned' => $memories,
        ]);
    }
}
