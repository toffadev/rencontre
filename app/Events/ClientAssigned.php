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

class ClientAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The moderator instance.
     *
     * @var \App\Models\User
     */
    public $moderator;

    /**
     * The client instance.
     *
     * @var \App\Models\User
     */
    public $client;

    /**
     * The profile instance.
     *
     * @var \App\Models\Profile
     */
    public $profile;

    /**
     * Create a new event instance.
     */
    public function __construct(User $moderator, User $client, Profile $profile)
    {
        $this->moderator = $moderator;
        $this->client = $client;
        $this->profile = $profile;
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
        return 'client.assigned';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'client' => [
                'id' => $this->client->id,
                'name' => $this->client->name,
            ],
            'profile' => [
                'id' => $this->profile->id,
                'name' => $this->profile->name,
            ]
        ];
    }
}
