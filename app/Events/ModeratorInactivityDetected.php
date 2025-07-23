<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ModeratorInactivityDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $moderatorId;
    public $profileId;
    public $clientId;
    public $timestamp;
    public $reason;
    public $assignmentId; // Ajout pour traçabilité

    /**
     * Create a new event instance.
     */
    public function __construct(
        $moderatorId,
        $profileId,
        $clientId = null,
        $assignmentId = null,  // Nouveau paramètre
        $reason = 'inactivity'
    ) {
        Log::info("📡 [DEBUG] Événement ModeratorInactivityDetected créé", [
            'moderator_id' => $moderatorId,
            'profile_id' => $profileId,
            'client_id' => $clientId
        ]);
        $this->moderatorId = $moderatorId;
        $this->profileId = $profileId;
        $this->clientId = $clientId;
        $this->assignmentId = $assignmentId ?? $this->getAssignmentId($moderatorId, $profileId); // Utilise la valeur fournie ou cherche
        $this->timestamp = now();
        $this->reason = $reason;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('moderator.' . $this->moderatorId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'inactivity.timeout';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'moderator_id' => $this->moderatorId,
            'profile_id' => $this->profileId,
            'client_id' => $this->clientId,
            'assignment_id' => $this->assignmentId,
            'timestamp' => $this->timestamp->toISOString(),
            'reason' => $this->reason,
            'type' => 'timeout'
        ];
    }

    /**
     * Récupère l'ID d'assignation
     */
    private function getAssignmentId($moderatorId, $profileId)
    {
        return \App\Models\ModeratorProfileAssignment::where('user_id', $moderatorId)  // 'user_id' au lieu de 'moderator_id'
            ->where('profile_id', $profileId)
            ->where('is_active', true)  // 'is_active' au lieu de 'status'
            ->value('id');  // Récupère directement l'ID
    }
}
