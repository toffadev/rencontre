<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PendingMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Le nombre de messages en attente
     */
    protected $pendingMessagesCount;

    /**
     * Create a new notification instance.
     */
    public function __construct(int $pendingMessagesCount)
    {
        $this->pendingMessagesCount = $pendingMessagesCount;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/moderateur/chat');

        return (new MailMessage)
            ->subject('Messages clients en attente ⚠️')
            ->greeting('Bonjour ' . $notifiable->name)
            ->line("Il y a {$this->pendingMessagesCount} messages clients qui attendent une réponse.")
            ->line('Votre intervention est nécessaire pour maintenir une bonne expérience utilisateur.')
            ->action('Répondre aux messages', $url)
            ->line('Merci de votre réactivité!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'pending_messages_count' => $this->pendingMessagesCount
        ];
    }
}
