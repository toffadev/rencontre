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


class RotateModeratorProfilesTask2
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

        // 1. Vérifier et traiter la file d'attente AVANT la rotation
        $queueResults = $this->queueService->checkAndProcessQueue();

        Log::info("📋 Résultats de la vérification de la file d'attente", [
            'added_to_queue' => $queueResults['added_to_queue'],
            'processed_assignments' => $queueResults['processed_assignments'],
            'remaining_in_queue' => $queueResults['remaining_in_queue'],
            'available_profiles' => $queueResults['available_profiles']
        ]);

        // 2. Identifier les modérateurs actifs
        $activeModerators = User::where('type', 'moderateur')
            ->where('status', 'active')
            ->where('is_online', true)
            ->get();

        Log::info("👥 Modérateurs actifs trouvés: " . $activeModerators->count());

        if ($activeModerators->isEmpty()) {
            Log::info("⚠️ Aucun modérateur actif, rotation annulée");
            return 0;
        }

        // 3. Identifier les profils avec des messages en attente
        $profilesWithPendingMessages = $this->getProfilesWithPendingMessages();

        Log::info("📥 Profils avec messages en attente: " . count($profilesWithPendingMessages));

        // 4. Identifier les modérateurs inactifs (sans activité récente)
        $inactiveAssignments = $this->getInactiveAssignments();

        // 5. Traiter chaque attribution inactive individuellement
        $rotationsPerformed = 0;

        foreach ($inactiveAssignments as $assignment) {
            // Désactiver l'assignation inactive
            $oldModeratorId = $assignment->user_id;
            $profileId = $assignment->profile_id;

            Log::info("🚫 Désactivation de l'assignation inactive", [
                'assignment_id' => $assignment->id,
                'moderator_id' => $oldModeratorId,
                'profile_id' => $profileId
            ]);

            // Marquer l'assignation comme inactive
            $assignment->is_active = false;
            $assignment->save();

            // Trouver un nouveau modérateur disponible (différent de l'ancien)
            $availableModerators = $activeModerators->filter(function ($moderator) use ($oldModeratorId) {
                return $moderator->id != $oldModeratorId;
            });

            if ($availableModerators->isNotEmpty()) {
                // Prendre le modérateur le moins occupé
                $newModerator = $this->assignmentService->findLeastBusyModerator(null, $profileId);

                if ($newModerator) {
                    // Attribuer le profil au nouveau modérateur
                    $newAssignment = $this->assignmentService->assignProfileToModerator($newModerator->id, $profileId, true);

                    if ($newAssignment) {
                        $rotationsPerformed++;
                        Log::info("🔄 Profil réattribué pour cause d'inactivité", [
                            'profile_id' => $profileId,
                            'old_moderator_id' => $oldModeratorId,
                            'new_moderator_id' => $newModerator->id
                        ]);

                        // Émettre l'événement de réattribution
                        event(new \App\Events\ProfileAssigned(
                            $newModerator,
                            $profileId,
                            $newAssignment->id,
                            $oldModeratorId,
                            'inactivity'
                        ));
                    }
                } else {
                    // Si aucun modérateur disponible, remettre l'ancien en file d'attente
                    $this->queueService->addToQueue($oldModeratorId, 1); // Priorité élevée
                    Log::info("🔄 Ancien modérateur ajouté à la file d'attente", [
                        'moderator_id' => $oldModeratorId
                    ]);
                }
            } else {
                // Remettre l'ancien modérateur en file d'attente
                $this->queueService->addToQueue($oldModeratorId, 1); // Priorité élevée
                Log::info("🔄 Modérateur ajouté à la file d'attente (aucun autre disponible)", [
                    'moderator_id' => $oldModeratorId
                ]);
            }
        }

        // 6. Traiter à nouveau la file d'attente après les rotations
        $finalQueueResults = $this->queueService->checkAndProcessQueue();

        Log::info("📋 Résultats finaux de la file d'attente", [
            'added_to_queue' => $finalQueueResults['added_to_queue'],
            'processed_assignments' => $finalQueueResults['processed_assignments'],
            'remaining_in_queue' => $finalQueueResults['remaining_in_queue'],
            'available_profiles' => $finalQueueResults['available_profiles']
        ]);

        Log::info("✅ Rotation des profils terminée", [
            'rotations_performed' => $rotationsPerformed,
            'total_queue_assignments' => $queueResults['processed_assignments'] + $finalQueueResults['processed_assignments']
        ]);

        return $rotationsPerformed;
    }

    /**
     * Récupère les attributions de modérateurs inactifs
     */
    protected function getInactiveAssignments()
    {
        Log::info("🔍 Recherche des attributions inactives (inactivité > 1 minute)");

        $inactiveTime = Carbon::now()->subMinutes(1);

        $inactiveAssignments = ModeratorProfileAssignment::where('is_active', true)
            ->where(function ($query) use ($inactiveTime) {
                $query->where('last_message_sent', '<', $inactiveTime)
                    ->where(function ($q) use ($inactiveTime) {
                        $q->whereNull('last_typing')
                            ->orWhere('last_typing', '<', $inactiveTime);
                    });
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
     *
     * @return array
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
     * Vérifie l'inactivité des modérateurs et réattribue les profils si nécessaire
     */
    public function checkInactivity()
    {
        Log::info("🔎 Vérification de l'inactivité des modérateurs");

        // 1. Traiter d'abord la file d'attente
        $queueResults = $this->queueService->checkAndProcessQueue();

        Log::info("📋 File d'attente traitée avant vérification d'inactivité", [
            'processed_assignments' => $queueResults['processed_assignments'],
            'remaining_in_queue' => $queueResults['remaining_in_queue']
        ]);

        // 2. Récupérer les modérateurs actifs et en ligne
        $activeModerators = User::where('type', 'moderateur')
            ->where('status', 'active')
            ->where('is_online', true)
            ->get();

        if ($activeModerators->isEmpty()) {
            Log::info("⚠️ Aucun modérateur actif, vérification annulée");
            return 0;
        }

        // 3. Profils avec messages en attente
        $profilesWithPendingMessages = $this->getProfilesWithPendingMessages();

        Log::info("📥 Profils avec messages en attente", [
            'count' => count($profilesWithPendingMessages),
            'profiles' => $profilesWithPendingMessages,
            'timestamp' => now()->toDateTimeString()
        ]);

        // 4. Attributions inactives
        $inactiveTime = now()->subMinute();

        $inactiveAssignments = ModeratorProfileAssignment::where('is_active', true)
            ->where(function ($query) use ($inactiveTime) {
                $query->where(function ($q) use ($inactiveTime) {
                    $q->where('last_message_sent', '<', $inactiveTime)
                        ->where(function ($subq) use ($inactiveTime) {
                            $subq->whereNull('last_typing')
                                ->orWhere('last_typing', '<', $inactiveTime);
                        });
                })
                    ->orWhere(function ($q) use ($inactiveTime) {
                        $q->where('last_activity', '<', $inactiveTime)
                            ->orWhereNull('last_activity');
                    });
            })
            ->join('users', 'moderator_profile_assignments.user_id', '=', 'users.id')
            ->where(function ($query) {
                $query->where('users.is_online', false)
                    ->orWhere('users.last_online_at', '<', now()->subMinutes(3));
            })
            ->select('moderator_profile_assignments.*')
            ->get();

        Log::info("🕒 Assignations inactives détectées", [
            'count' => $inactiveAssignments->count(),
            'timestamp' => now()->toDateTimeString()
        ]);

        $reassigned = 0;

        // 5. Traiter chaque assignation inactive
        foreach ($inactiveAssignments as $assignment) {
            $oldModeratorId = $assignment->user_id;
            $oldProfileId = $assignment->profile_id;

            // Désactiver l'assignation
            $assignment->last_activity_check = now();
            $assignment->is_active = false;
            $assignment->save();

            Log::info("🚫 Assignation désactivée", [
                'assignment_id' => $assignment->id,
                'moderator_id' => $oldModeratorId,
                'profile_id' => $oldProfileId
            ]);

            // Cas 1 : Réattribuer le même profil si encore des messages non lus
            $hasUnreadMessages = Message::where('profile_id', $oldProfileId)
                ->where('is_from_client', true)
                ->whereNull('read_at')
                ->exists();

            if ($hasUnreadMessages) {
                $newModerator = $this->assignmentService->findLeastBusyModerator($oldModeratorId, $oldProfileId);

                if ($newModerator) {
                    $newAssignment = $this->assignmentService->assignProfileToModerator($newModerator->id, $oldProfileId);

                    if ($newAssignment) {
                        $reassigned++;

                        Log::info("🔁 Profil réattribué pour inactivité", [
                            'old_moderator' => $oldModeratorId,
                            'new_moderator' => $newModerator->id,
                            'profile_id' => $oldProfileId
                        ]);

                        event(new \App\Events\ProfileAssigned(
                            $newModerator,
                            $oldProfileId,
                            $newAssignment->id,
                            $oldModeratorId,
                            'inactivity'
                        ));
                    }
                } else {
                    // Aucun modérateur disponible, ajouter à la file d'attente
                    $this->queueService->addToQueue($oldModeratorId, 1);
                    Log::info("🔄 Modérateur ajouté à la file d'attente (profil avec messages non lus)", [
                        'moderator_id' => $oldModeratorId,
                        'profile_id' => $oldProfileId
                    ]);
                }
            }

            // Cas 2 : Réattribuer un profil en attente à l'ancien modérateur
            if (!empty($profilesWithPendingMessages)) {
                $pendingProfileId = array_shift($profilesWithPendingMessages);

                $moderator = User::find($oldModeratorId);
                if ($moderator && $moderator->is_online && $moderator->status === 'active') {
                    $newAssignment = $this->assignmentService->assignProfileToModerator($oldModeratorId, $pendingProfileId);

                    if ($newAssignment) {
                        $reassigned++;

                        Log::info("📌 Profil en attente attribué à l'ancien modérateur", [
                            'moderator_id' => $oldModeratorId,
                            'profile_id' => $pendingProfileId
                        ]);

                        event(new \App\Events\ProfileAssigned(
                            $moderator,
                            $pendingProfileId,
                            $newAssignment->id,
                            null,
                            'pending_messages'
                        ));
                    }
                } else {
                    // Remettre le profil dans la liste s'il ne peut pas le recevoir
                    array_push($profilesWithPendingMessages, $pendingProfileId);
                    Log::info("↩️ Profil remis en attente (modérateur hors ligne)", [
                        'moderator_id' => $oldModeratorId,
                        'profile_id' => $pendingProfileId
                    ]);
                }
            }
        }

        // 6. Attribuer un profil aux modérateurs actifs sans profil
        foreach ($activeModerators as $moderator) {
            $hasActiveAssignment = ModeratorProfileAssignment::where('user_id', $moderator->id)
                ->where('is_active', true)
                ->exists();

            if (!$hasActiveAssignment && !empty($profilesWithPendingMessages)) {
                $pendingProfileId = array_shift($profilesWithPendingMessages);

                $newAssignment = $this->assignmentService->assignProfileToModerator($moderator->id, $pendingProfileId, true);

                if ($newAssignment) {
                    $reassigned++;

                    Log::info("🆕 Profil attribué à un modérateur disponible", [
                        'moderator_id' => $moderator->id,
                        'profile_id' => $pendingProfileId
                    ]);

                    event(new \App\Events\ProfileAssigned(
                        $moderator,
                        $pendingProfileId,
                        $newAssignment->id,
                        null,
                        'new_assignment'
                    ));
                }
            } elseif (!$hasActiveAssignment) {
                // Ajouter à la file d'attente s'il n'a rien reçu
                $this->queueService->addToQueue($moderator->id, 0);
                Log::info("🧾 Modérateur ajouté à la file d'attente", [
                    'moderator_id' => $moderator->id
                ]);
            }
        }

        // 7. Traiter à nouveau la file d'attente après les réattributions
        $finalQueueResults = $this->queueService->checkAndProcessQueue();

        Log::info("📋 File d'attente retraitée après vérification d'inactivité", [
            'processed_assignments' => $finalQueueResults['processed_assignments'],
            'remaining_in_queue' => $finalQueueResults['remaining_in_queue']
        ]);

        Log::info("✅ Vérification d'inactivité terminée", [
            'direct_reassignments' => $reassigned,
            'queue_assignments' => $queueResults['processed_assignments'] + $finalQueueResults['processed_assignments'],
            'total_assignments' => $reassigned + $queueResults['processed_assignments'] + $finalQueueResults['processed_assignments']
        ]);

        return $reassigned;
    }
}
