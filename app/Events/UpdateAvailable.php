<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event when a new OpenEntity version is available.
 *
 * Broadcasts immediately to notify the user in the UI.
 */
class UpdateAvailable implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $currentVersion,
        public string $latestVersion,
        public ?string $releaseUrl = null,
        public ?string $changelog = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('entity.notifications'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'type' => 'update_available',
            'current_version' => $this->currentVersion,
            'latest_version' => $this->latestVersion,
            'release_url' => $this->releaseUrl,
            'changelog' => $this->changelog,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'update.available';
    }
}
