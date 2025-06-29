<?php

namespace App\Tasks;

use App\Models\ModeratorProfileAssignment;
use App\Models\Profile;
use App\Models\Message;
use App\Services\ModeratorAssignmentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Support\Facades\DB;


class RotateModeratorProfilesTask
{
    protected $assignmentService;

    public function __construct(ModeratorAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    public function __invoke()
    {
        Log::info("Début de la rotation des profils modérateurs");

        // 1. Identifier les modérateurs actifs
        $activeModerators = User::where('type', 'moderateur')
            ->where('status', 'active')
            ->where('is_online', true)
            ->get();

        Log::info("Modérateurs actifs trouvés: " . $activeModerators->count());

        if ($activeModerators->isEmpty()) {
            Log::info("Aucun modérateur actif, rotation annulée");
            return 0;
        }

        // 2. Identifier les profils avec des messages en attente
        $profilesWithPendingMessages = $this->getProfilesWithPendingMessages();

        Log::info("Profils avec messages en attente: " . count($profilesWithPendingMessages));

        // 3. Identifier les modérateurs inactifs (sans activité récente)
        // MODIFICATION ICI: Utiliser la méthode getInactiveAssignments() avec 1 minute comme seuil
        $inactiveAssignments = $this->getInactiveAssignments();
        $inactiveModerators = $inactiveAssignments->pluck('user_id')->unique()->toArray();

        Log::info("Modérateurs inactifs: " . count($inactiveModerators));

        // 4. Réattribuer les profils avec messages en attente aux modérateurs inactifs
        $rotationsPerformed = 0;

        foreach ($profilesWithPendingMessages as $profileId) {
            // Vérifier si ce profil est déjà attribué à un modérateur actif
            $hasActiveAssignment = ModeratorProfileAssignment::where('profile_id', $profileId)
                ->where('is_active', true)
                ->exists();

            if (!$hasActiveAssignment && !empty($inactiveModerators)) {
                // Attribuer ce profil à un modérateur inactif
                $moderatorId = array_shift($inactiveModerators);
                $assignment = $this->assignmentService->assignProfileToModerator($moderatorId, $profileId, true);

                if ($assignment) {
                    $rotationsPerformed++;
                    Log::info("Profil réattribué", [
                        'profile_id' => $profileId,
                        'moderator_id' => $moderatorId
                    ]);
                }
            }
        }

        Log::info("Rotation des profils terminée, rotations effectuées: " . $rotationsPerformed);
        return $rotationsPerformed;
    }

    /**
     * Récupère les attributions de modérateurs inactifs
     */
    protected function getInactiveAssignments()
    {
        // Conserver le délai d'inactivité à 1 minute comme vous le souhaitez
        Log::info("Recherche des attributions inactives (inactivité > 1 minute)");

        $inactiveAssignments = ModeratorProfileAssignment::where('is_active', true)
            ->where(function ($query) {
                $query->where('last_activity', '<', Carbon::now()->subMinutes(1))
                    ->orWhereNull('last_activity');
            })
            ->with('user')
            ->get();

        Log::info("Attributions inactives trouvées", [
            'count' => $inactiveAssignments->count(),
            'assignments' => $inactiveAssignments->map(function ($a) {
                return [
                    'id' => $a->id,
                    'user_id' => $a->user_id,
                    'profile_id' => $a->profile_id,
                    'last_activity' => $a->last_activity ? $a->last_activity->diffForHumans() : 'jamais'
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

        Log::info("Profils avec le dernier message provenant du client", [
            'count' => count($pendingProfiles)
        ]);

        return $pendingProfiles;
    }

    /**
     * Vérifie l'inactivité des modérateurs et réattribue les profils si nécessaire
     * Cette méthode peut être appelée plus fréquemment que la rotation complète
     */
    /* public function checkInactivity()
    {
        Log::info("Vérification de l'inactivité des modérateurs");

        // 1. Identifier les modérateurs actifs
        $activeModerators = User::where('type', 'moderateur')
            ->where('status', 'active')
            ->where('is_online', true)
            ->get();

        if ($activeModerators->isEmpty()) {
            Log::info("Aucun modérateur actif, vérification annulée");
            return 0;
        }

        // 2. Identifier les profils avec des messages en attente
        $profilesWithPendingMessages = $this->getProfilesWithPendingMessages();

        // 3. Identifier les modérateurs inactifs (sans activité récente)
        // Utiliser 1 minute comme seuil d'inactivité
        $inactiveAssignments = ModeratorProfileAssignment::where('is_active', true)
            ->where(function ($query) {
                $query->where('last_activity', '<', Carbon::now()->subMinute())
                    ->orWhereNull('last_activity');
            })
            ->get();

        $reassigned = 0;

        foreach ($inactiveAssignments as $assignment) {
            Log::info("Modérateur inactif détecté", [
                'moderator_id' => $assignment->user_id,
                'profile_id' => $assignment->profile_id,
                'last_activity' => $assignment->last_activity ? $assignment->last_activity->diffForHumans() : 'jamais'
            ]);

            // Si des profils sont en attente, réattribuer ce modérateur inactif
            if (!empty($profilesWithPendingMessages)) {
                $pendingProfileId = array_shift($profilesWithPendingMessages);

                // Désactiver l'assignation actuelle
                $assignment->is_active = false;
                $assignment->save();

                // Créer une nouvelle assignation pour un profil en attente
                $newAssignment = $this->assignmentService->assignProfileToModerator(
                    $assignment->user_id,
                    $pendingProfileId,
                    true
                );

                if ($newAssignment) {
                    $reassigned++;
                    Log::info("Profil en attente assigné à un modérateur inactif", [
                        'moderator_id' => $assignment->user_id,
                        'old_profile_id' => $assignment->profile_id,
                        'new_profile_id' => $pendingProfileId
                    ]);
                }
            }
        }

        Log::info("Vérification d'inactivité terminée, réassignations: $reassigned");
        return $reassigned;
    } */

    /**
     * Vérifie l'inactivité des modérateurs et réattribue les profils si nécessaire
     */
    public function checkInactivity()
    {
        Log::info("Vérification de l'inactivité des modérateurs");

        // 1. Modérateurs actifs en ligne
        $activeModerators = User::where('type', 'moderateur')
            ->where('status', 'active')
            ->where('is_online', true)
            ->get();

        if ($activeModerators->isEmpty()) {
            Log::info("Aucun modérateur actif, vérification annulée");
            return 0;
        }

        // 2. Profils avec messages en attente
        $profilesWithPendingMessages = $this->getProfilesWithPendingMessages();

        Log::info("Profils avec messages en attente", [
            'count' => count($profilesWithPendingMessages),
            'profiles' => $profilesWithPendingMessages
        ]);

        // 3. Assignations inactives (> 1 min)
        $inactiveAssignments = ModeratorProfileAssignment::where('is_active', true)
            ->where(function ($query) {
                $query->where('last_activity', '<', now()->subMinute())
                    ->orWhereNull('last_activity');
            })
            ->get();

        Log::info("Assignations inactives trouvées", [
            'count' => $inactiveAssignments->count(),
            'assignments' => $inactiveAssignments->map(function ($a) {
                return [
                    'id' => $a->id,
                    'user_id' => $a->user_id,
                    'profile_id' => $a->profile_id,
                    'last_activity' => $a->last_activity?->diffForHumans() ?? 'jamais'
                ];
            })->toArray()
        ]);

        $reassigned = 0;

        // 4. Traitement des assignations inactives
        foreach ($inactiveAssignments as $assignment) {
            $oldModeratorId = $assignment->user_id;
            $oldProfileId = $assignment->profile_id;

            $assignment->is_active = false;
            $assignment->save();

            Log::info("Assignation désactivée pour inactivité", [
                'assignment_id' => $assignment->id,
                'moderator_id' => $oldModeratorId,
                'profile_id' => $oldProfileId
            ]);

            // Cas 1 : Réattribuer le profil inactif à un autre modérateur
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
                        Log::info("Profil réattribué à un autre modérateur", [
                            'old_moderator' => $oldModeratorId,
                            'new_moderator' => $newModerator->id,
                            'profile_id' => $oldProfileId
                        ]);

                        // 🔔 Événement WebSocket avec informations de réattribution
                        event(new \App\Events\ProfileAssigned(
                            $newModerator,
                            $oldProfileId,
                            $newAssignment->id,
                            $oldModeratorId,  // Ancien modérateur
                            'inactivity'      // Raison de la réattribution
                        ));
                    }
                }
            }

            // Cas 2 : Réattribuer un profil en attente à l'ancien modérateur
            if (!empty($profilesWithPendingMessages)) {
                $pendingProfileId = array_shift($profilesWithPendingMessages);

                $newAssignment = $this->assignmentService->assignProfileToModerator($oldModeratorId, $pendingProfileId);

                if ($newAssignment) {
                    Log::info("Profil en attente assigné à un modérateur inactif", [
                        'moderator_id' => $oldModeratorId,
                        'profile_id' => $pendingProfileId
                    ]);

                    $reassigned++;

                    // 🔔 Événement WebSocket avec informations de réattribution
                    event(new \App\Events\ProfileAssigned(
                        User::find($oldModeratorId),
                        $pendingProfileId,
                        $newAssignment->id,
                        null,               // Pas d'ancien modérateur pour cette assignation
                        'pending_messages'   // Raison de l'assignation: messages en attente
                    ));
                }
            }
        }

        // 5. Mettre en file d'attente les modérateurs sans profil actif
        foreach ($activeModerators as $moderator) {
            $hasActiveAssignment = ModeratorProfileAssignment::where('user_id', $moderator->id)
                ->where('is_active', true)
                ->exists();

            if (!$hasActiveAssignment) {
                $inQueue = \App\Models\ModeratorQueue::where('moderator_id', $moderator->id)
                    ->where('status', 'waiting')
                    ->exists();

                if (!$inQueue) {
                    $this->assignmentService->addModeratorToQueue($moderator->id);

                    Log::info("Modérateur ajouté à la file d'attente", [
                        'moderator_id' => $moderator->id
                    ]);
                }
            }
        }

        Log::info("Vérification d'inactivité terminée", ['total_reassignments' => $reassigned]);
        return $reassigned;
    }
}
