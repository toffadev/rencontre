<?php

namespace App\Events;

use App\Models\ProfilePointTransaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProfilePointsPurchased implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $transaction;

    /**
     * Créer une nouvelle instance d'événement.
     */
    public function __construct(ProfilePointTransaction $transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * Obtenir les canaux de diffusion de l'événement.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Diffuser sur le canal privé du client
            new PrivateChannel('client.' . $this->transaction->client_id),
            // Diffuser sur le canal privé du modérateur si présent
            $this->transaction->moderator_id
                ? new PrivateChannel('moderator.' . $this->transaction->moderator_id)
                : null,
        ];
    }

    /**
     * Obtenir le nom de l'événement à diffuser.
     */
    public function broadcastAs(): string
    {
        return 'profile.points.purchased';
    }

    /**
     * Obtenir les données à diffuser.
     */
    public function broadcastWith(): array
    {
        return [
            'transaction' => [
                'id' => $this->transaction->id,
                'points_amount' => $this->transaction->points_amount,
                'money_amount' => $this->transaction->money_amount,
                'profile' => [
                    'id' => $this->transaction->profile_id,
                    'name' => $this->transaction->profile->name,
                ],
                'created_at' => $this->transaction->created_at,
            ],
        ];
    }
}
