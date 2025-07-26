<?php

namespace App\Console\Commands;

use App\Services\ModeratorAssignmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messages:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Traite les messages non attribués et les assigne aux modérateurs disponibles';

    /**
     * The moderator assignment service.
     *
     * @var \App\Services\ModeratorAssignmentService
     */
    protected $assignmentService;

    /**
     * Create a new command instance.
     */
    public function __construct(ModeratorAssignmentService $assignmentService)
    {
        parent::__construct();
        $this->assignmentService = $assignmentService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Début du traitement des messages non attribués...');
        Log::info('[ProcessMessages] Début du traitement des messages non attribués');

        // Réattribuer les profils des modérateurs inactifs
        $this->info('Réattribution des profils inactifs...');
        $inactiveProfilesCount = $this->assignmentService->reassignInactiveProfiles(1);
        $this->info("Nombre de profils inactifs réattribués: $inactiveProfilesCount");
        Log::info("[ProcessMessages] Profils inactifs réattribués: $inactiveProfilesCount");

        // Récupérer le nombre de clients avec messages en attente avant traitement
        $clientsNeedingResponse = $this->assignmentService->getClientsNeedingResponse();
        $this->info("Nombre de clients avec messages sans réponse: " . $clientsNeedingResponse->count());

        // Traiter les messages non assignés
        $this->info('Attribution des messages aux modérateurs...');
        $assignmentStats = $this->assignmentService->processUnassignedMessages();

        // Afficher les statistiques détaillées d'assignation
        $this->info("Nombre de clients nouvellement assignés: " . $assignmentStats['new']);
        $this->info("Nombre de clients réassignés: " . $assignmentStats['reassigned']);
        $this->info("Nombre de clients avec assignation confirmée: " . $assignmentStats['confirmed']);
        $this->info("Nombre total de clients assignés: " . $assignmentStats['total']);

        // Traiter spécifiquement les clients en attente de réponse
        $this->info('Traitement des clients en attente de réponse...');

        // Récupérer les statistiques avant traitement
        $clientsByActivity = $clientsNeedingResponse->groupBy('activity_level');

        $activityStats = [
            'actifs' => ($clientsByActivity[1] ?? collect())->count(),
            'semi_actifs' => ($clientsByActivity[2] ?? collect())->count(),
            'inactifs' => ($clientsByActivity[3] ?? collect())->count(),
        ];

        $this->info("Répartition des clients sans réponse: " .
            "{$activityStats['actifs']} actifs, " .
            "{$activityStats['semi_actifs']} semi-actifs, " .
            "{$activityStats['inactifs']} inactifs");

        // Traiter les clients selon leur priorité
        $clientsProcessed = $this->assignmentService->processClientsNeedingResponse();

        $this->info("Nombre de clients dont les messages ont été traités: $clientsProcessed");

        Log::info("[ProcessMessages] Traitement terminé", [
            'clients_sans_reponse' => $clientsNeedingResponse->count(),
            'clients_assignes' => $assignmentStats['total'],
            'clients_traites' => $clientsProcessed,
            'repartition' => $activityStats
        ]);

        $this->info('Traitement terminé avec succès.');

        return Command::SUCCESS;
    }
}
