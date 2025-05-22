<?php

namespace App\Events;

use App\Models\ModeratorProfileAssignment;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ProfileAssigned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * L'attribution de profil.
     *
     * @var \App\Models\ModeratorProfileAssignment
     */
    public $assignment;

    /**
     * Le modérateur.
     *
     * @var \App\Models\User
     */
    public $moderator;

    /**
     * Le profil.
     *
     * @var \App\Models\Profile
     */
    public $profile;

    /**
     * Create a new event instance.
     */
    public function __construct(ModeratorProfileAssignment $assignment)
    {
        $this->assignment = $assignment;
        $this->moderator = $assignment->user;
        $this->profile = $assignment->profile;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Canal privé pour le modérateur
        return [new PrivateChannel('moderator.' . $this->moderator->id)];
    }

    /**
     * Nom de l'événement pour le frontend.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'profile.assigned';
    }

    /**
     * Données à envoyer avec l'événement.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'assignment_id' => $this->assignment->id,
            'moderator_id' => $this->moderator->id,
            'profile' => [
                'id' => $this->profile->id,
                'name' => $this->profile->name,
                'gender' => $this->profile->gender,
                'bio' => $this->profile->bio,
                'main_photo_path' => $this->profile->main_photo_path,
            ],
            'is_active' => $this->assignment->is_active,
            'assigned_at' => $this->assignment->created_at->toDateTimeString(),
        ];
    }
}
