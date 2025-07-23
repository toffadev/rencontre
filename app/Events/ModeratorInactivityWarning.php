<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ModeratorInactivityWarning implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $moderatorId;
    public $profileId;
    public $remainingSeconds;
    public $timestamp;
    public $warningLevel; // Ajout pour différents niveaux d'alerte

    /**
     * Create a new event instance.
     */
    public function __construct($moderatorId, $profileId, $remainingSeconds)
    {
        $this->moderatorId = $moderatorId;
        $this->profileId = $profileId;
        $this->remainingSeconds = $remainingSeconds;
        $this->timestamp = now();

        // Déterminer le niveau d'urgence
        $this->warningLevel = $this->determineWarningLevel($remainingSeconds);
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
        return 'inactivity.warning';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'moderator_id' => $this->moderatorId,
            'profile_id' => $this->profileId,
            'remaining_seconds' => $this->remainingSeconds,
            'remaining_formatted' => $this->formatRemainingTime(),
            'timestamp' => $this->timestamp->toISOString(),
            'warning_level' => $this->warningLevel,
            'type' => 'warning'
        ];
    }

    /**
     * Détermine le niveau d'urgence de l'avertissement
     */
    private function determineWarningLevel($remainingSeconds)
    {
        if ($remainingSeconds <= 10) {
            return 'critical';
        } elseif ($remainingSeconds <= 20) {
            return 'high';
        } else {
            return 'medium';
        }
    }

    /**
     * Formate le temps restant pour l'affichage
     */
    private function formatRemainingTime()
    {
        $minutes = floor($this->remainingSeconds / 60);
        $seconds = $this->remainingSeconds % 60;

        if ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $seconds);
        } else {
            return sprintf('%ds', $seconds);
        }
    }
}
