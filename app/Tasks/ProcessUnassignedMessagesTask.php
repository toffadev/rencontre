<?php

namespace App\Tasks;

use App\Services\ModeratorAssignmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessUnassignedMessagesTask
{
    /**
     * Le service d'attribution des modérateurs.
     *
     * @var \App\Services\ModeratorAssignmentService
     */
    protected $assignmentService;

    /**
     * Créer une nouvelle instance de tâche.
     *
     * @param  \App\Services\ModeratorAssignmentService  $assignmentService
     * @return void
     */
    public function __construct(ModeratorAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    /**
     * Définir la planification de la tâche.
     */
    public function schedule(): string
    {
        return '*/2 * * * *'; // Toutes les 2 minutes
    }

    /**
     * Exécuter la tâche planifiée.
     */
    public function __invoke(): void
    {
        Log::info('Traitement des messages non assignés...');

        // Libérer d'abord les profils des modérateurs inactifs
        $releasedCount = $this->assignmentService->reassignInactiveProfiles(30); // 30 minutes d'inactivité

        if ($releasedCount > 0) {
            Log::info("{$releasedCount} profil(s) libéré(s) de modérateurs inactifs.");
        }

        // Traiter les messages non assignés
        $assignedCount = $this->assignmentService->processUnassignedMessages();

        Log::info("{$assignedCount} client(s) assigné(s) à des modérateurs.");
    }
}
