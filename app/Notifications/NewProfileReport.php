<?php

namespace App\Notifications;

use App\Models\ProfileReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewProfileReport extends Notification implements ShouldQueue
{
    use Queueable;

    protected $report;

    public function __construct(ProfileReport $report)
    {
        $this->report = $report;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $reportedUser = $this->report->reportedUser;
        $reporter = $this->report->reporter;

        return (new MailMessage)
            ->subject('Nouveau signalement de profil')
            ->line("Un nouveau profil a été signalé.")
            ->line("Profil signalé : {$reportedUser->name}")
            ->line("Signalé par : {$reporter->name}")
            ->line("Raison : {$this->report->reason}")
            ->line("Description : {$this->report->description}")
            ->action('Voir le signalement', route('admin.reports.show', $this->report->id));
    }

    public function toArray($notifiable)
    {
        return [
            'report_id' => $this->report->id,
            'reported_user_id' => $this->report->reported_user_id,
            'reporter_id' => $this->report->reporter_id,
            'reason' => $this->report->reason,
        ];
    }
}
