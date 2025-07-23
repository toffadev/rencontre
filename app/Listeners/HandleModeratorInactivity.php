<?php

namespace App\Listeners;

use App\Events\ModeratorInactivityDetected;
use App\Services\ModeratorAssignmentService;
use App\Services\TimeoutManagementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\ModeratorProfileAssignment;
use App\Models\User;
use App\Models\Profile;
use App\Events\ProfileAssigned;
use Illuminate\Support\Facades\Schema;

class HandleModeratorInactivity implements ShouldQueue
{
    use InteractsWithQueue;

    protected $assignmentService;
    protected $timeoutService;

    // Configuration de la queue
    public $queue = 'high'; // Priorité haute pour les réassignations
    public $timeout = 30; // Timeout de 30 secondes
    public $tries = 3; // 3 tentatives max

    /**
     * Create the event listener.
     */
    public function __construct(
        ModeratorAssignmentService $assignmentService,
        TimeoutManagementService $timeoutService
    ) {
        $this->assignmentService = $assignmentService;
        $this->timeoutService = $timeoutService;

        // LOG DE DIAGNOSTIC : Vérifier que le listener est instancié
        Log::debug('🏗️ LISTENER INSTANCIÉ - HandleModeratorInactivity', [
            'listener_class' => self::class,
            'queue' => $this->queue,
            'timeout' => $this->timeout,
            'tries' => $this->tries,
            'cache_driver' => config('cache.default'),
            'broadcast_driver' => config('broadcasting.default'),
            'queue_driver' => config('queue.default'),
            'timestamp' => now()
        ]);
    }

    /**
     * Handle the event.
     */
    public function handle(ModeratorInactivityDetected $event): void
    {
        // LOG PRINCIPAL : Confirmer que le listener est exécuté
        Log::info('🚨 LISTENER EXÉCUTÉ - Traitement inactivité modérateur', [
            'listener_method' => 'handle',
            'moderator_id' => $event->moderatorId,
            'profile_id' => $event->profileId,
            'client_id' => $event->clientId,
            'assignment_id' => $event->assignmentId,
            'reason' => $event->reason,
            'timestamp' => $event->timestamp,
            'queue_job_id' => $this->job ? $this->job->getJobId() : 'sync_mode',
            'queue_name' => $this->job ? $this->job->getQueue() : 'sync_mode',
            'attempts' => $this->attempts(),
        ]);

        try {
            DB::transaction(function () use ($event) {
                Log::info('🔄 DÉBUT TRANSACTION - Traitement inactivité', [
                    'moderator_id' => $event->moderatorId,
                    'profile_id' => $event->profileId
                ]);

                // 1. Nettoyer le timer expiré
                Log::info('⏰ Nettoyage timer en cours...', [
                    'moderator_id' => $event->moderatorId,
                    'profile_id' => $event->profileId
                ]);

                $this->timeoutService->cancelTimer($event->moderatorId, $event->profileId);

                Log::info('✅ Timer nettoyé avec succès');

                // 2. Réassigner le profil
                Log::info('🔄 Début réassignation profil...', [
                    'profile_id' => $event->profileId,
                    'inactive_moderator' => $event->moderatorId
                ]);

                $result = $this->reassignProfile($event->profileId, $event->moderatorId, $event->assignmentId);

                if ($result) {
                    Log::info('✅ TRANSACTION RÉUSSIE - Profil réassigné');
                } else {
                    Log::warning('⚠️ TRANSACTION ÉCHOUÉE - Réassignation impossible');
                }
            });
        } catch (\Exception $e) {
            Log::error('❌ ERREUR CRITIQUE - Échec transaction inactivité', [
                'moderator_id' => $event->moderatorId,
                'profile_id' => $event->profileId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'attempts' => $this->attempts()
            ]);

            // Re-lancer l'exception pour déclencher les tentatives de retry
            throw $e;
        }
    }

