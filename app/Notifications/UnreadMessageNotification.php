<?php

namespace App\Notifications;

use App\Models\ClientNotification;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UnreadMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @var Message
     */
    protected $message;

    /**
     * @var ClientNotification
     */
    protected $notification;

    /**
     * Create a new notification instance.
     */
    public function __construct(Message $message, ClientNotification $notification)
    {
        $this->message = $message;
        $this->notification = $notification;
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
        $profile = $this->message->profile;
        $utm = "utm_source=email&utm_medium=notification&utm_campaign=unread_message&notification_id={$this->notification->id}";
        $url = route('client.home') . "?{$utm}";

        return (new MailMessage)
            ->subject("Nouveau message de {$profile->name}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("{$profile->name} vous a envoyé un message il y a 30 minutes.")
            ->line("Ne manquez pas cette opportunité de continuer votre conversation !")
            ->action("Voir le message", $url)
            ->line("Si vous avez des questions, n'hésitez pas à nous contacter.")
            ->salutation("Merci, L'équipe " . config('app.name'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message_id' => $this->message->id,
            'profile_id' => $this->message->profile_id,
            'notification_id' => $this->notification->id,
        ];
    }
}
