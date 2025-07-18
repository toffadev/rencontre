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

    /* public function __invoke()
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

        // 3. Identifier les modérateurs inactifs avec un seuil plus strict
        $inactiveAssignments = $this->getInactiveAssignments();
        Log::info("🕒 Assignations inactives détectées: " . $inactiveAssignments->count());

        $rotationsPerformed = 0;

        // 4. PRIORITÉ 1: Traiter les profils avec messages en attente
        foreach ($profilesWithPendingMessages as $profileId) {
            // Vérifier si ce profil a déjà un modérateur actif
            $existingAssignment = ModeratorProfileAssignment::where('profile_id', $profileId)
                ->where('is_active', true)
                ->first();

            if ($existingAssignment) {
                // Vérifier si le modérateur assigné est vraiment inactif
                $isInactive = $this->isModeratorInactive($existingAssignment);

                if (!$isInactive) {
                    Log::info("✅ Profil déjà assigné à un modérateur actif", [
                        'profile_id' => $profileId,
                        'moderator_id' => $existingAssignment->user_id
                    ]);
                    continue;
                }

                // Modérateur inactif, procéder à la rotation
                $this->rotateInactiveAssignment($existingAssignment, $activeModerators);
                $rotationsPerformed++;
            } else {
                // Aucun modérateur assigné, trouver le moins occupé
                $newModerator = $this->assignmentService->findLeastBusyModerator(null, $profileId);

                if ($newModerator) {
                    $newAssignment = $this->assignmentService->assignProfileToModerator($newModerator->id, $profileId, true);

                    if ($newAssignment) {
                        $rotationsPerformed++;
                        Log::info("🆕 Profil avec messages en attente assigné", [
                            'profile_id' => $profileId,
                            'moderator_id' => $newModerator->id
                        ]);

                        event(new \App\Events\ProfileAssigned(
                            $newModerator,
                            $profileId,
                            $newAssignment->id,
                            null,
                            'pending_messages'
                        ));
                    }
                }
            }
        }

        // 5. PRIORITÉ 2: Traiter les autres assignations inactives
        foreach ($inactiveAssignments as $assignment) {
            $profileId = $assignment->profile_id;

            // Éviter de traiter deux fois le même profil
            if (in_array($profileId, $profilesWithPendingMessages)) {
                continue;
            }

            $this->rotateInactiveAssignment($assignment, $activeModerators);
            $rotationsPerformed++;
        }

        // 6. PRIORITÉ 3: Traiter la file d'attente SEULEMENT après avoir géré les urgences
        $queueResults = $this->queueService->checkAndProcessQueue();
        Log::info("📋 Résultats de la file d'attente", [
            'processed_assignments' => $queueResults['processed_assignments'],
            'remaining_in_queue' => $queueResults['remaining_in_queue']
        ]);

        Log::info("✅ Rotation des profils terminée", [
            'rotations_performed' => $rotationsPerformed,
            'queue_assignments' => $queueResults['processed_assignments'],
            'total_assignments' => $rotationsPerformed + $queueResults['processed_assignments']
        ]);

        return $rotationsPerformed;
    } */

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

        // 3. Identifier les modérateurs inactifs avec un seuil plus strict
        $inactiveAssignments = $this->getInactiveAssignments();
        Log::info("🕒 Assignations inactives détectées: " . $inactiveAssignments->count());

        $rotationsPerformed = 0;
        $assignedModerators = []; // Tracker les modérateurs déjà assignés dans ce cycle

        // 4. PRIORITÉ 1: Traiter les profils avec messages en attente
        foreach ($profilesWithPendingMessages as $profileId) {
            // Vérifier si ce profil a déjà un modérateur actif
            $existingAssignment = ModeratorProfileAssignment::where('profile_id', $profileId)
                ->where('is_active', true)
                ->first();

            if ($existingAssignment) {
                // Vérifier si le modérateur assigné est vraiment inactif
                $isInactive = $this->isModeratorInactive($existingAssignment);

                if (!$isInactive) {
                    Log::info("✅ Profil déjà assigné à un modérateur actif", [
                        'profile_id' => $profileId,
                        'moderator_id' => $existingAssignment->user_id
                    ]);
                    continue;
                }

                // Modérateur inactif, procéder à la rotation
                $newModeratorId = $this->rotateInactiveAssignment($existingAssignment, $activeModerators, $assignedModerators);
                if ($newModeratorId) {
                    $assignedModerators[] = $newModeratorId;
                    $rotationsPerformed++;
                }
            } else {
                // Aucun modérateur assigné, trouver le moins occupé parmi les disponibles
                $availableModerators = $activeModerators->filter(function ($moderator) use ($assignedModerators) {
                    return !in_array($moderator->id, $assignedModerators);
                });

                if ($availableModerators->isNotEmpty()) {
                    $newModerator = $availableModerators->sortBy(function ($moderator) {
                        return ModeratorProfileAssignment::where('user_id', $moderator->id)
                            ->where('is_active', true)
                            ->count();
                    })->first();

                    if ($newModerator) {
                        $newAssignment = $this->assignmentService->assignProfileToModerator($newModerator->id, $profileId, true);

                        if ($newAssignment) {
                            $assignedModerators[] = $newModerator->id;
                            $rotationsPerformed++;
                            Log::info("🆕 Profil avec messages en attente assigné", [
                                'profile_id' => $profileId,
                                'moderator_id' => $newModerator->id
                            ]);

                            event(new \App\Events\ProfileAssigned(
                                $newModerator,
                                $profileId,
                                $newAssignment->id,
                                null,
                                'pending_messages'
                            ));
                        }
                    }
                }
            }
        }

        // 5. PRIORITÉ 2: Traiter les autres assignations inactives
        foreach ($inactiveAssignments as $assignment) {
            $profileId = $assignment->profile_id;

            // Éviter de traiter deux fois le même profil
            if (in_array($profileId, $profilesWithPendingMessages)) {
                continue;
            }

            // Vérifier si l'ancien modérateur n'a pas déjà été réassigné
            if (in_array($assignment->user_id, $assignedModerators)) {
                continue;
            }

            $newModeratorId = $this->rotateInactiveAssignment($assignment, $activeModerators, $assignedModerators);

            if ($newModeratorId) {
                $assignedModerators[] = $newModeratorId;
                $rotationsPerformed++;
            }
        }

        // 6. PRIORITÉ 3: Traiter la file d'attente SEULEMENT après avoir géré les urgences
        $queueResults = $this->queueService->checkAndProcessQueue($assignedModerators);
        Log::info("📋 Résultats de la file d'attente", [
            'processed_assignments' => $queueResults['processed_assignments'],
            'remaining_in_queue' => $queueResults['remaining_in_queue']
        ]);

        Log::info("✅ Rotation des profils terminée", [
            'rotations_performed' => $rotationsPerformed,
            'queue_assignments' => $queueResults['processed_assignments'],
            'total_assignments' => $rotationsPerformed + $queueResults['processed_assignments'],
            'assigned_moderators' => $assignedModerators
        ]);

        return $rotationsPerformed;
    }

    /**
     * Vérifier si un modérateur est vraiment inactif
     */
    private function isModeratorInactive($assignment)
    {
        $inactiveThreshold = now()->subMinutes(1); // Seuil plus strict : 2 minutes

        // Vérifier l'utilisateur
        $user = User::find($assignment->user_id);
        if (!$user || !$user->is_online || $user->status !== 'active') {
            return true;
        }

        // Vérifier les activités
        $lastMessage = $assignment->last_message_sent;
        $lastTyping = $assignment->last_typing;

        // Inactif si :
        // - Pas de message depuis le seuil ET
        // - Pas de frappe depuis le seuil ET 

        return (!$lastMessage || $lastMessage < $inactiveThreshold) &&
            (!$lastTyping || $lastTyping < $inactiveThreshold);
    }

    /**
     * Effectuer la rotation d'une assignation inactive
     */
    /* private function rotateInactiveAssignment($assignment, $activeModerators)
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

        // CORRECTION : Filtrer les modérateurs disponibles en excluant l'ancien
        $availableModerators = $activeModerators->filter(function ($moderator) use ($oldModeratorId) {
            return $moderator->id != $oldModeratorId;
        });

        Log::info("👥 Modérateurs disponibles pour rotation", [
            'total_active' => $activeModerators->count(),
            'available_for_rotation' => $availableModerators->count()
        ]);

        $newModerator = null;

        if ($availableModerators->isNotEmpty()) {
            // Essayer d'abord avec le service (mais uniquement parmi les disponibles)
            $newModerator = $this->assignmentService->findLeastBusyModerator($oldModeratorId, $profileId);

            // Si le service ne retourne pas un modérateur valide, prendre le moins occupé manuellement
            if (!$newModerator || $newModerator->id == $oldModeratorId) {
                // Prendre le modérateur avec le moins d'assignations actives
                $newModerator = $availableModerators->sortBy(function ($moderator) {
                    return ModeratorProfileAssignment::where('user_id', $moderator->id)
                        ->where('is_active', true)
                        ->count();
                })->first();
            }
        }

        if ($newModerator && $newModerator->id != $oldModeratorId) {
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
                    'inactivity'
                ));
            }
        } else {
            // Aucun autre modérateur disponible, profil reste non assigné
            Log::info("⚠️ Aucun autre modérateur disponible, profil reste non assigné", [
                'profile_id' => $profileId,
                'old_moderator_id' => $oldModeratorId
            ]);

            // Le profil sera repris par la logique de messages en attente au prochain cycle
        }
    } */

    /**
     * Effectuer la rotation d'une assignation inactive
     */
    private function rotateInactiveAssignment($assignment, $activeModerators, $assignedModerators = [])
    {
        $oldModeratorId = $assignment->user_id;
        $profileId = $assignment->profile_id;

        Log::info("🔄 Rotation d'assignation inactive", [
            'assignment_id' => $assignment->id,
            'old_moderator_id' => $oldModeratorId,
            'profile_id' => $profileId,
            'already_assigned_moderators' => $assignedModerators
        ]);

        // Désactiver l'assignation
        $assignment->is_active = false;
        $assignment->save();

        // Filtrer les modérateurs disponibles en excluant l'ancien ET ceux déjà assignés
        $availableModerators = $activeModerators->filter(function ($moderator) use ($oldModeratorId, $assignedModerators) {
            return $moderator->id != $oldModeratorId && !in_array($moderator->id, $assignedModerators);
        });

        Log::info("👥 Modérateurs disponibles pour rotation", [
            'total_active' => $activeModerators->count(),
            'excluded_old' => $oldModeratorId,
            'excluded_assigned' => $assignedModerators,
            'available_for_rotation' => $availableModerators->count()
        ]);

        $newModerator = null;

        if ($availableModerators->isNotEmpty()) {
            // Prendre le modérateur avec le moins d'assignations actives parmi les disponibles
            $newModerator = $availableModerators->sortBy(function ($moderator) {
                return ModeratorProfileAssignment::where('user_id', $moderator->id)
                    ->where('is_active', true)
                    ->count();
            })->first();
        }

        if ($newModerator) {
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
                    'inactivity'
                ));

                return $newModerator->id;
            }
        }

        Log::info("⚠️ Aucun modérateur disponible pour rotation", [
            'profile_id' => $profileId,
            'old_moderator_id' => $oldModeratorId,
            'available_count' => $availableModerators->count()
        ]);

        // Le profil sera repris par la logique de messages en attente au prochain cycle
        return null;
    }

    /**
     * Récupère les attributions de modérateurs inactifs avec des critères plus stricts
     */
    protected function getInactiveAssignments()
    {
        Log::info("🔍 Recherche des attributions inactives (seuil: 1 minute)");

        $inactiveTime = now()->subMinutes(1); // Seuil de 1 minute au lieu de 2

        $inactiveAssignments = ModeratorProfileAssignment::where('is_active', true)
            ->where(function ($query) use ($inactiveTime) {
                // CORRECTION: Ne plus utiliser last_activity dans la requête
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

        Log::info("📊 Attributions inactives trouvées", [
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