    /**
     * Réassigne un profil à un autre modérateur
     */
    protected function reassignProfile($profileId, $inactiveModerator, $assignmentId = null)
    {
        /* Log::info('🔄 DÉBUT RÉASSIGNATION - reassignProfile appelée', [
            'profile_id' => $profileId,
            'inactive_moderator' => $inactiveModerator,
            'assignment_id' => $assignmentId,
            'method' => 'reassignProfile'
        ]); */

        Log::debug('Données de réassignation', [
            'profile_id' => $profileId,
            'moderator_id' => $inactiveModerator,
            'assignment_id' => $assignmentId,
            'all_columns' => Schema::getColumnListing('moderator_profile_assignments'),
            'sample_data' => ModeratorProfileAssignment::first()?->toArray()
        ]);

        try {
            // 1. Vérifier que l'assignation existe toujours
            Log::debug('🔍 Recherche assignation actuelle...', [
                'moderator_id' => $inactiveModerator,
                'profile_id' => $profileId
            ]);

            $currentAssignment = ModeratorProfileAssignment::where('user_id', $inactiveModerator)
                ->where('profile_id', $profileId)
                ->where('is_active', true)
                ->first();

            if (!$currentAssignment) {
                Log::warning('⚠️ ASSIGNATION INTROUVABLE - Déjà traitée ou inexistante', [
                    'moderator_id' => $inactiveModerator,
                    'profile_id' => $profileId,
                    'assignment_id' => $assignmentId,
                    'total_assignments' => ModeratorProfileAssignment::where('user_id', $inactiveModerator)
                        ->where('profile_id', $profileId)
                        ->count(),
                    'active_assignments' => ModeratorProfileAssignment::where('user_id', $inactiveModerator)
                        ->where('profile_id', $profileId)
                        ->where('is_active', true)
                        ->count()
                ]);
                return false;
            }

            Log::info('✅ ASSIGNATION TROUVÉE', [
                'assignment_id' => $currentAssignment->id,
                'assigned_at' => $currentAssignment->assigned_at,
                'last_activity' => $currentAssignment->last_activity
            ]);

            // 2. Désactiver l'assignation actuelle (SANS ended_at qui n'existe pas)
            Log::info('🚫 Désactivation assignation actuelle...', [
                'assignment_id' => $currentAssignment->id
            ]);

            $currentAssignment->is_active = false;
            $currentAssignment->is_currently_active = false;
            // RETIRÉ: $currentAssignment->ended_at = now(); // Cette colonne n'existe pas
            $currentAssignment->save();

            Log::info('✅ ASSIGNATION DÉSACTIVÉE', [
                'assignment_id' => $currentAssignment->id
            ]);

            // 3. Trouver un nouveau modérateur disponible (excluant le modérateur inactif)
            Log::info('🔍 Recherche nouveau modérateur...', [
                'excluding_moderator' => $inactiveModerator
            ]);

            $newModerator = User::where('type', 'moderateur')
                ->where('status', 'active')
                ->where('is_online', true)
                ->where('id', '!=', $inactiveModerator)
                ->withCount(['moderatorProfileAssignments as active_assignments_count' => function ($query) {
                    $query->where('is_active', true);
                }])
                ->orderBy('active_assignments_count', 'asc')
                ->first();

            if (!$newModerator) {
                Log::critical('🔥 CRITIQUE - Aucun modérateur disponible pour réassignation', [
                    'profile_id' => $profileId,
                    'inactive_moderator' => $inactiveModerator,
                    'total_moderators' => User::where('type', 'moderateur')->count(),
                    'active_moderators' => User::where('type', 'moderateur')
                        ->where('status', 'active')->count(),
                    'online_moderators' => User::where('type', 'moderateur')
                        ->where('status', 'active')
                        ->where('is_online', true)->count(),
                    'available_moderators' => User::where('type', 'moderateur')
                        ->where('status', 'active')
                        ->where('is_online', true)
                        ->where('id', '!=', $inactiveModerator)->count(),
                    'timestamp' => now()
                ]);
                return false;
            }

            Log::info('✅ NOUVEAU MODÉRATEUR TROUVÉ', [
                'new_moderator_id' => $newModerator->id,
                'new_moderator_name' => $newModerator->name,
                'new_moderator_email' => $newModerator->email,
                'current_assignments' => $newModerator->active_assignments_count
            ]);

            // 4. Créer une nouvelle assignation
            Log::info('📝 Création nouvelle assignation...', [
                'new_moderator_id' => $newModerator->id,
                'profile_id' => $profileId
            ]);

            $newAssignment = new ModeratorProfileAssignment();
            $newAssignment->user_id = $newModerator->id;
            $newAssignment->profile_id = $profileId;
            $newAssignment->is_active = true;
            $newAssignment->is_currently_active = true;
            $newAssignment->assigned_at = now();
            $newAssignment->last_activity = now();
            $newAssignment->save();

            Log::info('✅ NOUVELLE ASSIGNATION CRÉÉE', [
                'new_assignment_id' => $newAssignment->id,
                'created_at' => $newAssignment->created_at
            ]);

            // 5. Vérifier que le profil existe
            $profile = Profile::find($profileId);
            if (!$profile) {
                Log::error('❌ PROFIL INTROUVABLE pour événement', [
                    'profile_id' => $profileId
                ]);
                throw new \Exception("Profil {$profileId} introuvable");
            }

            // 6. Déclencher l'événement ProfileAssigned
            Log::info('📢 Déclenchement événement ProfileAssigned...', [
                'new_moderator_id' => $newModerator->id,
                'profile_id' => $profileId,
                'new_assignment_id' => $newAssignment->id
            ]);

            event(new ProfileAssigned(
                $newModerator,
                $profileId,  // Passer l'ID et non l'objet
                $newAssignment->id,  // Passer l'ID et non l'objet
                $inactiveModerator,  // L'ancien modérateur
                'inactivity'  // La raison
            ));

            Log::info('✅ ÉVÉNEMENT DÉCLENCHÉ - ProfileAssigned');

            Log::info('🎉 RÉASSIGNATION TERMINÉE AVEC SUCCÈS', [
                'profile_id' => $profileId,
                'old_moderator' => $inactiveModerator,
                'new_moderator' => $newModerator->id,
                'old_assignment_id' => $currentAssignment->id,
                'new_assignment_id' => $newAssignment->id
            ]);

            // 7. Démarrer un nouveau timer pour la nouvelle assignation
            Log::info('⏰ Démarrage nouveau timer inactivité...', [
                'new_moderator_id' => $newModerator->id,
                'profile_id' => $profileId
            ]);

            $this->timeoutService->startInactivityTimer($newModerator->id, $profileId);

            Log::info('✅ NOUVEAU TIMER DÉMARRÉ');

            return $newAssignment;
        } catch (\Exception $e) {
            Log::error('❌ EXCEPTION LORS DE LA RÉASSIGNATION', [
                'profile_id' => $profileId,
                'moderator_id' => $inactiveModerator,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Marque temporairement un modérateur comme indisponible
     */
    public function markModeratorTemporarilyUnavailable($moderatorId, $minutes = 5)
    {
        Log::info('🔒 Marquage modérateur temporairement indisponible...', [
            'moderator_id' => $moderatorId,
            'duration_minutes' => $minutes
        ]);

        try {
            $cacheKey = "moderator_temp_unavailable_{$moderatorId}";

            // Utilisation correcte avec le driver database
            cache()->put($cacheKey, true, $minutes * 60); // Convertir en secondes

            Log::info('✅ MODÉRATEUR MARQUÉ INDISPONIBLE', [
                'moderator_id' => $moderatorId,
                'duration_minutes' => $minutes,
                'cache_key' => $cacheKey,
                'cache_driver' => config('cache.default'),
                'expires_at' => now()->addMinutes($minutes)
            ]);
        } catch (\Exception $e) {
            Log::warning('⚠️ Impossible de marquer le modérateur comme temporairement indisponible', [
                'moderator_id' => $moderatorId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Gestion des échecs de traitement
     */
    public function failed(ModeratorInactivityDetected $event, \Throwable $exception)
    {
        Log::critical('💥 ÉCHEC DÉFINITIF - Traitement d\'inactivité', [
            'listener_class' => self::class,
            'moderator_id' => $event->moderatorId,
            'profile_id' => $event->profileId,
            'client_id' => $event->clientId,
            'assignment_id' => $event->assignmentId,
            'reason' => $event->reason,
            'total_tries' => $this->tries,
            'current_attempt' => $this->attempts(),
            'error_message' => $exception->getMessage(),
            'error_file' => $exception->getFile(),
            'error_line' => $exception->getLine(),
            'stack_trace' => $exception->getTraceAsString(),
            'timestamp' => now()
        ]);

        // Ici on pourrait implémenter une logique de récupération d'urgence :
        // - Notifier les administrateurs
        // - Forcer une réassignation manuelle
        // - Mettre le système en mode dégradé
    }

    /**
     * Méthode pour vérifier le nombre de tentatives
     */
    public function attempts()
    {
        return $this->job ? $this->job->attempts() : 1;
    }
}
