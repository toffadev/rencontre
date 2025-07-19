<?php

namespace App\Tasks;

use App\Models\ModeratorProfileAssignment;
use App\Models\Profile;
use App\Models\Message;
use App\Services\ModeratorAssignmentService;
use App\Services\ModeratorQueueService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RotateModeratorProfilesTask
{
    protected $assignmentService;
    protected $queueService;

    public function __construct(ModeratorAssignmentService $assignmentService, ModeratorQueueService $queueService)
    {
        $this->assignmentService = $assignmentService;
        $this->queueService = $queueService;
    }

    public function __invoke()
    {
        Log::info("🔄 Début de la rotation des profils modérateurs");

        // 1. Identifier les modérateurs actifs
        $activeModerators = User::where('type', 'moderateur')
            ->where('status', 'active')
            ->where('is_online', true)
            ->get();

        Log::info("👥 Modérateurs actifs trouvés: " . $activeModerators->count());

        if ($activeModerators->isEmpty()) {
            Log::info("⚠️ Aucun modérateur actif, rotation annulée");
            return 0;
        }

        // 2. Identifier les profils avec des messages en attente (PRIORITÉ ABSOLUE)
        $profilesWithPendingMessages = $this->getProfilesWithPendingMessages();
        Log::info("📥 Profils avec messages en attente: " . count($profilesWithPendingMessages));

        // 3. Identifier TOUTES les assignations inactives avec un seuil plus strict
        $allInactiveAssignments = $this->getAllInactiveAssignments();
        Log::info("🕒 Assignations inactives détectées: " . $allInactiveAssignments->count());

        $rotationsPerformed = 0;
        $assignedModerators = []; // Tracker les modérateurs déjà assignés dans ce cycle
        $processedProfiles = []; // Tracker les profils déjà traités pour éviter les doublons

        // 4. PRIORITÉ 1: Traiter les profils avec messages en attente
        foreach ($profilesWithPendingMessages as $profileId) {
            $result = $this->handleProfileWithPendingMessages(
                $profileId,
                $activeModerators,
                $assignedModerators,
                $processedProfiles,
                $allInactiveAssignments
            );

            if ($result['rotated']) {
                $rotationsPerformed++;
                $assignedModerators = array_merge($assignedModerators, $result['assigned_moderators']);
            }
            $processedProfiles[] = $profileId;
        }

        // 5. PRIORITÉ 2: Traiter TOUTES les autres assignations inactives (CORRECTION CRITIQUE)
        foreach ($allInactiveAssignments as $assignment) {
            $profileId = $assignment->profile_id;

            // Vérifier si le profil a déjà été traité ET si la rotation a réussi
            if (in_array($profileId, $processedProfiles)) {
                // Vérifier si l'assignation est toujours active (rotation a échoué)
                $currentAssignment = ModeratorProfileAssignment::where('profile_id', $profileId)
                    ->where('is_active', true)
                    ->first();

                if ($currentAssignment && $currentAssignment->user_id == $assignment->user_id) {
                    // L'assignation n'a pas changé, le modérateur est toujours inactif
                    Log::info("🔄 Profil déjà traité mais rotation échouée, nouvelle tentative", [
                        'profile_id' => $profileId,
                        'moderator_id' => $assignment->user_id
                    ]);
                } else {
                    // Rotation réussie, passer au suivant
                    continue;
                }
            }

            // Vérifier si le modérateur a déjà été réassigné dans ce cycle
            if (in_array($assignment->user_id, $assignedModerators)) {
                Log::info("⏭️ Modérateur déjà réassigné dans ce cycle", [
                    'moderator_id' => $assignment->user_id,
                    'profile_id' => $profileId
                ]);
                continue;
            }

            // CORRECTION : Vérifier à nouveau l'inactivité (état peut avoir changé)
            if (!$this->isModeratorInactive($assignment)) {
                Log::info("✅ Modérateur redevenu actif, pas de rotation nécessaire", [
                    'moderator_id' => $assignment->user_id,
                    'profile_id' => $profileId
                ]);
                continue;
            }

            $newModeratorId = $this->rotateInactiveAssignment($assignment, $activeModerators, $assignedModerators);

            if ($newModeratorId) {
                $assignedModerators[] = $newModeratorId;
                $rotationsPerformed++;
                Log::info("✅ Rotation d'assignation inactive réussie", [
                    'old_moderator_id' => $assignment->user_id,
                    'new_moderator_id' => $newModeratorId,
                    'profile_id' => $profileId
                ]);
            }

            $processedProfiles[] = $profileId;
        }

        // 6. PRIORITÉ 3: Traiter la file d'attente SEULEMENT après avoir géré les urgences
        $queueResults = $this->queueService->checkAndProcessQueue($assignedModerators);
        Log::info("📋 Résultats de la file d'attente", [
            'processed_assignments' => $queueResults['processed_assignments'],
            'remaining_in_queue' => $queueResults['remaining_in_queue']
        ]);

        // 7. VÉRIFICATION FINALE : S'assurer qu'aucun modérateur inactif n'a été oublié
        $this->performFinalInactivityCheck($activeModerators, $assignedModerators);

        Log::info("✅ Rotation des profils terminée", [
            'rotations_performed' => $rotationsPerformed,
            'queue_assignments' => $queueResults['processed_assignments'],
            'total_assignments' => $rotationsPerformed + $queueResults['processed_assignments'],
            'assigned_moderators' => $assignedModerators,
            'processed_profiles' => $processedProfiles
        ]);

        return $rotationsPerformed;
    }



    /**
     * MÉTHODE CORRIGÉE: Gérer les profils avec messages en attente
     */
    private function handleProfileWithPendingMessages($profileId, $activeModerators, $assignedModerators, $processedProfiles, $allInactiveAssignments)
    {
        $result = ['rotated' => false, 'assigned_moderators' => []];

        // Vérifier si ce profil a déjà un modérateur assigné
        $existingAssignment = ModeratorProfileAssignment::where('profile_id', $profileId)
            ->where('is_active', true)
            ->first();

        if ($existingAssignment) {
            // PROFIL DÉJÀ ASSIGNÉ - Vérifier l'activité du modérateur
            $isInactive = $this->isModeratorInactive($existingAssignment);

            if (!$isInactive) {
                Log::info("✅ Profil avec messages déjà assigné à un modérateur actif", [
                    'profile_id' => $profileId,
                    'moderator_id' => $existingAssignment->user_id
                ]);
                return $result;
            }

            // Modérateur inactif, procéder à la rotation
            $newModeratorId = $this->rotateInactiveAssignment($existingAssignment, $activeModerators, $assignedModerators);
            if ($newModeratorId) {
                $result['assigned_moderators'][] = $newModeratorId;
                $result['rotated'] = true;
                Log::info("🔄 Rotation effectuée pour profil avec messages", [
                    'profile_id' => $profileId,
                    'old_moderator_id' => $existingAssignment->user_id,
                    'new_moderator_id' => $newModeratorId
                ]);
            }
        } else {
            // PROFIL NON ASSIGNÉ - C'EST LE PROBLÈME PRINCIPAL !
            Log::info("🆕 Profil avec messages en attente non assigné détecté", [
                'profile_id' => $profileId
            ]);

            // Trouver le meilleur modérateur disponible
            $newModerator = $this->findBestAvailableModerator($activeModerators, $assignedModerators);

            if ($newModerator) {
                $newAssignment = $this->assignmentService->assignProfileToModerator($newModerator->id, $profileId, true);

                if ($newAssignment) {
                    $result['assigned_moderators'][] = $newModerator->id;
                    $result['rotated'] = true; // Changé : considérer comme une rotation réussie
                    Log::info("🆕 Profil avec messages en attente assigné avec succès", [
                        'profile_id' => $profileId,
                        'moderator_id' => $newModerator->id,
                        'assignment_id' => $newAssignment->id
                    ]);

                    // CORRECTION : Déclencher l'événement ProfileAssigned
                    event(new \App\Events\ProfileAssigned(
                        $newModerator,
                        $profileId,
                        $newAssignment->id,
                        null, // Pas d'ancien modérateur
                        'pending_messages_assignment' // Type d'assignation
                    ));
                } else {
                    Log::warning("❌ Échec de l'assignation du profil avec messages", [
                        'profile_id' => $profileId,
                        'moderator_id' => $newModerator->id
                    ]);
                }
            } else {
                Log::warning("⚠️ Aucun modérateur disponible pour profil avec messages", [
                    'profile_id' => $profileId,
                    'available_moderators' => $activeModerators->count(),
                    'already_assigned' => count($assignedModerators)
                ]);

                // NOUVEAU : Ajouter à la file d'attente si aucun modérateur disponible
                // Note: Vous pourriez implémenter une méthode addToQueue ici
            }
        }

        return $result;
    }


    /**
     * MÉTHODE CORRIGÉE: Trouver le meilleur modérateur disponible
     */
    private function findBestAvailableModerator($activeModerators, $assignedModerators, $excludeModeratorId = null)
    {
        $availableModerators = $activeModerators->filter(function ($moderator) use ($assignedModerators, $excludeModeratorId) {
            // Exclure les modérateurs déjà assignés dans ce cycle
            if (in_array($moderator->id, $assignedModerators)) {
                return false;
            }

            // Exclure explicitement le modérateur à remplacer
            if ($excludeModeratorId && $moderator->id == $excludeModeratorId) {
                return false;
            }

            return true;
        });

        if ($availableModerators->isEmpty()) {
            return null;
        }

        // Prendre le modérateur avec le moins d'assignations actives
        return $availableModerators->sortBy(function ($moderator) {
            return ModeratorProfileAssignment::where('user_id', $moderator->id)
                ->where('is_active', true)
                ->count();
        })->first();
    }

    /**
     * NOUVELLE MÉTHODE: Vérification finale pour s'assurer qu'aucun modérateur inactif n'est oublié
     */
    private function performFinalInactivityCheck($activeModerators, $assignedModerators)
    {
        Log::info("🔍 Vérification finale d'inactivité");

        $finalCheck = ModeratorProfileAssignment::where('is_active', true)
            ->whereHas('user', function ($query) {
                $query->where('is_online', true)
                    ->where('status', 'active');
            })
            ->with('user')
            ->get();

        $forgottenInactive = $finalCheck->filter(function ($assignment) use ($assignedModerators) {
            // Exclure les modérateurs déjà traités dans ce cycle
            if (in_array($assignment->user_id, $assignedModerators)) {
                return false;
            }

            return $this->isModeratorInactive($assignment);
        });

        if ($forgottenInactive->count() > 0) {
            Log::warning("⚠️ Modérateurs inactifs oubliés détectés", [
                'count' => $forgottenInactive->count(),
                'assignments' => $forgottenInactive->map(function ($a) {
                    return [
                        'user_id' => $a->user_id,
                        'profile_id' => $a->profile_id,
                        'last_message' => $a->last_message_sent ? $a->last_message_sent->diffForHumans() : 'jamais',
                        'last_typing' => $a->last_typing ? $a->last_typing->diffForHumans() : 'jamais'
                    ];
                })->toArray()
            ]);

            // Optionnel : Programmer une rotation d'urgence ou ajouter à la file d'attente
        } else {
            Log::info("✅ Aucun modérateur inactif oublié");
        }
    }

    /**
     * Vérifier si un modérateur est vraiment inactif
     */
    private function isModeratorInactive($assignment)
    {
        $inactiveThreshold = now()->subMinutes(1); // Seuil strict : 1 minute

        // Vérifier l'utilisateur
        $user = User::find($assignment->user_id);
        if (!$user || !$user->is_online || $user->status !== 'active') {
            return true;
        }

        // Vérifier les activités
        $lastMessage = $assignment->last_message_sent;
        $lastTyping = $assignment->last_typing;

        // CORRECTION : Considérer comme inactif si :
        // - Pas de message depuis le seuil ET
        // - Pas de frappe depuis le seuil
        // - Mais vérifier aussi si ces valeurs sont NULL (jamais d'activité)

        $messageInactive = !$lastMessage || $lastMessage < $inactiveThreshold;
        $typingInactive = !$lastTyping || $lastTyping < $inactiveThreshold;

        // Forcer la journalisation pour le débogage
        Log::debug("Vérification d'inactivité détaillée", [
            'user_id' => $assignment->user_id,
            'profile_id' => $assignment->profile_id,
            'last_message_sent' => $lastMessage ? $lastMessage->toDateTimeString() : 'jamais',
            'last_typing' => $lastTyping ? $lastTyping->toDateTimeString() : 'jamais',
            'message_inactive' => $messageInactive ? 'oui' : 'non',
            'typing_inactive' => $typingInactive ? 'oui' : 'non',
            'threshold' => $inactiveThreshold->toDateTimeString(),
            'is_inactive' => ($messageInactive && $typingInactive) ? 'OUI' : 'NON'
        ]);

        return $messageInactive && $typingInactive;
    }

    /**
     * Effectuer la rotation d'une assignation inactive - VERSION SIMPLIFIÉE
     */
    private function rotateInactiveAssignment($assignment, $activeModerators, $assignedModerators = [])
    {
        $oldModeratorId = $assignment->user_id;
        $profileId = $assignment->profile_id;

        Log::info("🔄 Rotation d'assignation inactive", [
            'assignment_id' => $assignment->id,
            'old_moderator_id' => $oldModeratorId,
            'profile_id' => $profileId
        ]);

        // Désactiver l'assignation
        $assignment->is_active = false;
        $assignment->save();

        // Trouver un nouveau modérateur
        $newModerator = $this->findBestAvailableModerator($activeModerators, $assignedModerators, $oldModeratorId);

        if (!$newModerator) {
            Log::info("⚠️ Aucun modérateur disponible pour rotation", [
                'profile_id' => $profileId,
                'old_moderator_id' => $oldModeratorId
            ]);
            return null;
        }

        $newAssignment = $this->assignmentService->assignProfileToModerator($newModerator->id, $profileId, true);

        if ($newAssignment) {
            Log::info("✅ Profil réattribué avec succès", [
                'profile_id' => $profileId,
                'old_moderator_id' => $oldModeratorId,
                'new_moderator_id' => $newModerator->id
            ]);

            event(new \App\Events\ProfileAssigned(
                $newModerator,
                $profileId,
                $newAssignment->id,
                $oldModeratorId,
                'inactivity_rotation'
            ));

            return $newModerator->id;
        }

        return null;
    }

    /**
     * MÉTHODE AMÉLIORÉE : Récupère TOUTES les attributions de modérateurs inactifs
     */
    protected function getAllInactiveAssignments()
    {
        Log::info("🔍 Recherche exhaustive des attributions inactives (seuil: 1 minute)");

        $inactiveTime = now()->subMinutes(1);

        $inactiveAssignments = ModeratorProfileAssignment::where('is_active', true)
            ->where(function ($query) use ($inactiveTime) {
                // Inactif si pas de message récent ET pas de frappe récente
                $query->where(function ($q) use ($inactiveTime) {
                    $q->where(function ($subq) use ($inactiveTime) {
                        $subq->where('last_message_sent', '<', $inactiveTime)
                            ->orWhereNull('last_message_sent');
                    })->where(function ($subq) use ($inactiveTime) {
                        $subq->where('last_typing', '<', $inactiveTime)
                            ->orWhereNull('last_typing');
                    });
                });
            })
            ->whereHas('user', function ($query) {
                $query->where('is_online', true)
                    ->where('status', 'active');
            })
            ->with('user')
            ->get();

        Log::info("📊 Attributions inactives trouvées (exhaustives)", [
            'count' => $inactiveAssignments->count(),
            'assignments' => $inactiveAssignments->map(function ($a) {
                return [
                    'id' => $a->id,
                    'user_id' => $a->user_id,
                    'profile_id' => $a->profile_id,
                    'last_message_sent' => $a->last_message_sent ? $a->last_message_sent->diffForHumans() : 'jamais',
                    'last_typing' => $a->last_typing ? $a->last_typing->diffForHumans() : 'jamais'
                ];
            })->toArray()
        ]);

        return $inactiveAssignments;
    }

    /**
     * Récupère les profils avec des messages en attente (dernier message provenant du client)
     */
    private function getProfilesWithPendingMessages()
    {
        // Sous-requête pour obtenir le dernier message de chaque conversation (client-profil)
        $latestMessages = DB::table('messages')
            ->select(
                'client_id',
                'profile_id',
                DB::raw('MAX(created_at) as latest_message_at')
            )
            ->groupBy('client_id', 'profile_id');

        // Requête principale pour trouver les conversations où le dernier message est du client
        $pendingProfiles = DB::table('messages as m')
            ->joinSub($latestMessages, 'latest', function ($join) {
                $join->on('m.client_id', '=', 'latest.client_id')
                    ->on('m.profile_id', '=', 'latest.profile_id')
                    ->on('m.created_at', '=', 'latest.latest_message_at');
            })
            ->where('m.is_from_client', true)
            ->select('m.profile_id')
            ->distinct()
            ->pluck('profile_id')
            ->toArray();

        Log::info("📥 Profils avec le dernier message provenant du client", [
            'count' => count($pendingProfiles),
            'profiles' => $pendingProfiles
        ]);

        return $pendingProfiles;
    }

    /**
     * Méthode simplifiée pour vérifier l'inactivité sans les complications de l'ancienne version
     */
    public function checkInactivity()
    {
        Log::info("🔎 Vérification simplifiée de l'inactivité");

        // Appeler la logique principale qui est maintenant prioritaire et cohérente
        return $this->__invoke();
    }
}
