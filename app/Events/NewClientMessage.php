<?php

namespace App\Events;

use App\Models\Message;
use App\Models\Profile;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewClientMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $profile;
    public $unreadCount;

    /**
     * Create a new event instance.
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
        $this->profile = Profile::find($message->profile_id);

        // Calculer le nombre de messages non lus pour ce profil
        $this->unreadCount = Message::where('profile_id', $message->profile_id)
            ->where('is_from_client', true)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('moderators'), // Canal pour tous les modérateurs
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'content' => $this->message->content,
                'client_id' => $this->message->client_id,
                'profile_id' => $this->message->profile_id,
                'created_at' => $this->message->created_at,
            ],
            'profile' => [
                'id' => $this->profile->id,
                'name' => $this->profile->name,
                'photo_url' => $this->profile->main_photo_path,
            ],
            'unread_count' => $this->unreadCount,
        ];
    }
}
