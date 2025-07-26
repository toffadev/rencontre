<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\ProfileLock;
use App\Models\ClientLock;
use App\Events\ProfileLockStatusChanged;

/**
 * Service pour gérer les verrous sur les profils et clients.
 * Version corrigée pour éviter les race conditions et les assignations multiples.
 */
class ProfileLockService
{
    const CACHE_TTL = 60; // Cache TTL en secondes
    const LOCK_CLEANUP_INTERVAL = 30; // Nettoyage toutes les 30 secondes

    /**
     * Verrouiller un profil de manière atomique
     */
    public function lockProfile($profileId, $moderatorId = null, $duration = 30)
    {
        // Utiliser une transaction avec verrous exclusifs pour éviter les race conditions
        return DB::transaction(function () use ($profileId, $moderatorId, $duration) {

            // Verrouillage exclusif de la ligne pour éviter les accès concurrents
            $existingLock = ProfileLock::where('profile_id', $profileId)
                ->whereNull('deleted_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate() // ✅ Verrou exclusif
                ->first();

            if ($existingLock) {
                Log::warning("Tentative de verrouillage d'un profil déjà verrouillé", [
                    'profile_id' => $profileId,
                    'moderator_id' => $moderatorId,
                    'existing_moderator' => $existingLock->moderator_id
                ]);
                return false;
            }

            // Créer le nouveau verrou de manière atomique
            try {
                $lock = ProfileLock::create([
                    'profile_id' => $profileId,
                    'moderator_id' => $moderatorId,
                    'locked_at' => now(),
                    'expires_at' => now()->addSeconds($duration),
                    'lock_type' => $moderatorId ? 'assignment' : 'system'
                ]);

                // Cache pour améliorer les performances des vérifications fréquentes
                $this->cacheProfileLock($profileId, $lock->expires_at);

                // Émettre un événement pour notifier les modérateurs
                event(new ProfileLockStatusChanged($profileId, 'locked', $moderatorId, $duration));

                Log::info("Profil verrouillé avec succès", [
                    'profile_id' => $profileId,
                    'moderator_id' => $moderatorId,
                    'duration' => $duration,
                    'lock_id' => $lock->id
                ]);

                return true;
            } catch (\Exception $e) {
                Log::error("Erreur lors du verrouillage du profil", [
                    'profile_id' => $profileId,
                    'moderator_id' => $moderatorId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Déverrouiller un profil de manière atomique
     */
    public function unlockProfile($profileId)
    {
        return DB::transaction(function () use ($profileId) {
            $lock = ProfileLock::where('profile_id', $profileId)
                ->whereNull('deleted_at')
                ->lockForUpdate() // ✅ Verrou exclusif
                ->first();

            if (!$lock) {
                return false;
            }

            // Soft delete pour conserver l'historique
            $lock->delete();

            // Supprimer du cache
            $this->removeCachedProfileLock($profileId);

            // Émettre un événement pour notifier les modérateurs
            event(new ProfileLockStatusChanged($profileId, 'unlocked'));

            Log::info("Profil déverrouillé", [
                'profile_id' => $profileId,
                'lock_id' => $lock->id
            ]);

            return true;
        });
    }

    /**
     * Vérifier si un profil est verrouillé avec cache optimisé
     */
    public function isProfileLocked($profileId)
    {
        // Vérifier d'abord le cache pour éviter les requêtes fréquentes
        $cacheKey = "profile_lock_{$profileId}";
        $cachedExpiry = Cache::get($cacheKey);

        if ($cachedExpiry && Carbon::parse($cachedExpiry)->isFuture()) {
            return true;
        }

        if ($cachedExpiry && Carbon::parse($cachedExpiry)->isPast()) {
            Cache::forget($cacheKey);
            return false;
        }

        // Vérification en base si pas en cache
        $isLocked = ProfileLock::where('profile_id', $profileId)
            ->whereNull('deleted_at')
            ->where('expires_at', '>', now())
            ->exists();

        return $isLocked;
    }

    /**
     * Verrouiller un client de manière atomique
     */
    public function lockClient($clientId, $profileId, $moderatorId, $duration = 30)
    {
        return DB::transaction(function () use ($clientId, $profileId, $moderatorId, $duration) {

            // Vérifier les verrous existants avec verrouillage exclusif
            $existingLock = ClientLock::where('client_id', $clientId)
                ->where('profile_id', $profileId)
                ->whereNull('deleted_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($existingLock) {
                return false;
            }

            try {
                $lock = ClientLock::create([
                    'client_id' => $clientId,
                    'profile_id' => $profileId,
                    'moderator_id' => $moderatorId,
                    'locked_at' => now(),
                    'expires_at' => now()->addSeconds($duration),
                    'lock_reason' => 'conversation'
                ]);

                Log::info("Client verrouillé", [
                    'client_id' => $clientId,
                    'profile_id' => $profileId,
                    'moderator_id' => $moderatorId,
                    'duration' => $duration,
                    'lock_id' => $lock->id
                ]);

                return true;
            } catch (\Exception $e) {
                Log::error("Erreur lors du verrouillage du client", [
                    'client_id' => $clientId,
                    'profile_id' => $profileId,
                    'moderator_id' => $moderatorId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Déverrouiller un client de manière atomique
     */
    public function unlockClient($clientId, $profileId = null)
    {
        return DB::transaction(function () use ($clientId, $profileId) {
            $query = ClientLock::where('client_id', $clientId)
                ->whereNull('deleted_at')
                ->lockForUpdate(); // ✅ Verrou exclusif

            if ($profileId) {
                $query->where('profile_id', $profileId);
            }

            $locks = $query->get();

            if ($locks->isEmpty()) {
                return false;
            }

            foreach ($locks as $lock) {
                $lock->delete();
            }

            Log::info("Client déverrouillé", [
                'client_id' => $clientId,
                'profile_id' => $profileId,
                'locks_removed' => count($locks)
            ]);

            return true;
        });
    }

    /**
     * Vérifier si un client est verrouillé
     */
    public function isClientLocked($clientId, $profileId = null)
    {
        $query = ClientLock::where('client_id', $clientId)
            ->whereNull('deleted_at')
            ->where('expires_at', '>', now());

        if ($profileId) {
            $query->where('profile_id', $profileId);
        }

        return $query->exists();
    }

    /**
     * Nettoyer les verrous expirés de manière contrôlée
     * ✅ Optimisation: Appel moins fréquent et plus intelligent
     */
    public function cleanExpiredLocks()
    {
        // Utiliser le cache pour éviter un nettoyage trop fréquent
        $lastCleanup = Cache::get('last_lock_cleanup', 0);

        if (time() - $lastCleanup < self::LOCK_CLEANUP_INTERVAL) {
            return ['profiles_cleaned' => 0, 'clients_cleaned' => 0];
        }

        return DB::transaction(function () {
            $profilesCleaned = 0;
            $clientsCleaned = 0;

            // Nettoyer les verrous de profil expirés
            $expiredProfileLocks = ProfileLock::whereNull('deleted_at')
                ->where('expires_at', '<=', now())
                ->lockForUpdate()
                ->get();

            foreach ($expiredProfileLocks as $lock) {
                $lock->delete();
                $this->removeCachedProfileLock($lock->profile_id);

                // Émettre un événement pour notifier les modérateurs
                event(new ProfileLockStatusChanged($lock->profile_id, 'unlocked'));
                $profilesCleaned++;
            }

            // Nettoyer les verrous de client expirés
            $expiredClientLocks = ClientLock::whereNull('deleted_at')
                ->where('expires_at', '<=', now())
                ->lockForUpdate()
                ->get();

            foreach ($expiredClientLocks as $lock) {
                $lock->delete();
                $clientsCleaned++;
            }

            // Mettre à jour le timestamp du dernier nettoyage
            Cache::put('last_lock_cleanup', time(), self::CACHE_TTL);

            if ($profilesCleaned > 0 || $clientsCleaned > 0) {
                Log::info("Nettoyage des verrous expirés", [
                    'profiles_cleaned' => $profilesCleaned,
                    'clients_cleaned' => $clientsCleaned
                ]);
            }

            return [
                'profiles_cleaned' => $profilesCleaned,
                'clients_cleaned' => $clientsCleaned
            ];
        });
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
            'time_remaining' => max(0, $lock->expires_at->diffInSeconds(now()))
        ];
    }

    /**
     * Obtenir tous les profils verrouillés
     */
    public function getLockedProfileIds()
    {
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

    /**
     * ✅ NOUVEAU: Forcer le déverrouillage d'un profil (pour les cas d'urgence)
     */
    public function forceUnlockProfile($profileId, $reason = 'forced_unlock')
    {
        return DB::transaction(function () use ($profileId, $reason) {
            $locks = ProfileLock::where('profile_id', $profileId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->get();

            foreach ($locks as $lock) {
                $lock->delete();
            }

            $this->removeCachedProfileLock($profileId);

            Log::warning("Déverrouillage forcé du profil", [
                'profile_id' => $profileId,
                'reason' => $reason,
                'locks_removed' => count($locks)
            ]);

            event(new ProfileLockStatusChanged($profileId, 'force_unlocked'));

            return count($locks) > 0;
        });
    }

    /**
     * Méthodes de gestion du cache
     */
    private function cacheProfileLock($profileId, $expiresAt)
    {
        $cacheKey = "profile_lock_{$profileId}";
        $ttl = max(1, $expiresAt->diffInSeconds(now()));
        Cache::put($cacheKey, $expiresAt->toISOString(), $ttl);
    }

    private function removeCachedProfileLock($profileId)
    {
        Cache::forget("profile_lock_{$profileId}");
    }

    /**
     * ✅ NOUVEAU: Vérifier l'intégrité des verrous
     */
    public function checkLockIntegrity()
    {
        $issues = [];

        // Vérifier les doublons de verrous de profil
        $duplicateProfiles = ProfileLock::select('profile_id')
            ->whereNull('deleted_at')
            ->where('expires_at', '>', now())
            ->groupBy('profile_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('profile_id');

        if ($duplicateProfiles->isNotEmpty()) {
            $issues['duplicate_profile_locks'] = $duplicateProfiles->toArray();
        }

        // Vérifier les doublons de verrous de client
        $duplicateClients = ClientLock::select('client_id', 'profile_id')
            ->whereNull('deleted_at')
            ->where('expires_at', '>', now())
            ->groupBy(['client_id', 'profile_id'])
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicateClients->isNotEmpty()) {
            $issues['duplicate_client_locks'] = $duplicateClients->toArray();
        }

        return $issues;
    }
}
