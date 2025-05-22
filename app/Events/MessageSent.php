<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Le message envoyé.
     *
     * @var \App\Models\Message
     */
    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        // Canal privé pour le client
        $clientChannel = new PrivateChannel('client.' . $this->message->client_id);

        // Canal privé pour le profil (utilisé par les modérateurs)
        $profileChannel = new PrivateChannel('profile.' . $this->message->profile_id);

        return [$clientChannel, $profileChannel];
    }

    /**
     * Nom de l'événement pour le frontend.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'message.sent';
    }

    /**
     * Données à envoyer avec l'événement.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'content' => $this->message->content,
            'client_id' => $this->message->client_id,
            'profile_id' => $this->message->profile_id,
            'is_from_client' => $this->message->is_from_client,
            'created_at' => $this->message->created_at->toDateTimeString(),
        ];
    }
}
