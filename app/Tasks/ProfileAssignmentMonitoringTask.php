<?php

namespace App\Tasks;

use App\Models\ModeratorProfileAssignment;
use App\Models\Profile;
use App\Models\User;
use App\Models\Message;
use App\Models\ModeratorQueue;
use App\Services\ModeratorAssignmentService;
use App\Services\ConflictResolutionService;
use App\Services\ProfileLockService;
use App\Services\ModeratorQueueService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Tâche de surveillance et de maintenance des attributions de profils aux modérateurs.
 * 
 * Cette tâche s'exécute périodiquement pour :
 * - Détecter et résoudre les conflits d'attribution de profils aux modérateurs.
 * - Surveiller et analyser l'état de la file d'attente des modérateurs en attente.
 * - Valider les règles métier (ex : un modérateur ne doit avoir qu'un seul profil principal).
 * - Nettoyer les verrous expirés et libérer les profils attribués à des modérateurs inactifs.
 * - Identifier les profils orphelins (sans modérateur actif) ayant des messages clients non lus,
 *   puis tenter de les réassigner automatiquement à un modérateur disponible.
 * - Nettoyer les attributions avec des données incohérentes ou invalides.
 * - Générer des alertes système en cas de surcharge, de verrous nombreux ou de conflits non résolus.
 * 
 * L'objectif est d'assurer la cohérence, la fluidité et la réactivité du système
 * d'attribution des profils dans l'application.
 */
class ProfileAssignmentMonitoringTask
{
    protected $assignmentService;
    protected $conflictService;
    protected $lockService;
    protected $queueService;
    protected $rotateTask;

    public function __construct(
        ?ModeratorAssignmentService $assignmentService = null,
        ?ConflictResolutionService $conflictService = null,
        ?ProfileLockService $lockService = null,
        ?ModeratorQueueService $queueService = null,
        ?RotateModeratorProfilesTask $rotateTask = null
    ) {
        $this->assignmentService = $assignmentService ?? new ModeratorAssignmentService();
        $this->conflictService = $conflictService ?? new ConflictResolutionService();
        $this->lockService = $lockService ?? new ProfileLockService();
        $this->queueService = $queueService ?? new ModeratorQueueService();
        $this->rotateTask = $rotateTask;
    }

    /**
     * Exécution principale de la tâche de surveillance
     */
    public function __invoke()
    {
        Log::info("Démarrage de la tâche de surveillance des attributions de profils");

        // Vérifier les conflits d'attribution
        $conflictsResolved = $this->checkForConflicts();

        // Surveiller l'état de la file d'attente
        $queueStatus = $this->monitorQueueStatus();

        // Traiter la file d'attente si des profils sont disponibles
        $assignmentsProcessed = $this->queueService->processQueue();

        // Valider le respect des règles d'attribution
        $rulesValidated = $this->validateAssignmentRules();

        // Nettoyer les états expirés
        $cleanupResult = $this->cleanupExpiredStates();

        // Détecter les profils orphelins
        $orphanProfiles = $this->detectOrphanProfiles();

        // Nettoyer les attributions incohérentes
        $inconsistentAssignments = $this->cleanInconsistentAssignments();

        // Générer des alertes système si nécessaire
        $alerts = $this->generateAlerts();

        // Cette tâche s'exécute plus fréquemment pour vérifier l'inactivité
        $this->rotateTask->checkInactivity();

        // Vérifier aussi les messages non assignés
        $this->assignmentService->processUnassignedMessages();

        Log::info("Fin de la tâche de surveillance des attributions de profils", [
            'conflicts_resolved' => $conflictsResolved,
            'queue_status' => $queueStatus,
            'assignments_processed' => $assignmentsProcessed,
            'rules_validated' => $rulesValidated,
            'cleanup_result' => $cleanupResult,
            'orphan_profiles' => $orphanProfiles,
            'inconsistent_assignments' => $inconsistentAssignments,
            'alerts_generated' => count($alerts)
        ]);

        return [
            'status' => 'completed',
            'timestamp' => now()->toIso8601String()
        ];
    }

    /**
     * Vérifier les conflits d'attribution
     */
    private function checkForConflicts()
    {
        // Utiliser le service de résolution de conflits
        return $this->conflictService->validateAssignmentIntegrity();
    }

    /**
     * Surveiller l'état de la file d'attente
     */
    private function monitorQueueStatus()
    {
        $queueStatus = $this->queueService->getQueueStatus();

        // Vérifier si des modérateurs sont en attente depuis trop longtemps
        $longWaitingModerators = ModeratorQueue::where('queued_at', '<', now()->subMinutes(15))->get();

        if ($longWaitingModerators->isNotEmpty()) {
            Log::warning("Modérateurs en attente depuis plus de 15 minutes", [
                'count' => $longWaitingModerators->count(),
                'moderator_ids' => $longWaitingModerators->pluck('moderator_id')->toArray()
            ]);

            // Vérifier si des profils peuvent être libérés
            $this->assignmentService->reassignInactiveProfiles(5); // Réduire à 5 minutes pour libérer des profils
        }

        return $queueStatus;
    }

    /**
     * Valider le respect des règles d'attribution
     */
    private function validateAssignmentRules()
    {
        $issues = 0;

        // 1. Vérifier qu'un modérateur n'a qu'un seul profil principal
        $moderatorsWithMultiplePrimary = User::whereHas('profileAssignments', function ($query) {
            $query->where('is_active', true)->where('is_primary', true);
        }, '>', 1)->get();

        foreach ($moderatorsWithMultiplePrimary as $moderator) {
            Log::warning("Modérateur avec plusieurs profils principaux", [
                'moderator_id' => $moderator->id
            ]);

            // Conserver uniquement le plus récent comme principal
            $assignments = ModeratorProfileAssignment::where('user_id', $moderator->id)
                ->where('is_active', true)
                ->where('is_primary', true)
                ->orderBy('assigned_at', 'desc')
                ->get();

            $kept = false;
            foreach ($assignments as $assignment) {
                if (!$kept) {
                    $kept = true;
                    continue;
                }

                $assignment->is_primary = false;
                $assignment->save();
                $issues++;
            }
        }

        // 2. Vérifier les clients dupliqués dans les attributions
        $clientProfilePairs = [];
        $assignments = ModeratorProfileAssignment::where('is_active', true)->get();

        foreach ($assignments as $assignment) {
            $conversationIds = $assignment->conversation_ids ?? [];

            foreach ($conversationIds as $clientId) {
                $key = $clientId . '-' . $assignment->profile_id;

                if (!isset($clientProfilePairs[$key])) {
                    $clientProfilePairs[$key] = [];
                }

                $clientProfilePairs[$key][] = $assignment->id;
            }
        }

        foreach ($clientProfilePairs as $key => $assignmentIds) {
            if (count($assignmentIds) > 1) {
                Log::warning("Client attribué à plusieurs modérateurs pour le même profil", [
                    'key' => $key,
                    'assignment_ids' => $assignmentIds
                ]);

                // Garder uniquement la première attribution
                for ($i = 1; $i < count($assignmentIds); $i++) {
                    $assignment = ModeratorProfileAssignment::find($assignmentIds[$i]);

                    if ($assignment) {
                        list($clientId, $profileId) = explode('-', $key);
                        $assignment->removeConversation($clientId);
                        $issues++;
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * Nettoyer les états expirés
     */
    private function cleanupExpiredStates()
    {
        // Nettoyer les verrous expirés
        $locksResult = $this->lockService->cleanExpiredLocks();

        // Libérer les profils des modérateurs inactifs (après 20 minutes)
        $inactiveResult = $this->assignmentService->reassignInactiveProfiles(20);

        return [
            'locks_cleaned' => $locksResult,
            'inactive_assignments_released' => $inactiveResult
        ];
    }

    /**
     * Détecter les profils orphelins
     */
    private function detectOrphanProfiles()
    {
        $orphanProfiles = Profile::whereDoesntHave('moderatorAssignments', function ($query) {
            $query->where('is_active', true);
        })->get();

        // Vérifier s'il y a des clients avec des messages non lus pour ces profils
        $orphanProfilesWithMessages = 0;

        foreach ($orphanProfiles as $profile) {
            $hasUnreadMessages = Message::where('profile_id', $profile->id)
                ->where('is_from_client', true)
                ->whereNull('read_at')
                ->exists();

            if ($hasUnreadMessages) {
                Log::warning("Profil orphelin avec des messages non lus détecté", [
                    'profile_id' => $profile->id,
                    'profile_name' => $profile->name
                ]);

                // Trouver un modérateur disponible pour attribuer ce profil
                $availableModerator = User::where('type', 'moderateur')
                    ->where('status', 'active')
                    ->whereDoesntHave('profileAssignments', function ($query) {
                        $query->where('is_active', true)->where('active_conversations_count', '>', 3);
                    })
                    ->first();

                if ($availableModerator) {
                    $this->assignmentService->assignProfileToModerator(
                        $availableModerator->id,
                        $profile->id,
                        true // Définir comme profil principal
                    );

                    $orphanProfilesWithMessages++;
                }
            }
        }

        return $orphanProfilesWithMessages;
    }

    /**
     * Nettoyer les attributions incohérentes
     */
    private function cleanInconsistentAssignments()
    {
        $fixed = 0;

        // Vérifier les assignments avec des conversation_ids non valides
        $assignments = ModeratorProfileAssignment::where('is_active', true)->get();

        foreach ($assignments as $assignment) {
            $conversationIds = $assignment->conversation_ids ?? [];
            $originalCount = count($conversationIds);

            // Filtrer les IDs non valides (non numériques ou négatifs)
            $validIds = array_filter($conversationIds, function ($id) {
                return is_numeric($id) && $id > 0;
            });

            if (count($validIds) !== $originalCount) {
                Log::warning("Attribution avec des IDs de conversation non valides", [
                    'assignment_id' => $assignment->id,
                    'invalid_count' => $originalCount - count($validIds)
                ]);

                $assignment->conversation_ids = array_values($validIds);
                $assignment->active_conversations_count = count($validIds);
                $assignment->save();

                $fixed++;
            }

            // Vérifier si le nombre de conversations est cohérent
            if ($assignment->active_conversations_count !== count($conversationIds)) {
                Log::warning("Attribution avec un compteur de conversations incohérent", [
                    'assignment_id' => $assignment->id,
                    'counted' => $assignment->active_conversations_count,
                    'actual' => count($conversationIds)
                ]);

                $assignment->active_conversations_count = count($conversationIds);
                $assignment->save();

                $fixed++;
            }
        }

        return $fixed;
    }

    /**
     * Générer des alertes système
     */
    private function generateAlerts()
    {
        $alerts = [];

        // 1. Alerte si trop de modérateurs en file d'attente
        $queueLength = ModeratorQueue::count();
        if ($queueLength > 3) {
            $alerts[] = [
                'type' => 'queue_overload',
                'severity' => 'warning',
                'message' => "File d'attente des modérateurs surchargée ({$queueLength} modérateurs)",
                'timestamp' => now()->toIso8601String()
            ];
        }

        // 2. Alerte si trop de verrous actifs
        $profileLocks = $this->lockService->getLockedProfileIds();
        if (count($profileLocks) > 5) {
            $alerts[] = [
                'type' => 'locks_overload',
                'severity' => 'warning',
                'message' => "Nombre élevé de profils verrouillés (" . count($profileLocks) . " profils)",
                'timestamp' => now()->toIso8601String()
            ];
        }

        // 3. Alerte si des conflits non résolus
        $conflicts = $this->conflictService->validateAssignmentIntegrity();
        if ($conflicts > 0) {
            $alerts[] = [
                'type' => 'unresolved_conflicts',
                'severity' => 'error',
                'message' => "Conflits d'attribution non résolus ({$conflicts} conflits)",
                'timestamp' => now()->toIso8601String()
            ];
        }

        // Logger les alertes
        foreach ($alerts as $alert) {
            Log::error("Alerte système: " . $alert['message'], $alert);
        }

        return $alerts;
    }
}
