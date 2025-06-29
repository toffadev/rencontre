<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ModeratorActivityEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $moderatorId;
    public $profileId;
    public $clientId;
    public $activityType;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct($moderatorId, $profileId, $clientId, $activityType)
    {
        $this->moderatorId = $moderatorId;
        $this->profileId = $profileId;
        $this->clientId = $clientId;
        $this->activityType = $activityType; // 'typing', 'reading', 'idle', etc.
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Diffuser sur le canal du profil pour que tous les modérateurs
        // qui partagent ce profil puissent voir l'activité
        return [
            new PrivateChannel('profile.' . $this->profileId),
        ];
    }
}
