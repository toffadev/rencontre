<?php

namespace App\Services;

use App\Models\ModeratorProfileAssignment;
use App\Models\User;
use App\Events\ModeratorActivityEvent;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\ModeratorAssignmentService;

/**
 * Service pour gérer les activités des modérateurs dans l'application.
 * 
 * Ce service permet de :
 * - enregistrer l'activité de frappe (typing) des modérateurs,
 * - détecter les modérateurs inactifs afin de pouvoir réassigner leurs profils,
 * - calculer la charge de travail de chaque modérateur,
 * - surveiller les temps de réponse pour détecter d'éventuels retards,
 * - identifier si une majorité de modérateurs sont surchargés.
 * 
 * Il utilise le service `ModeratorAssignmentService` pour gérer les réassignations.
 * 
 * L'objectif global est d'assurer une gestion efficace des modérateurs pour que
 * les clients reçoivent des réponses rapides et qu'aucun profil ne reste sans modérateur actif.
 */
class ModeratorActivityService
{
    protected $assignmentService;

    public function __construct(?ModeratorAssignmentService $assignmentService = null)
    {
        $this->assignmentService = $assignmentService ?? new ModeratorAssignmentService();
    }

    /**
     * Record typing activity with inactivity monitoring
     */
    public function recordTypingActivity($userId, $profileId, $clientId)
    {
        $assignment = ModeratorProfileAssignment::where('user_id', $userId)
            ->where('profile_id', $profileId)
            ->where('is_active', true)
            ->first();

        if ($assignment) {
            // Vérifier si la dernière activité de frappe est récente (moins de 3 secondes)
            $shouldEmitEvent = true;
            if ($assignment->last_typing) {
                $timeSinceLastTyping = $assignment->last_typing->diffInSeconds(now());
                // Ne pas émettre d'événement si moins de 3 secondes se sont écoulées depuis le dernier
                if ($timeSinceLastTyping < 3) {
                    $shouldEmitEvent = false;
                }
            }

            // Mettre à jour les horodatages d'activité
            $assignment->last_typing = now();
            $assignment->last_activity = now();
            $assignment->last_activity_check = now();
            $assignment->save();

            // N'émettre l'événement que si nécessaire (debounce)
            if ($shouldEmitEvent) {
                event(new ModeratorActivityEvent($userId, $profileId, $clientId, 'typing'));

                // Vérifier l'inactivité des autres modérateurs
                $this->detectInactiveModerators();
            }
        }
    }

    /**
     * Détecter les modérateurs inactifs pour réattribution
     */
    public function detectInactiveModerators($thresholdMinutes = 1)
    {
        // Surveillance toutes les 15 secondes au lieu de 30
        $lastCheck = ModeratorProfileAssignment::where('last_activity_check', '>', now()->subSeconds(15))
            ->exists();

        if ($lastCheck) {
            return false; // Éviter les vérifications trop fréquentes
        }

        $inactiveTime = now()->subMinutes($thresholdMinutes);

        $inactiveAssignments = ModeratorProfileAssignment::where('is_active', true)
            ->where(function ($query) use ($inactiveTime) {
                $query->where('last_activity', '<', $inactiveTime)
                    ->orWhereNull('last_activity');
            })
            ->get();

        Log::info("Détection des modérateurs inactifs", [
            'threshold_minutes' => $thresholdMinutes,
            'inactive_count' => $inactiveAssignments->count()
        ]);

        foreach ($inactiveAssignments as $assignment) {
            $this->triggerReassignmentForInactivity($assignment->user_id);

            // Marquer comme vérifié
            $assignment->last_activity_check = now();
            $assignment->save();
        }

        return count($inactiveAssignments);
    }

