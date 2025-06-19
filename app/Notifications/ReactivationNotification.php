<?php

namespace App\Notifications;

use App\Models\ClientNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReactivationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @var ClientNotification
     */
    protected $notification;

    /**
     * Create a new notification instance.
     */
    public function __construct(ClientNotification $notification)
    {
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
        $utm = "utm_source=email&utm_medium=notification&utm_campaign=reactivation&notification_id={$this->notification->id}";
        $url = route('client.home') . "?{$utm}";

        return (new MailMessage)
            ->subject("Vous nous manquez, {$notifiable->name} !")
            ->view('emails.reactivation', [
                'user' => $notifiable,
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
            'notification_id' => $this->notification->id,
        ];
    }
}
