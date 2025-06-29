<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Événement qui est diffusé quand la position d'un modérateur dans la file d'attente change.
 * 
 * Il notifie le modérateur concerné (canal privé) de :
 * - Sa nouvelle position dans la file.
 * - Le temps d'attente estimé avant d'être servi.
 * - Le nombre de profils disponibles à ce moment.
 * 
 * Cet événement permet une mise à jour temps réel de l'interface modérateur
 * pour suivre leur position dans la file d'attente.
 */

class ModeratorQueuePositionChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $moderatorId;
    public $position;
    public $estimatedWaitTime;
    public $availableProfiles;

    /**
     * Create a new event instance.
     */
    public function __construct($moderatorId, $position, $estimatedWaitTime, $availableProfiles = [])
    {
        $this->moderatorId = $moderatorId;
        $this->position = $position;
        $this->estimatedWaitTime = $estimatedWaitTime;
        $this->availableProfiles = $availableProfiles;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('moderator.' . $this->moderatorId),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'queue.position.changed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'position' => $this->position,
            'estimated_wait_time' => $this->estimatedWaitTime,
            'available_profiles_count' => count($this->availableProfiles),
            'timestamp' => now()->toIso8601String()
        ];
    }
}
