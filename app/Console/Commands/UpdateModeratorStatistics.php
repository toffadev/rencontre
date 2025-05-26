<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\ModeratorStatistic;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateModeratorStatistics extends Command
{
    protected $signature = 'moderator:update-stats {--date= : Date for which to update statistics (YYYY-MM-DD)}';
    protected $description = 'Update moderator statistics for messages and earnings';

    public function handle()
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::yesterday();
        $this->info("Updating moderator statistics for {$date->format('Y-m-d')}");

        // Récupérer tous les modérateurs
        $moderators = User::where('type', 'moderator')->get();

        foreach ($moderators as $moderator) {
            $this->updateModeratorStats($moderator, $date);
        }

        $this->info('Statistics update completed.');
    }

    protected function updateModeratorStats(User $moderator, Carbon $date)
    {
        // Récupérer les messages du modérateur pour la date donnée
        $messages = Message::where('moderator_id', $moderator->id)
            ->whereDate('created_at', $date)
            ->where('is_from_client', false)
            ->get();

        // Calculer les statistiques par profil
        $profileStats = [];
        foreach ($messages as $message) {
            if (!isset($profileStats[$message->profile_id])) {
                $profileStats[$message->profile_id] = [
                    'short_messages' => 0,
                    'long_messages' => 0,
                    'earnings' => 0
                ];
            }

            $isLong = strlen($message->content) >= 10;
            $earnings = $isLong ? 50 : 25;

            if ($isLong) {
                $profileStats[$message->profile_id]['long_messages']++;
            } else {
                $profileStats[$message->profile_id]['short_messages']++;
            }

            $profileStats[$message->profile_id]['earnings'] += $earnings;
        }

        // Récupérer les points reçus par profil
        $pointsReceived = DB::table('profile_point_transactions')
            ->where('moderator_id', $moderator->id)
            ->whereDate('created_at', $date)
            ->groupBy('profile_id')
            ->select('profile_id', DB::raw('SUM(points_amount) as total_points'))
            ->get()
            ->pluck('total_points', 'profile_id')
            ->toArray();

        // Mettre à jour ou créer les statistiques pour chaque profil
        foreach ($profileStats as $profileId => $stats) {
            ModeratorStatistic::updateOrCreate(
                [
                    'user_id' => $moderator->id,
                    'profile_id' => $profileId,
                    'stats_date' => $date->format('Y-m-d')
                ],
                [
                    'short_messages_count' => $stats['short_messages'],
                    'long_messages_count' => $stats['long_messages'],
                    'points_received' => $pointsReceived[$profileId] ?? 0,
                    'earnings' => $stats['earnings']
                ]
            );
        }

        $this->info("Updated statistics for moderator {$moderator->name}");
    }
}
