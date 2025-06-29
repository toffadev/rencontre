<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\ProfileLock;
use App\Models\ClientLock;
use App\Events\ProfileLockStatusChanged;

/**
 * Service pour gérer les verrous sur les profils et clients.
 * 
 * Ce service permet de :
 * - verrouiller et déverrouiller un profil ou un client pour éviter les accès concurrents,
 * - vérifier si un profil ou client est actuellement verrouillé,
 * - nettoyer automatiquement les verrous expirés,
 * - fournir des informations sur les verrous en cours,
 * - notifier les modérateurs lors des changements de statut de verrouillage.
 * 
 * L'objectif est d'assurer qu'un profil ou client ne soit pas modifié simultanément
 * par plusieurs modérateurs, garantissant ainsi la cohérence et la bonne gestion des conversations.
 */
class ProfileLockService
{
    /**
     * Verrouiller un profil
     */
    public function lockProfile($profileId, $moderatorId = null, $duration = 30)
    {
        // Vérifier si le profil est déjà verrouillé
        if ($this->isProfileLocked($profileId)) {
            return false;
        }

        // Créer un nouveau verrou
        $lock = new ProfileLock([
            'profile_id' => $profileId,
            'moderator_id' => $moderatorId,
            'locked_at' => now(),
            'expires_at' => now()->addSeconds($duration),
            'lock_type' => $moderatorId ? 'assignment' : 'system'
        ]);

        $lock->save();

        // Émettre un événement pour notifier les modérateurs
        event(new ProfileLockStatusChanged($profileId, 'locked', $moderatorId, $duration));

        Log::info("Profil verrouillé", [
            'profile_id' => $profileId,
            'moderator_id' => $moderatorId,
            'duration' => $duration
        ]);

        return true;
    }

    /**
     * Déverrouiller un profil
     */
    public function unlockProfile($profileId)
    {
        $lock = ProfileLock::where('profile_id', $profileId)
            ->whereNull('deleted_at')
            ->first();

        if (!$lock) {
            return false;
        }

        // Soft delete pour conserver l'historique
        $lock->delete();

        // Émettre un événement pour notifier les modérateurs
        event(new ProfileLockStatusChanged($profileId, 'unlocked'));

        Log::info("Profil déverrouillé", [
            'profile_id' => $profileId
        ]);

        return true;
    }

    /**
     * Vérifier si un profil est verrouillé
     */
    public function isProfileLocked($profileId)
    {
        $this->cleanExpiredLocks();

        return ProfileLock::where('profile_id', $profileId)
            ->whereNull('deleted_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Verrouiller un client
     */
    public function lockClient($clientId, $profileId, $moderatorId, $duration = 30)
    {
        // Vérifier si le client est déjà verrouillé
        if ($this->isClientLocked($clientId, $profileId)) {
            return false;
        }

        // Créer un nouveau verrou
        $lock = new ClientLock([
            'client_id' => $clientId,
            'profile_id' => $profileId,
            'moderator_id' => $moderatorId,
            'locked_at' => now(),
            'expires_at' => now()->addSeconds($duration),
            'lock_reason' => 'conversation'
        ]);

        $lock->save();

        Log::info("Client verrouillé", [
            'client_id' => $clientId,
            'profile_id' => $profileId,
            'moderator_id' => $moderatorId,
            'duration' => $duration
        ]);

        return true;
    }

    /**
     * Déverrouiller un client
     */
    public function unlockClient($clientId, $profileId = null)
    {
        $query = ClientLock::where('client_id', $clientId)
            ->whereNull('deleted_at');

        if ($profileId) {
            $query->where('profile_id', $profileId);
        }

        $locks = $query->get();

        if ($locks->isEmpty()) {
            return false;
        }

        foreach ($locks as $lock) {
            // Soft delete pour conserver l'historique
            $lock->delete();
        }

        Log::info("Client déverrouillé", [
            'client_id' => $clientId,
            'profile_id' => $profileId
        ]);

        return true;
    }

    /**
     * Vérifier si un client est verrouillé
     */
    public function isClientLocked($clientId, $profileId = null)
    {
        $this->cleanExpiredLocks();

        $query = ClientLock::where('client_id', $clientId)
            ->whereNull('deleted_at')
            ->where('expires_at', '>', now());

        if ($profileId) {
            $query->where('profile_id', $profileId);
        }

        return $query->exists();
    }

    /**
     * Nettoyer les verrous expirés
     */
    public function cleanExpiredLocks()
    {
        // Nettoyer les verrous de profil expirés
        $expiredProfileLocks = ProfileLock::whereNull('deleted_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expiredProfileLocks as $lock) {
            $lock->delete();

            // Émettre un événement pour notifier les modérateurs
            event(new ProfileLockStatusChanged($lock->profile_id, 'unlocked'));
        }

        // Nettoyer les verrous de client expirés
        $expiredClientLocks = ClientLock::whereNull('deleted_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expiredClientLocks as $lock) {
            $lock->delete();
        }

        return [
            'profiles_cleaned' => count($expiredProfileLocks),
            'clients_cleaned' => count($expiredClientLocks)
        ];
    }

    /**
     * Obtenir des informations sur un verrou
     */
    public function getLockInfo($resource, $type = 'profile')
    {
        if ($type === 'profile') {
            $lock = ProfileLock::where('profile_id', $resource)
                ->whereNull('deleted_at')
                ->where('expires_at', '>', now())
                ->first();
        } else {
            $lock = ClientLock::where('client_id', $resource)
                ->whereNull('deleted_at')
                ->where('expires_at', '>', now())
                ->first();
        }

        if (!$lock) {
            return null;
        }

        return [
            'id' => $lock->id,
            'resource_id' => $type === 'profile' ? $lock->profile_id : $lock->client_id,
            'moderator_id' => $lock->moderator_id,
            'locked_at' => $lock->locked_at,
            'expires_at' => $lock->expires_at,
            'type' => $type === 'profile' ? $lock->lock_type : $lock->lock_reason,
            'time_remaining' => $lock->expires_at->diffInSeconds(now())
        ];
    }

    /**
     * Obtenir tous les profils verrouillés
     */
    public function getLockedProfileIds()
    {
        $this->cleanExpiredLocks();

        return ProfileLock::whereNull('deleted_at')
            ->where('expires_at', '>', now())
            ->pluck('profile_id')
            ->toArray();
    }

    /**
     * Obtenir tous les clients verrouillés
     */
    public function getAllLockedClients()
    {
        $this->cleanExpiredLocks();

        $locks = ClientLock::whereNull('deleted_at')
            ->where('expires_at', '>', now())
            ->get();

        $lockedClients = [];

        foreach ($locks as $lock) {
            $lockedClients[$lock->client_id] = [
                'profile_id' => $lock->profile_id,
                'moderator_id' => $lock->moderator_id,
                'locked_at' => $lock->locked_at,
                'expires_at' => $lock->expires_at,
                'reason' => $lock->lock_reason
            ];
        }

        return $lockedClients;
    }
}
