<?php

namespace App\Events;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProfileAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The moderator instance.
     *
     * @var \App\Models\User
     */
    public $moderator;

    /**
     * The profile instance.
     *
     * @var \App\Models\Profile
     */
    public $profile;

    public $isPrimary;
    public $clientId;

    /**
     * Create a new event instance.
     */
    public function __construct(User $moderator, Profile $profile, bool $isPrimary = false, ?int $clientId = null)
    {
        $this->moderator = $moderator;
        $this->profile = $profile;
        $this->isPrimary = $isPrimary;
        $this->clientId = $clientId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('moderator.' . $this->moderator->id),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'profile.assigned';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'profile' => $this->profile,
            'is_primary' => $this->isPrimary,
            'client_id' => $this->clientId,
        ];
    }
}
