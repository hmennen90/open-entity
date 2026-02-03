<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Entity\MindService;
use App\Models\Thought;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MindController extends Controller
{
    public function __construct(
        private MindService $mindService
    ) {}

    /**
     * Letzte Gedanken abrufen.
     */
    public function thoughts(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'type' => 'nullable|string|in:observation,reflection,decision,emotion,curiosity',
        ]);

        $query = Thought::latest();

        if ($request->type) {
            $query->where('type', $request->type);
        }

        $thoughts = $query->limit($request->limit ?? 20)->get();

        return response()->json([
            'thoughts' => $thoughts,
        ]);
    }

    /**
     * Persönlichkeit der Entität.
     */
    public function personality(): JsonResponse
    {
        return response()->json([
            'personality' => $this->mindService->getPersonality(),
        ]);
    }

    /**
     * Aktuelle Interessen.
     */
    public function interests(): JsonResponse
    {
        return response()->json([
            'interests' => $this->mindService->getInterests(),
        ]);
    }

    /**
     * Meinungen die sich gebildet haben.
     */
    public function opinions(): JsonResponse
    {
        return response()->json([
            'opinions' => $this->mindService->getOpinions(),
        ]);
    }
}
