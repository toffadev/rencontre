<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use App\Models\ProfileReport;

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
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'report_id' => $this->report->id,
            'reporter_name' => $this->report->reporter->name,
            'reported_profile_id' => $this->report->reported_profile_id,
            'reported_profile_name' => $this->report->reportedProfile->name,
            'reported_user_id' => $this->report->reported_user_id,
            'reason' => $this->report->reason,
            'description' => $this->report->description,
            'created_at' => $this->report->created_at,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'report_id' => $this->report->id,
            'reporter_name' => $this->report->reporter->name,
            'reported_profile_name' => $this->report->reportedProfile->name,
            'reason' => $this->report->reason,
            'created_at' => $this->report->created_at->diffForHumans(),
        ]);
    }
}
