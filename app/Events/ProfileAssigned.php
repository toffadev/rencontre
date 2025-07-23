<?php

namespace App\Events;

use App\Models\Profile;
use App\Models\User;
use App\Models\ModeratorProfileAssignment;
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

    public $moderator;
    public $profileId;
    public $assignmentId;
    public $isShared; // Nouveau champ pour indiquer si le profil est partagé
    public $oldModeratorId; // ID de l'ancien modérateur en cas de réattribution
    public $forced; // Indique si c'est une réattribution forcée
    public $reason; // Raison de la réattribution (ex: "inactivity")

    /**
     * Create a new event instance.
     */
    public function __construct($moderator, $profileId, $assignmentId, $oldModeratorId = null, $reason = null)
    {
        $this->moderator = $moderator;
        $this->profileId = $profileId;
        $this->assignmentId = $assignmentId;
        $this->oldModeratorId = $oldModeratorId;
        $this->reason = $reason;
        $this->forced = $oldModeratorId !== null || $reason === 'inactivity';

        // Vérifier si ce profil est déjà attribué à d'autres modérateurs
        $this->isShared = ModeratorProfileAssignment::where('profile_id', $profileId)
            ->where('is_active', true)
            ->where('id', '!=', $assignmentId)
            ->exists();
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
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        // Vérifiez si le profil existe et a les propriétés nécessaires
        $profileData = [
            'id' => $this->profile->id ?? null,
            'name' => $this->profile->name ?? null,
        ];

        // Vérifiez si is_primary existe avant de l'utiliser
        if (isset($this->profile->is_primary)) {
            $profileData['is_primary'] = $this->profile->is_primary;
        } else {
            $profileData['is_primary'] = false; // ou une valeur par défaut appropriée
        }

        return [
            'profile' => $profileData,
            'moderator' => [
                'id' => $this->moderator->id ?? null,
                'name' => $this->moderator->name ?? null,
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
