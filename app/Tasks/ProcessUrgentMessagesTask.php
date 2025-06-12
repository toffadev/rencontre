<?php

namespace App\Tasks;

use App\Services\ModeratorAssignmentService;
use Illuminate\Support\Facades\Log;

class ProcessUrgentMessagesTask
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
        return '*/30 * * * * *'; // Toutes les 30 secondes
    }

    /**
     * Exécuter la tâche planifiée.
     */
    public function __invoke(): void
    {
        Log::info('Traitement des messages urgents (sans réponse depuis 2+ minutes)...');

        // Traiter seulement les messages urgents (non répondus depuis 2 minutes ou plus)
        $assignedCount = $this->assignmentService->processUnassignedMessages(true);

        if ($assignedCount > 0) {
            Log::info("{$assignedCount} client(s) urgent(s) réattribué(s) à des modérateurs.");
        }
    }
}
