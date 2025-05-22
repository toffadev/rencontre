<?php

namespace App\Services;

use App\Events\ProfileAssigned;
use App\Models\ModeratorProfileAssignment;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ModeratorAssignmentService
{
    /**
     * Attribuer un profil à un modérateur
     *
     * @param User $moderator Le modérateur
     * @param Profile|null $profile Le profil à attribuer (ou null pour auto-sélection)
     * @return ModeratorProfileAssignment|null
     */
    public function assignProfileToModerator(User $moderator, ?Profile $profile = null): ?ModeratorProfileAssignment
    {
        // Vérifier que l'utilisateur est bien un modérateur
        if (!$moderator->isModerator()) {
            return null;
        }

        // Désactiver les attributions actuelles du modérateur
        ModeratorProfileAssignment::where('user_id', $moderator->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Si aucun profil spécifique n'est fourni, en trouver un disponible
        if (!$profile) {
            $profile = $this->findAvailableProfile();

            // Si aucun profil n'est disponible, retourner null
            if (!$profile) {
                return null;
            }
        } else {
            // Vérifier si le profil est déjà utilisé activement par un autre modérateur
            $isProfileInUse = ModeratorProfileAssignment::where('profile_id', $profile->id)
                ->where('is_active', true)
                ->exists();

            if ($isProfileInUse) {
                return null;
            }
        }

        // Créer la nouvelle attribution
        $assignment = ModeratorProfileAssignment::create([
            'user_id' => $moderator->id,
            'profile_id' => $profile->id,
            'is_active' => true,
            'last_activity' => now(),
        ]);

        // Déclencher l'événement d'attribution
        event(new ProfileAssigned($assignment));

        return $assignment;
    }

    /**
     * Trouver un profil disponible pour attribution
     *
     * @return Profile|null
     */
    private function findAvailableProfile(): ?Profile
    {
        // Récupérer les ID des profils actuellement attribués et actifs
        $assignedProfileIds = ModeratorProfileAssignment::where('is_active', true)
            ->pluck('profile_id')
            ->toArray();

        // Récupérer un profil actif qui n'est pas actuellement attribué
        return Profile::where('status', 'active')
            ->whereNotIn('id', $assignedProfileIds)
            ->inRandomOrder()
            ->first();
    }

    /**
     * Libérer un profil attribué à un modérateur
     *
     * @param User $moderator Le modérateur
     * @return bool
     */
    public function releaseProfile(User $moderator): bool
    {
        return ModeratorProfileAssignment::where('user_id', $moderator->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    /**
     * Obtenir le profil actuellement attribué à un modérateur
     *
     * @param User $moderator Le modérateur
     * @return Profile|null
     */
    public function getCurrentAssignedProfile(User $moderator): ?Profile
    {
        $assignment = ModeratorProfileAssignment::where('user_id', $moderator->id)
            ->where('is_active', true)
            ->first();

        return $assignment ? $assignment->profile : null;
    }

    /**
     * Mettre à jour la dernière activité d'un modérateur sur un profil
     *
     * @param User $moderator Le modérateur
     * @return bool
     */
    public function updateLastActivity(User $moderator): bool
    {
        return ModeratorProfileAssignment::where('user_id', $moderator->id)
            ->where('is_active', true)
            ->update(['last_activity' => now()]);
    }

    /**
     * Réattribuer les profils des modérateurs inactifs
     *
     * @param int $inactiveMinutes Nombre de minutes d'inactivité avant réattribution
     * @return int Nombre de profils réattribués
     */
    public function reassignInactiveProfiles(int $inactiveMinutes = 30): int
    {
        $cutoffTime = now()->subMinutes($inactiveMinutes);

        // Récupérer les attributions inactives
        $inactiveAssignments = ModeratorProfileAssignment::where('is_active', true)
            ->where('last_activity', '<', $cutoffTime)
            ->get();

        // Marquer ces attributions comme inactives
        foreach ($inactiveAssignments as $assignment) {
            $assignment->update(['is_active' => false]);
        }

        return $inactiveAssignments->count();
    }
}
