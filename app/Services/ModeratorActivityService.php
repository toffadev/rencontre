<?php

namespace App\Services;

use App\Models\ModeratorProfileAssignment;
use App\Models\User;
use App\Models\Message;
use App\Events\ModeratorActivityEvent;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\ModeratorAssignmentService;
use App\Services\TimeoutManagementService;
use App\Events\ModeratorInactivityDetected;

/**
 * Service pour gérer les activités des modérateurs dans l'application.
 * 
 * Nouvelle version réactive basée sur des événements plutôt que des vérifications périodiques.
 * 
 * Ce service permet de :
 * - Enregistrer les activités des modérateurs (frappe, messages envoyés, etc.)
 * - Réinitialiser les timers d'inactivité via le TimeoutManagementService
 * - Coordonner les événements d'activité avec le frontend
 */
class ModeratorActivityService
{
    protected $assignmentService;
    protected $timeoutService;

    public function __construct(
        ModeratorAssignmentService $assignmentService,
        TimeoutManagementService $timeoutService
    ) {
        $this->assignmentService = $assignmentService;
        $this->timeoutService = $timeoutService;
    }

    /**
     * Enregistre une activité de frappe et réinitialise le timer d'inactivité
     */
    public function recordTypingActivity($userId, $profileId, $clientId)
    {
        $assignment = ModeratorProfileAssignment::where('user_id', $userId)
            ->where('profile_id', $profileId)
            ->where('is_active', true)
            ->first();

        if ($assignment) {
            // Debounce pour éviter des événements trop fréquents
            $shouldEmitEvent = !$assignment->last_typing ||
                $assignment->last_typing->diffInSeconds(now()) >= 3;

            // Mettre à jour les horodatages
            $assignment->last_typing = now();
            $assignment->last_activity = now();
            $assignment->last_activity_check = now();
            $assignment->save();

            // Réinitialiser le timer d'inactivité
            $this->timeoutService->resetTimer($userId, $profileId, $clientId);

            // Émettre l'événement si nécessaire
            if ($shouldEmitEvent) {
                event(new ModeratorActivityEvent($userId, $profileId, $clientId, 'typing'));
            }
        }
    }

    /**
     * Méthode générique pour enregistrer tout type d'activité
     */
    public function recordActivity($userId, $profileId, $clientId, $activityType)
    {
        $assignment = ModeratorProfileAssignment::where('user_id', $userId)
            ->where('profile_id', $profileId)
            ->where('is_active', true)
            ->first();

        if ($assignment) {
            // Mettre à jour les horodatages selon le type d'activité
            switch ($activityType) {
                case 'message_sent':
                    $assignment->last_message_sent = now();
                    break;
                case 'typing':
                    $assignment->last_typing = now();
                    break;
                case 'view':
                    $assignment->last_view = now();
                    break;
            }

            // Mettre à jour l'activité globale
            $assignment->last_activity = now();
            $assignment->last_activity_check = now();
            $assignment->save();

            // Réinitialiser le timer d'inactivité
            $this->timeoutService->resetTimer($userId, $profileId, $clientId);

            // Émettre l'événement
            event(new ModeratorActivityEvent($userId, $profileId, $clientId, $activityType));

            return true;
        }

        return false;
    }

    /**
     * Réinitialise explicitement un timer d'inactivité
     */
    public function resetInactivityTimer($userId, $profileId, $clientId = null)
    {
        $exists = ModeratorProfileAssignment::where('user_id', $userId)
            ->where('profile_id', $profileId)
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            return $this->timeoutService->resetTimer($userId, $profileId, $clientId);
        }

