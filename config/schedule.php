<?php

use Illuminate\Support\Facades\Schedule;

return function (Schedule $schedule) {
    // Mise à jour des statistiques des modérateurs chaque jour à 00:05
    $schedule->command('moderator:update-stats')
        ->dailyAt('00:05')
        ->appendOutputTo(storage_path('logs/moderator-stats.log'));
};
