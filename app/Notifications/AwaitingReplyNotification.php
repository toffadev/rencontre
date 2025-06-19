<?php

namespace App\Notifications;

use App\Models\ClientNotification;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AwaitingReplyNotification extends Notification implements ShouldQueue
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
     * @var int
     */
    protected $pendingCount;

    /**
     * Create a new notification instance.
     */
    public function __construct(Message $message, ClientNotification $notification, int $pendingCount = 1)
    {
        $this->message = $message;
        $this->notification = $notification;
        $this->pendingCount = $pendingCount;
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
        $utm = "utm_source=email&utm_medium=notification&utm_campaign=awaiting_reply&notification_id={$this->notification->id}";
        $url = route('client.home') . "?{$utm}";

        return (new MailMessage)
            ->subject("{$profile->name} attend votre réponse")
            ->view('emails.awaiting-reply', [
                'user' => $notifiable,
                'profile' => $profile,
                'message' => $this->message,
                'url' => $url
            ]);
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