        return false;
    }

    /**
     * Demander un délai avant le changement de profil
     */
    public function requestDelay($moderatorId, $profileId, $minutes = 5)
    {
        try {
            $assignment = ModeratorProfileAssignment::where('user_id', $moderatorId)
                ->where('profile_id', $profileId)
                ->where('is_active', true)
                ->first();

            if (!$assignment) {
                return false;
            }

            // Mettre à jour la dernière activité
            $assignment->last_activity = now();
            $assignment->last_activity_check = now();
            $assignment->save();

            // Prolonger le timer d'inactivité
            $this->timeoutService->extendTimeout($moderatorId, $profileId, $minutes);

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

        $score = max(0, min(100, 100 - ($totalLoad * 20)));

        return [
            'score' => $score,
            'active_profiles' => $activeProfiles,
            'conversations' => $totalLoad,
            'status' => $score > 50 ? 'disponible' : ($score > 20 ? 'occupé' : 'surchargé')
        ];
    }

    /**
     * Identifier les modérateurs surchargés
     */
    public function identifyOverloadedModerators()
    {
        $moderators = User::where('type', 'moderateur')
            ->where('status', 'active')
            ->where('is_online', true)
            ->get();

        if ($moderators->isEmpty()) {
            return false;
        }

        $overloadedCount = 0;
        $totalWorkload = 0;

        foreach ($moderators as $moderator) {
            $workload = $this->calculateModeratorWorkload($moderator->id);
            $totalWorkload += $workload['conversations'];

            if ($workload['status'] === 'surchargé') {
                $overloadedCount++;
            }
        }

        $averageLoad = $totalWorkload / $moderators->count();
        $highAverageLoad = $averageLoad > 3;

        return ($overloadedCount / $moderators->count()) > 0.7 && $highAverageLoad;
    }

    public function checkInactivity()
    {
        Log::info("Vérification de l'inactivité des modérateurs");

        $thresholdMinutes = config('moderator.inactivity_threshold', 1);
        $threshold = now()->subMinutes($thresholdMinutes);

        // Modification ici : utilisation de 'activeAssignments' au lieu de 'assignments'
        $moderators = User::with('activeAssignments') // Utilise la relation existante
            ->where('type', 'moderateur')
            ->where('status', 'active')
            ->where('is_online', true)
            ->get();

        Log::info("Modérateurs actifs trouvés", ['count' => $moderators->count()]);

        foreach ($moderators as $moderator) {
            // Modification ici : utilisation directe de la relation pré-chargée
            $activeAssignments = $moderator->activeAssignments;

            if ($activeAssignments->isEmpty()) {
                continue;
            }

            $lastActivity = $this->getLastActivity($moderator->id);

            Log::info("Dernière activité du modérateur", [
                'moderator_id' => $moderator->id,
                'moderator_name' => $moderator->name,
                'last_activity' => $lastActivity ? $lastActivity->format('Y-m-d H:i:s') : 'Jamais',
                'threshold' => $threshold->format('Y-m-d H:i:s')
            ]);

            if ($lastActivity && $lastActivity < $threshold) {
                $inactivityDuration = $lastActivity->diffInMinutes(now());

                Log::info("Modérateur inactif détecté", [
                    'moderator_id' => $moderator->id,
                    'moderator_name' => $moderator->name,
                    'inactivity_duration' => $inactivityDuration,
                    'threshold_minutes' => $thresholdMinutes
                ]);

                try {
                    foreach ($activeAssignments as $assignment) {
                        event(new ModeratorInactivityDetected(
                            $moderator->id,
                            $assignment->profile_id,
                            $assignment->client_id,
                            $assignment->id,
                            'inactivity'
                        ));
                    }

                    Log::info("Événement ModeratorInactivityDetected émis avec succès", [
                        'moderator_id' => $moderator->id,
                        'assignments_count' => $activeAssignments->count()
                    ]);
                } catch (\Exception $e) {
                    Log::error("Erreur lors de l'émission de ModeratorInactivityDetected", [
                        'error' => $e->getMessage(),
                        'moderator_id' => $moderator->id
                    ]);
                }
            }
        }
        Log::info("Vérification de l'inactivité des modérateurs - Fin");
    }

    private function getLastActivity($moderatorId)
    {
        // Vérifier la dernière activité (message envoyé ou saisie)
        $lastMessage = Message::where('moderator_id', $moderatorId)
            ->where('is_from_client', false)
            ->orderBy('created_at', 'desc')
            ->first();

        // Récupérer la dernière saisie depuis les assignations
        $lastTypingAssignment = ModeratorProfileAssignment::where('user_id', $moderatorId)
            ->whereNotNull('last_typing')
            ->orderBy('last_typing', 'desc')
            ->first();

        $lastTyping = $lastTypingAssignment ? $lastTypingAssignment->last_typing : null;

        // Déterminer la dernière activité entre message et saisie
        $lastMessageTime = $lastMessage ? $lastMessage->created_at : null;
        $lastTypingTime = $lastTyping;

        if ($lastMessageTime && $lastTypingTime) {
            return $lastMessageTime->gt($lastTypingTime) ? $lastMessageTime : $lastTypingTime;
        } else if ($lastMessageTime) {
            return $lastMessageTime;
        } else if ($lastTypingTime) {
            return $lastTypingTime;
        }

        return null; // Aucune activité trouvée
    }
}
