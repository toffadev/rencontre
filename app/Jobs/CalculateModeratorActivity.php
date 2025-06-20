<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CalculateModeratorActivity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $now = Carbon::now();
        $timeout = $now->copy()->subMinutes(5);

        $moderators = User::where('type', 'moderateur')
            ->where('is_online', true)
            ->get();

        foreach ($moderators as $moderator) {
            if (!$moderator->last_online_at || $moderator->last_online_at->lt($timeout)) {
                $moderator->is_online = false;
                $moderator->save();
                Log::info("Modérateur #{$moderator->id} ({$moderator->name}) marqué comme hors ligne (inactivité)");
            }
        }
    }
}
