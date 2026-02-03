<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event wenn die Entität ein neues Tool erstellt hat.
 */
class ToolCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $toolName,
        public string $filePath,
        public string $description,
        public bool $loadedSuccessfully = true,
        public array $warnings = []
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
            'type' => 'tool_created',
            'tool_name' => $this->toolName,
            'file_path' => $this->filePath,
            'description' => $this->description,
            'loaded_successfully' => $this->loadedSuccessfully,
            'warnings' => $this->warnings,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'tool.created';
    }
}
