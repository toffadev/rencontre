<?php

use App\Services\ModeratorAssignmentService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('messages:process', function (ModeratorAssignmentService $assignmentService) {
    $this->info('Traitement des messages non assignés...');

    // Libérer d'abord les profils des modérateurs inactifs
    $releasedCount = $assignmentService->reassignInactiveProfiles(30);

    if ($releasedCount > 0) {
        $this->info("{$releasedCount} profil(s) libéré(s) de modérateurs inactifs.");
    }

    // Traiter les messages non assignés
    $assignedCount = $assignmentService->processUnassignedMessages();

    $this->info("{$assignedCount} client(s) assigné(s) à des modérateurs.");
})->purpose('Traiter manuellement les messages non assignés');
