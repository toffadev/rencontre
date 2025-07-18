<?php

namespace App\Services;

use App\Models\ModeratorProfileAssignment;
use App\Models\User;
use App\Models\Message;
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
    private static $lastGlobalCheck = null;

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

            // Mettre à jour les horodatages
            $assignment->last_typing = now();
            $assignment->last_activity = now(); // ✅ CORRECTION : Mise à jour de l'activité globale
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
     * Détecter les modérateurs inactifs :
     * - Aucun message envoyé depuis plus de 1 minute
     * - Et pas en train d'écrire
     */
    /* public function detectInactiveModerators($thresholdMinutes = 1)
    {
        // Debounce global pour éviter les vérifications trop fréquentes
        if (self::$lastGlobalCheck && self::$lastGlobalCheck->diffInSeconds(now()) < 10) {
            return false;
        }

        self::$lastGlobalCheck = now();

        $inactiveTime = now()->subMinutes($thresholdMinutes);

        // Détecter les modérateurs réellement inactifs
        $inactiveAssignments = ModeratorProfileAssignment::where('is_active', true)
            ->where(function ($query) use ($inactiveTime) {
                // CORRECTION: Gérer le cas où last_message_sent est NULL (nouveau modérateur)
                $query->where('last_message_sent', '<', $inactiveTime)
                    ->orWhereNull('last_message_sent'); // ✅ Ajout pour nouveaux modérateurs
            })->where(function ($query) use ($inactiveTime) {
                $query->where(function ($q) use ($inactiveTime) {
                    // Cas 1: A déjà envoyé des messages ET dernier message > 1 min
                    $q->where('last_message_sent', '<', $inactiveTime);
                })->orWhere(function ($q) use ($inactiveTime) {
                    // Cas 2: Jamais envoyé de message ET assigné depuis > 1 min
                    $q->whereNull('last_message_sent')
                        ->where('assigned_at', '<', $inactiveTime);
                });
            })
            ->where(function ($q) use ($inactiveTime) {
                // Logique originale conservée : pas de frappe en cours
                $q->whereNull('last_typing')
                    ->orWhere('last_typing', '<', $inactiveTime);
            })
            // Vérifier aussi le statut en ligne du modérateur
            ->whereHas('user', function ($query) {
                $query->where('is_online', true)
                    ->where('status', 'active');
            })
            ->get();

        Log::info("Recherche d'assignations inactives", [
            'threshold_minutes' => $thresholdMinutes,
            'inactive_count' => $inactiveAssignments->count(),
            'timestamp' => now()->toDateTimeString()
        ]);

        $processedCount = 0;

        foreach ($inactiveAssignments as $assignment) {
            // Vérifier s'il y a des messages en attente avant de décider
            $hasUnreadMessages = $this->hasUnreadMessages($assignment->profile_id);

            if ($hasUnreadMessages || $this->shouldForceRotation()) {
                // 🔁 Réattribution pour cause d'inactivité
                $this->triggerReassignmentForInactivity($assignment->user_id);
                $processedCount++;
            }

            // Marquer le contrôle comme fait
            $assignment->last_activity_check = now();
            $assignment->save();
        }

        return $processedCount;
    } */

    public function detectInactiveModerators($thresholdMinutes = 1)
    {
        // Debounce global pour éviter les vérifications trop fréquentes
        if (self::$lastGlobalCheck && self::$lastGlobalCheck->diffInSeconds(now()) < 10) {
            return 0;
        }

        self::$lastGlobalCheck = now();

        $inactiveTime = now()->subMinutes($thresholdMinutes);

        // Règle métier exacte : Un modérateur est inactif SI ET SEULEMENT SI :
        // 1. Aucun message envoyé depuis 1 minute (ou jamais envoyé depuis plus d'1 minute)
        // 2. ET aucune saisie en cours (pas de frappe récente)
        $inactiveAssignments = ModeratorProfileAssignment::where('is_active', true)
            ->where(function ($query) use ($inactiveTime) {
                // CONDITION 1: Inactivité des messages
                $query->where(function ($q) use ($inactiveTime) {
                    // Cas A: A déjà envoyé des messages ET dernier message > 1 min
                    $q->where('last_message_sent', '<', $inactiveTime)
                        // Cas B: Jamais envoyé de message ET assigné depuis > 1 min
                        ->orWhere(function ($subQ) use ($inactiveTime) {
                            $subQ->whereNull('last_message_sent')
                                ->where('assigned_at', '<', $inactiveTime);
                        });
                })
                    // CONDITION 2: ET aucune frappe en cours
                    ->where(function ($q) use ($inactiveTime) {
                        $q->whereNull('last_typing')
                            ->orWhere('last_typing', '<', $inactiveTime);
                    });
            })
            // Vérifier aussi le statut en ligne du modérateur
            ->whereHas('user', function ($query) {
                $query->where('is_online', true)
                    ->where('status', 'active');
            })
            ->get();

        Log::info("🔍 Recherche d'assignations inactives", [
            'threshold_minutes' => $thresholdMinutes,
            'inactive_count' => $inactiveAssignments->count(),
            'timestamp' => now()->toDateTimeString(),
            'conditions' => [
                'message_threshold' => $inactiveTime->toDateTimeString(),
                'typing_threshold' => $inactiveTime->toDateTimeString()
            ]
        ]);

        $processedCount = 0;
        $processedModerators = []; // Tracker les modérateurs déjà traités

        foreach ($inactiveAssignments as $assignment) {
            // Éviter de traiter le même modérateur plusieurs fois
            if (in_array($assignment->user_id, $processedModerators)) {
                continue;
            }

            // Debug: Vérifier pourquoi ce modérateur est considéré inactif
            Log::debug("Modérateur potentiellement inactif", [
                'user_id' => $assignment->user_id,
                'profile_id' => $assignment->profile_id,
                'last_message_sent' => $assignment->last_message_sent,
                'last_typing' => $assignment->last_typing,
                'assigned_at' => $assignment->assigned_at,
                'is_typing_active' => $assignment->last_typing && $assignment->last_typing->gt($inactiveTime),
                'is_message_active' => $assignment->last_message_sent && $assignment->last_message_sent->gt($inactiveTime)
            ]);

            // Vérifier s'il y a des messages en attente avant de décider
            $hasUnreadMessages = $this->hasUnreadMessages($assignment->profile_id);

            if ($hasUnreadMessages || $this->shouldForceRotation()) {
                // Réattribution pour cause d'inactivité
                $reassigned = $this->triggerReassignmentForInactivity($assignment->user_id);
                if ($reassigned) {
                    $processedModerators[] = $assignment->user_id;
                    $processedCount++;
                }
            }

            // Marquer le contrôle comme fait
            $assignment->last_activity_check = now();
            $assignment->save();
        }

        Log::info("✅ Détection d'inactivité terminée", [
            'processed_count' => $processedCount,
            'processed_moderators' => $processedModerators
        ]);

        return $processedCount;
    }


    /**
     * ✅ NOUVELLE MÉTHODE : Vérifier s'il y a des messages non lus pour un profil
     */
    private function hasUnreadMessages($profileId)
    {
        return Message::where('profile_id', $profileId)
            ->where('is_from_client', true)
            ->whereNull('read_at')
            ->exists();
    }

    /**
     * ✅ NOUVELLE MÉTHODE : Détermine si la rotation doit être forcée
     */
    private function shouldForceRotation()
    {
        $onlineModerators = User::where('type', 'moderateur')
            ->where('is_online', true)
            ->where('status', 'active')
            ->count();

        $profilesWithMessages = Message::where('is_from_client', true)
            ->whereNull('read_at')
            ->distinct('profile_id')
            ->count();

        // Forcer la rotation si plus de profils en attente que de modérateurs
        return $profilesWithMessages > $onlineModerators;
    }

    /**
     * ✅ CORRECTION : Déclencher la réattribution pour inactivité
     */
    /* public function triggerReassignmentForInactivity($moderatorId)
    {
        // ✅ CORRECTION : Ne plus vérifier la surcharge globale, prioriser les messages en attente
        $profilesWithMessages = Message::where('is_from_client', true)
            ->whereNull('read_at')
            ->distinct('profile_id')
            ->count();

        $availableModerators = User::where('type', 'moderateur')
            ->where('is_online', true)
            ->where('status', 'active')
            ->where('id', '!=', $moderatorId)
            ->count();

        // Si pas assez de modérateurs disponibles et pas de messages urgents, reporter
        if ($availableModerators == 0 && $profilesWithMessages == 0) {
            Log::info("Réattribution reportée - aucun modérateur disponible et pas de messages urgents", [
                'moderator_id' => $moderatorId
            ]);
            return false;
        }

        // Récupérer les assignations actives du modérateur
        $assignments = ModeratorProfileAssignment::where('user_id', $moderatorId)
            ->where('is_active', true)
            ->get();

        $reassignedAny = false;

        foreach ($assignments as $assignment) {
            // ✅ CORRECTION : Vérifier si ce profil a des messages en attente
            $hasUnreadMessages = $this->hasUnreadMessages($assignment->profile_id);

            if (!$hasUnreadMessages && !$this->shouldForceRotation()) {
                Log::info("Réattribution ignorée - pas de messages en attente", [
                    'moderator_id' => $moderatorId,
                    'profile_id' => $assignment->profile_id
                ]);
                continue;
            }

            // Déclencher la logique de réattribution pour inactivité (1 minute)
            $reassignedCount = $this->assignmentService->reassignInactiveProfiles(1);

            if ($reassignedCount > 0) {
                $reassignedAny = true;
                Log::info("Réattribution déclenchée pour inactivité", [
                    'moderator_id' => $moderatorId,
                    'profile_id' => $assignment->profile_id,
                    'reassigned' => $reassignedCount
                ]);

                // Émettre un événement pour informer le frontend de la réattribution
                $newAssignment = ModeratorProfileAssignment::where('profile_id', $assignment->profile_id)
                    ->where('is_active', true)
                    ->first();

                if ($newAssignment && $newAssignment->user_id != $moderatorId) {
                    event(new \App\Events\ProfileAssigned(
                        User::find($newAssignment->user_id),
                        $assignment->profile_id,
                        $newAssignment->id,
                        $moderatorId,
                        'inactivity'
                    ));
                }
            }
        }

        return $reassignedAny;
    } */

    public function triggerReassignmentForInactivity($moderatorId)
    {
        // Vérifier la disponibilité des ressources
        $profilesWithMessages = Message::where('is_from_client', true)
            ->whereNull('read_at')
            ->distinct('profile_id')
            ->count();

        $availableModerators = User::where('type', 'moderateur')
            ->where('is_online', true)
            ->where('status', 'active')
            ->where('id', '!=', $moderatorId)
            ->count();

        // Si pas assez de modérateurs disponibles et pas de messages urgents, reporter
        if ($availableModerators == 0 && $profilesWithMessages == 0) {
            Log::info("Réattribution reportée - aucun modérateur disponible et pas de messages urgents", [
                'moderator_id' => $moderatorId
            ]);
            return false;
        }

        // Récupérer les assignations actives du modérateur
        $assignments = ModeratorProfileAssignment::where('user_id', $moderatorId)
            ->where('is_active', true)
            ->get();

        $reassignedAny = false;

        foreach ($assignments as $assignment) {
            // Vérifier si ce profil a des messages en attente
            $hasUnreadMessages = $this->hasUnreadMessages($assignment->profile_id);

            if (!$hasUnreadMessages && !$this->shouldForceRotation()) {
                Log::info("Réattribution ignorée - pas de messages en attente", [
                    'moderator_id' => $moderatorId,
                    'profile_id' => $assignment->profile_id
                ]);
                continue;
            }

            // Désactiver l'assignation actuelle
            $assignment->is_active = false;
            $assignment->save();

            // Déclencher la logique de réattribution via le service
            $reassignedCount = $this->assignmentService->reassignInactiveProfiles(1);

            if ($reassignedCount > 0) {
                $reassignedAny = true;
                Log::info("Réattribution déclenchée pour inactivité", [
                    'moderator_id' => $moderatorId,
                    'profile_id' => $assignment->profile_id,
                    'reassigned_count' => $reassignedCount
                ]);

                // Émettre un événement pour informer le frontend de la réattribution
                $newAssignment = ModeratorProfileAssignment::where('profile_id', $assignment->profile_id)
                    ->where('is_active', true)
                    ->first();

                if ($newAssignment && $newAssignment->user_id != $moderatorId) {
                    event(new \App\Events\ProfileAssigned(
                        User::find($newAssignment->user_id),
                        $assignment->profile_id,
                        $newAssignment->id,
                        $moderatorId,
                        'inactivity'
                    ));
                }
            }
        }

        return $reassignedAny;
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
     * ✅ CORRECTION : Identifier les modérateurs surchargés de manière plus intelligente
     */
    public function identifyOverloadedModerators()
    {
        $moderators = User::where('type', 'moderateur')
            ->where('status', 'active')
            ->where('is_online', true) // ✅ CORRECTION : Seulement les modérateurs en ligne
            ->get();

        if ($moderators->isEmpty()) {
            return false; // Pas de modérateurs en ligne
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

        // ✅ CORRECTION : Considérer la charge moyenne aussi
        $averageLoad = $totalWorkload / $moderators->count();
        $highAverageLoad = $averageLoad > 3; // Plus de 3 conversations par modérateur en moyenne

        // Si plus de 70% des modérateurs sont surchargés ET charge moyenne élevée
        return ($overloadedCount / $moderators->count()) > 0.7 && $highAverageLoad;
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

            // Mettre à jour la dernière activité pour prolonger l'assignation
            $assignment->last_activity = now();
            $assignment->last_activity_check = now();
            $assignment->save();

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
