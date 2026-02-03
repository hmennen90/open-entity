<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event wenn ein Tool bei der Ausführung fehlschlägt.
 *
 * Informiert die Entität dass ein Tool einen Laufzeitfehler hatte
 * und möglicherweise nachgebessert werden muss.
 */
class ToolExecutionFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $toolName,
        public array $params,
        public string $error,
        public ?string $file = null,
        public ?int $line = null
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
            'type' => 'tool_execution_failed',
            'tool_name' => $this->toolName,
            'params' => $this->params,
            'error' => $this->error,
            'location' => $this->file ? "{$this->file}:{$this->line}" : null,
            'timestamp' => now()->toIso8601String(),
            'message' => $this->getHumanReadableMessage(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'tool.execution.failed';
    }

    /**
     * Generiere eine menschenlesbare Fehlermeldung.
     */
    public function getHumanReadableMessage(): string
    {
        $location = $this->file ? " in {$this->file} Zeile {$this->line}" : '';

        return "Das Tool '{$this->toolName}' ist bei der Ausführung fehlgeschlagen{$location}: {$this->error}";
    }
}
