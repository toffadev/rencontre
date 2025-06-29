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
        // Changé de 30 minutes à 1 minute pour le seuil d'inactivité
        $inactiveProfilesCount = $this->assignmentService->reassignInactiveProfiles(1);
        $this->info("Nombre de profils inactifs réattribués: $inactiveProfilesCount");
        Log::info("[ProcessMessages] Profils inactifs réattribués: $inactiveProfilesCount");

        // Traiter les messages non attribués
        $this->info('Attribution des messages aux modérateurs...');
        $clientsAssigned = $this->assignmentService->processUnassignedMessages();
        $this->info("Nombre de clients attribués à des modérateurs: $clientsAssigned");
        Log::info("[ProcessMessages] Clients attribués: $clientsAssigned");

        // Traiter spécifiquement les clients en attente de réponse
        $this->info('Traitement des clients en attente de réponse...');
        $clientsProcessed = $this->assignmentService->processClientsNeedingResponse();
        $this->info("Nombre de clients en attente traités: $clientsProcessed");
        Log::info("[ProcessMessages] Clients en attente traités: $clientsProcessed");

        $this->info('Traitement terminé avec succès.');
        Log::info('[ProcessMessages] Traitement terminé avec succès');

        return Command::SUCCESS;
    }
}
