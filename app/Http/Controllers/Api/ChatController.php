<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Entity\EntityService;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        private EntityService $entityService
    ) {}

    /**
     * Liste aller Gespräche.
     */
    public function index(): JsonResponse
    {
        $conversations = Conversation::with(['messages' => function ($query) {
            $query->latest()->limit(1);
        }])
            ->latest()
            ->paginate(20);

        return response()->json($conversations);
    }

    /**
     * Neues Gespräch erstellen.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'participant' => 'required|string|max:255',
            'channel' => 'nullable|string|max:50',
        ]);

        $conversation = Conversation::create([
            'participant' => $request->participant,
            'participant_type' => 'human',
            'channel' => $request->channel ?? 'web',
        ]);

        return response()->json([
            'data' => $conversation,
        ], 201);
    }

    /**
     * Ein einzelnes Gespräch mit Nachrichten.
     */
    public function show(Conversation $conversation): JsonResponse
    {
        $conversation->load('messages');

        return response()->json([
            'data' => $conversation,
        ]);
    }

    /**
     * Nachricht zu einem Gespräch hinzufügen.
     */
    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:10000',
        ]);

        // Nachricht des Benutzers speichern
        $userMessage = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'human',
            'content' => $request->message,
        ]);

        // Antwort der Entität generieren
        $response = $this->entityService->chat($conversation, $request->message);

        // Antwort speichern
        $entityMessage = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'entity',
            'content' => $response['message'],
            'metadata' => $response['metadata'] ?? null,
        ]);

        return response()->json([
            'user_message' => $userMessage,
            'entity_message' => $entityMessage,
            'thought_process' => $response['thought_process'] ?? null,
        ]);
    }

    /**
     * Nachricht an die Entität senden (Legacy).
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:10000',
            'conversation_id' => 'nullable|exists:conversations,id',
            'participant_name' => 'nullable|string|max:255',
        ]);

        // Ermittle den Participant-Namen
        $participantName = $request->participant_name;
        if (!$participantName || $participantName === 'Anonymous') {
            $participantName = $this->getDefaultParticipantName();
        }

        // Gespräch finden oder erstellen
        $conversation = $request->conversation_id
            ? Conversation::findOrFail($request->conversation_id)
            : Conversation::create([
                'participant' => $participantName,
                'participant_type' => 'human',
                'channel' => 'web',
            ]);

        // Nachricht des Benutzers speichern
        $userMessage = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'human',
            'content' => $request->message,
        ]);

        // Antwort der Entität generieren (mit Error-Handling)
        try {
            $response = $this->entityService->chat($conversation, $request->message);

            // Antwort speichern
            $entityMessage = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'entity',
                'content' => $response['message'],
                'metadata' => $response['metadata'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'conversation_id' => $conversation->id,
                'user_message_id' => $userMessage->id,
                'message' => $entityMessage->content,
                'thought_process' => $response['thought_process'] ?? null,
                'created_at' => $entityMessage->created_at,
            ]);
        } catch (\Exception $e) {
            // System-Fehlermeldung speichern
            $errorMessage = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'system',
                'content' => 'Ich kann gerade nicht antworten. Bitte prüfe die Modelanbindung.',
                'metadata' => ['error' => $e->getMessage()],
            ]);

            return response()->json([
                'success' => false,
                'conversation_id' => $conversation->id,
                'user_message_id' => $userMessage->id,
                'message' => $errorMessage->content,
                'error' => true,
                'error_details' => config('app.debug') ? $e->getMessage() : null,
                'created_at' => $errorMessage->created_at,
            ]);
        }
    }

    /**
     * Nachricht erneut senden (Retry).
     */
    public function retry(Request $request): JsonResponse
    {
        $request->validate([
            'message_id' => 'required|exists:messages,id',
        ]);

        $originalMessage = Message::findOrFail($request->message_id);
        $conversation = $originalMessage->conversation;

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'error' => 'Conversation for this message no longer exists',
            ], 404);
        }

        // Lösche vorherige Fehler-Antwort falls vorhanden
        Message::where('conversation_id', $conversation->id)
            ->where('role', 'system')
            ->where('created_at', '>', $originalMessage->created_at)
            ->delete();

        // Erneut versuchen
        try {
            $response = $this->entityService->chat($conversation, $originalMessage->content);

            $entityMessage = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'entity',
                'content' => $response['message'],
                'metadata' => $response['metadata'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'conversation_id' => $conversation->id,
                'message' => $entityMessage->content,
                'thought_process' => $response['thought_process'] ?? null,
                'created_at' => $entityMessage->created_at,
            ]);
        } catch (\Exception $e) {
            $errorMessage = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'system',
                'content' => 'Ich kann gerade nicht antworten. Bitte prüfe die Modelanbindung.',
                'metadata' => ['error' => $e->getMessage()],
            ]);

            return response()->json([
                'success' => false,
                'conversation_id' => $conversation->id,
                'message' => $errorMessage->content,
                'error' => true,
                'error_details' => config('app.debug') ? $e->getMessage() : null,
                'created_at' => $errorMessage->created_at,
            ]);
        }
    }

    /**
     * Chat-Historie abrufen.
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'conversation_id' => 'nullable|exists:conversations,id',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        if ($request->conversation_id) {
            $conversation = Conversation::with('messages')->find($request->conversation_id);

            return response()->json([
                'conversation' => $conversation,
            ]);
        }

        // Letzte Gespräche
        $conversations = Conversation::with(['messages' => function ($query) {
            $query->latest()->limit(5);
        }])
            ->latest()
            ->limit($request->limit ?? 10)
            ->get();

        return response()->json([
            'conversations' => $conversations,
        ]);
    }

    /**
     * Hole den Standard-Teilnehmernamen aus USER.md
     */
    private function getDefaultParticipantName(): string
    {
        $userMdPath = storage_path('app/public/workspace/USER.md');

        if (file_exists($userMdPath)) {
            $content = file_get_contents($userMdPath);

            if (preg_match('/\*\*What to call them:\*\*\s*(.+)/m', $content, $matches)) {
                $name = trim($matches[1]);
                if (!empty($name)) {
                    return $name;
                }
            }

            if (preg_match('/\*\*Name:\*\*\s*(.+)/m', $content, $matches)) {
                $name = trim($matches[1]);
                if (!empty($name)) {
                    return $name;
                }
            }
        }

        return 'Anonymous';
    }
}
