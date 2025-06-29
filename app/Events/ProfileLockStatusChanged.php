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
 * Événement diffusé lorsqu'un profil est verrouillé ou déverrouillé.
 * 
 * Cet événement informe en temps réel :
 * - Le canal privé du profil concerné,
 * - Et, si applicable, le canal privé du modérateur qui a verrouillé/déverrouillé ce profil.
 * 
 * Propriétés :
 * - $profileId : l'identifiant du profil concerné.
 * - $status : statut du verrou, soit 'locked' (verrouillé) ou 'unlocked' (déverrouillé).
 * - $moderatorId : (optionnel) identifiant du modérateur lié à cette action.
 * - $duration : (optionnel) durée en secondes du verrouillage.
 * - $timestamp : moment de la création de l'événement.
 * 
 * Utilisation :
 * Permet d'informer l'interface utilisateur et d'autres systèmes
 * qu'un profil est désormais indisponible ou libéré, avec un suivi du temps restant.
 */
class ProfileLockStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $profileId;
    public $status;
    public $moderatorId;
    public $duration;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct($profileId, $status, $moderatorId = null, $duration = null)
    {
        $this->profileId = $profileId;
        $this->status = $status; // 'locked' ou 'unlocked'
        $this->moderatorId = $moderatorId;
        $this->duration = $duration;
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('profile.' . $this->profileId),
        ];

        // Si un modérateur est concerné, diffuser également sur son canal
        if ($this->moderatorId) {
            $channels[] = new PrivateChannel('moderator.' . $this->moderatorId);
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'profile.lock.status';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'profile_id' => $this->profileId,
            'status' => $this->status,
            'moderator_id' => $this->moderatorId,
            'duration' => $this->duration,
            'timestamp' => $this->timestamp,
            'expires_at' => $this->duration ? now()->addSeconds($this->duration)->toIso8601String() : null
        ];
    }
}
