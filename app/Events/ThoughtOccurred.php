<?php

namespace App\Events;

use App\Models\Thought;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event when a new thought occurs.
 */
class ThoughtOccurred implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Thought $thought
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('entity.mind'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $data = [
            'id' => $this->thought->id,
            'type' => $this->thought->type,
            'content' => $this->thought->content,
            'intensity' => $this->thought->intensity,
            'led_to_action' => $this->thought->led_to_action,
            'action_taken' => $this->thought->action_taken,
            'created_at' => $this->thought->created_at->toIso8601String(),
        ];

        // Include tool execution details if present
        $context = $this->thought->context;
        if (!empty($context['tool_execution'])) {
            $data['tool_execution'] = $context['tool_execution'];
        }

        return $data;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'thought.occurred';
    }
}