    /**
     * Déclencher la réattribution pour inactivité
     */
    public function triggerReassignmentForInactivity($moderatorId)
    {
        // Vérifier la charge des autres modérateurs avant réattribution
        if ($this->identifyOverloadedModerators()) {
            Log::info("Réattribution annulée - modérateurs surchargés", [
                'moderator_id' => $moderatorId
            ]);
            return false;
        }

        // Récupérer les assignations actives du modérateur
        $assignments = ModeratorProfileAssignment::where('user_id', $moderatorId)
            ->where('is_active', true)
            ->get();

        foreach ($assignments as $assignment) {
            // Vérifier la réactivité du modérateur
            if ($this->monitorResponseTimes($assignment)) {
                continue; // Pas besoin de réattribution
            }

            // Déclencher la logique de réattribution pour inactivité (1 minute)
            $reassignedCount = $this->assignmentService->reassignInactiveProfiles(1);

            if ($reassignedCount > 0) {
                Log::info("Réattribution déclenchée pour inactivité", [
                    'moderator_id' => $moderatorId,
                    'profile_id' => $assignment->profile_id,
                    'reassigned' => $reassignedCount
                ]);

                // Émettre un événement pour informer le frontend de la réattribution
                // Cela permettra au frontend de mettre à jour l'interface sans actualisation
                $newAssignment = ModeratorProfileAssignment::where('profile_id', $assignment->profile_id)
                    ->where('is_active', true)
                    ->first();

                if ($newAssignment && $newAssignment->user_id != $moderatorId) {
                    // Émettre l'événement avec les détails de la nouvelle assignation
                    event(new \App\Events\ProfileAssigned(
                        User::find($newAssignment->user_id),
                        $assignment->profile_id,
                        $newAssignment->id,
                        $moderatorId,  // Ancien modérateur (ID)
                        'inactivity'   // Raison de la réattribution
                    ));
                }

                return true; // Une réattribution a été faite
            }

            break; // Ne pas continuer si aucune réattribution n'a été nécessaire
        }

        return false; // Aucune réattribution déclenchée
    }


    /**
     * Calculer la charge de travail d'un modérateur
     */
    public function calculateModeratorWorkload($moderatorId)
    {
        $assignments = ModeratorProfileAssignment::where('user_id', $moderatorId)
            ->where('is_active', true)
            ->get();

        $totalLoad = 0;
        $activeProfiles = 0;

        foreach ($assignments as $assignment) {
            $conversationCount = $assignment->active_conversations_count ?? 0;
            $totalLoad += $conversationCount;
            $activeProfiles++;
        }

        // Calculer le score de charge: 100 - (conversations actives × 20)
        $score = 100 - ($totalLoad * 20);
        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'active_profiles' => $activeProfiles,
            'conversations' => $totalLoad,
            'status' => $score > 50 ? 'disponible' : ($score > 20 ? 'occupé' : 'surchargé')
        ];
    }

    /**
     * Surveiller les temps de réponse par client
     */
    public function monitorResponseTimes($assignment)
    {
        $conversations = $assignment->conversation_ids ?? [];
        if (empty($conversations)) {
            return true; // Pas de conversations à surveiller
        }

        $profileId = $assignment->profile_id;
        $lastResponseTime = $assignment->last_message_sent;

        if (!$lastResponseTime || $lastResponseTime->diffInMinutes(now()) > 5) {
            // Pas de réponse depuis plus de 5 minutes, signaler possible inactivité
            return false;
        }

        return true;
    }

    /**
     * Identifier les modérateurs surchargés
     */
    public function identifyOverloadedModerators()
    {
        $moderators = User::where('type', 'moderateur')
            ->where('status', 'active')
            ->get();

        $overloadedCount = 0;

        foreach ($moderators as $moderator) {
            $workload = $this->calculateModeratorWorkload($moderator->id);
            if ($workload['status'] === 'surchargé') {
                $overloadedCount++;
            }
        }

        // Si plus de 50% des modérateurs sont surchargés, signaler surcharge
        return ($overloadedCount / max(1, count($moderators))) > 0.5;
    }

    /**
     * Demander un délai avant le changement de profil
     *
     * @param int $moderatorId ID du modérateur
     * @param int $profileId ID du profil
     * @param int $minutes Durée du délai en minutes
     * @return bool
     */
    public function requestDelay($moderatorId, $profileId, $minutes = 5)
    {
        try {
            // Vérifier si le modérateur a bien ce profil assigné
            $assignment = ModeratorProfileAssignment::where('user_id', $moderatorId)
                ->where('profile_id', $profileId)
                ->where('is_active', true)
                ->first();

            if (!$assignment) {
                return false;
            }

            // Mettre à jour la dernière activité pour prolonger l'assignation
            $assignment->last_activity = now();
            $assignment->last_activity_check = now();
            $assignment->save();

            // Enregistrer la demande de délai dans les logs
            Log::info("Délai demandé pour le changement de profil", [
                'moderator_id' => $moderatorId,
                'profile_id' => $profileId,
                'minutes' => $minutes
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la demande de délai", [
                'error' => $e->getMessage(),
                'moderator_id' => $moderatorId,
                'profile_id' => $profileId
            ]);
            return false;
        }
    }
}
