<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event wenn ein Tool nicht geladen werden kann.
 *
 * Informiert die Entität dass ein selbst geschriebenes Tool
 * Fehler enthält und nachgebessert werden muss.
 */
class ToolLoadFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $filePath,
        public string $stage,
        public array $errors
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('entity.mind'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'tool_load_failed',
            'file_path' => basename($this->filePath),
            'stage' => $this->stage,
            'errors' => array_map(fn ($e) => htmlspecialchars($e, ENT_QUOTES, 'UTF-8'), $this->errors),
            'timestamp' => now()->toIso8601String(),
            'message' => htmlspecialchars($this->getHumanReadableMessage(), ENT_QUOTES, 'UTF-8'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'tool.load.failed';
    }

    /**
     * Generiere eine menschenlesbare Fehlermeldung.
     */
    public function getHumanReadableMessage(): string
    {
        $filename = basename($this->filePath);

        return match($this->stage) {
            'syntax' => "Das Tool '{$filename}' hat Syntaxfehler: " . implode(', ', $this->errors),
            'interface' => "Das Tool '{$filename}' implementiert nicht alle erforderlichen Methoden: " . implode(', ', $this->errors),
            'security' => "Das Tool '{$filename}' verwendet verbotene Funktionen: " . implode(', ', $this->errors),
            'runtime' => "Das Tool '{$filename}' konnte nicht geladen werden: " . implode(', ', $this->errors),
            default => "Das Tool '{$filename}' hat einen unbekannten Fehler: " . implode(', ', $this->errors),
        };
    }
}
