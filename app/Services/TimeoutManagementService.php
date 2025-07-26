<?php

namespace App\Services;

use App\Models\ModeratorProfileAssignment;
use App\Events\ModeratorInactivityDetected;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TimeoutManagementService
{
    protected $inactivityThreshold = 60; // Secondes avant inactivité
    protected $warningThreshold = 30; // Secondes avant avertissement (calculé avant expiration)
    protected $activeTimersKey = 'active_inactivity_timers';

    /**
     * Démarre un timer d'inactivité pour une assignation modérateur-profil
     */
    public function startInactivityTimer($moderatorId, $profileId, $clientId = null)
    {
        $key = $this->getTimerKey($moderatorId, $profileId);
        $expiresAt = now()->addSeconds($this->inactivityThreshold);

        $timerData = [
            'moderator_id' => $moderatorId,
            'profile_id' => $profileId,
            'client_id' => $clientId,
            'started_at' => now(),
            'expires_at' => $expiresAt,
            'warning_sent' => false
        ];

        // Stocker le timer dans le cache
        Cache::put($key, $timerData, $expiresAt->addMinutes(1));

        // Ajouter la clé à la liste des timers actifs
        $this->addToActiveTimers($key);

        Log::info("Timer d'inactivité démarré", [
            'moderator_id' => $moderatorId,
            'profile_id' => $profileId,
            'expires_at' => $expiresAt->toDateTimeString()
        ]);

        return $timerData;
    }

    /**
     * Annule un timer d'inactivité
     */
    public function cancelTimer($moderatorId, $profileId)
    {
        $key = $this->getTimerKey($moderatorId, $profileId);

        if (Cache::has($key)) {
            Cache::forget($key);
            $this->removeFromActiveTimers($key);

            Log::info("Timer d'inactivité annulé", [
                'moderator_id' => $moderatorId,
                'profile_id' => $profileId
            ]);

            return true;
        }

        return false;
    }

    /**
     * Réinitialise un timer d'inactivité
     */
    public function resetTimer($moderatorId, $profileId, $clientId = null)
    {
        $lockKey = "timer_reset_lock:{$moderatorId}:{$profileId}";

        return Cache::lock($lockKey, 5)->get(function () use ($moderatorId, $profileId, $clientId) {
            $this->cancelTimer($moderatorId, $profileId);
            return $this->startInactivityTimer($moderatorId, $profileId, $clientId);
        });
    }

    /**
     * Prolonge un timer d'inactivité
     */
    public function extendTimeout($moderatorId, $profileId, $minutes = 2)
    {
        $key = $this->getTimerKey($moderatorId, $profileId);

        if (Cache::has($key)) {
            $timerData = Cache::get($key);

            $expiresAt = Carbon::parse($timerData['expires_at'])->addMinutes($minutes);
            $timerData['expires_at'] = $expiresAt;
            $timerData['warning_sent'] = false;

            Cache::put($key, $timerData, $expiresAt->addMinutes(1));

            Log::info("Timer d'inactivité prolongé", [
                'moderator_id' => $moderatorId,
                'profile_id' => $profileId,
                'new_expires_at' => $expiresAt->toDateTimeString()
            ]);

            return true;
        }

        return false;
    }

    /**
     * Accuse réception d'un timer d'inactivité
     */
    public function acknowledgeTimeout($moderatorId, $profileId)
    {
        $key = $this->getTimerKey($moderatorId, $profileId);

        if (Cache::has($key)) {
            $timerData = Cache::get($key);
            $timerData['warning_acknowledged'] = true;

            Cache::put($key, $timerData, Carbon::parse($timerData['expires_at'])->addMinutes(1));

            Log::info("Timer d'inactivité reconnu", [
                'moderator_id' => $moderatorId,
                'profile_id' => $profileId
            ]);

            return true;
        }

        return false;
    }

    /**
     * Vérifie les timers d'inactivité et envoie des avertissements si nécessaire
     * VERSION CORRIGÉE
     */
    public function checkTimers()
    {
        Log::info("🚀 [DEBUG] checkTimers() DÉMARRÉ - " . now()->toDateTimeString());

        $activeKeys = $this->getActiveTimers();
        $processedCount = 0;
        $expiredCount = 0;
        $warningsCount = 0;
        $now = now();

        Log::debug("[TimeoutManagementService] Début de checkTimers", [
            'active_timers_count' => count($activeKeys),
            'current_time' => $now->toDateTimeString()
        ]);

        foreach ($activeKeys as $key) {
            $timerData = Cache::get($key);

            if (!$timerData) {
                Log::info("🔍 [DEBUG] Timer data manquant pour key: " . $key);
                $this->removeFromActiveTimers($key);
                continue;
            }

            $expiresAt = Carbon::parse($timerData['expires_at']);

            // CORRECTION MAJEURE : Calcul correct de l'expiration
            $isExpired = $now->isAfter($expiresAt);
            $remainingSeconds = $isExpired ? 0 : $expiresAt->diffInSeconds($now);

            Log::debug("[TimeoutManagementService] Vérification timer", [
                'key' => $key,
                'moderator_id' => $timerData['moderator_id'],
                'profile_id' => $timerData['profile_id'],
                'expires_at' => $expiresAt->toDateTimeString(),
                'current_time' => $now->toDateTimeString(),
                'remaining_seconds' => $remainingSeconds,
                'is_expired' => $isExpired ? 'OUI' : 'NON',
                'calculation_method' => 'now->isAfter(expires_at)'
            ]);

            // Si le timer a expiré
            if ($isExpired) {
                Log::info("🚨 [DEBUG] TIMER EXPIRÉ - Appel de handleTimeoutExpiration");
                $this->handleTimeoutExpiration($timerData);
                $expiredCount++;
                continue;
            }

            // CORRECTION : Avertissement basé sur le temps restant avant expiration
            if (!$timerData['warning_sent'] && $remainingSeconds <= $this->warningThreshold) {
                Log::info("⚠️ [TimeoutManagementService] Envoi d'avertissement", [
                    'moderator_id' => $timerData['moderator_id'],
                    'profile_id' => $timerData['profile_id'],
                    'remaining_seconds' => $remainingSeconds
                ]);

                $this->sendWarning($timerData, $remainingSeconds);

                // Marquer l'avertissement comme envoyé
                $timerData['warning_sent'] = true;
                Cache::put($key, $timerData, $expiresAt->addMinutes(1));
                $warningsCount++;
            }

            $processedCount++;
        }

        Log::info("✅ [TimeoutManagementService] Vérification des timers terminée", [
            'processed' => $processedCount,
            'expired' => $expiredCount,
            'warnings_sent' => $warningsCount
        ]);

        return [
            'processed' => $processedCount,
            'expired' => $expiredCount,
            'warnings_sent' => $warningsCount
        ];
    }

    /**
     * Gère l'expiration d'un timer d'inactivité
     */
    public function handleTimeoutExpiration($timerData)
    {
        Log::info("🎯 [DEBUG] handleTimeoutExpiration() APPELÉ !");
        $moderatorId = $timerData['moderator_id'];
        $profileId = $timerData['profile_id'];
        $clientId = $timerData['client_id'] ?? null;

        Log::info("⏱️ [TimeoutManagementService] DÉBUT du traitement de l'expiration du timer", [
            'moderator_id' => $moderatorId,
            'profile_id' => $profileId,
            'client_id' => $clientId,
            'timestamp' => now()->toDateTimeString(),
            'timer_started_at' => $timerData['started_at'] ?? 'unknown',
            'timer_expires_at' => $timerData['expires_at'] ?? 'unknown'
        ]);

        try {
            // Vérifier que l'assignation existe encore
            $assignment = ModeratorProfileAssignment::where('user_id', $moderatorId)
                ->where('profile_id', $profileId)
                ->where('is_active', true)
                ->first();

            if (!$assignment) {
                Log::warning("⚠️ [TimeoutManagementService] Assignation non trouvée ou déjà inactive", [
                    'moderator_id' => $moderatorId,
                    'profile_id' => $profileId
                ]);

                $this->cancelTimer($moderatorId, $profileId);
                return false;
            }

            // Création et déclenchement de l'événement
            $event = new ModeratorInactivityDetected($moderatorId, $profileId, $clientId);

            Log::info("📣 [TimeoutManagementService] DÉCLENCHEMENT de l'événement", [
                'event_class' => get_class($event),
                'moderator_id' => $moderatorId,
                'profile_id' => $profileId,
                'client_id' => $clientId
            ]);

            event($event);

            Log::info("✅ [TimeoutManagementService] Événement déclenché avec SUCCÈS");

            // Supprimer le timer après traitement réussi
            $this->cancelTimer($moderatorId, $profileId);

            return true;
        } catch (\Throwable $e) {
            Log::error("❌ [TimeoutManagementService] ERREUR CRITIQUE lors du traitement", [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'moderator_id' => $moderatorId,
                'profile_id' => $profileId,
                'client_id' => $clientId
            ]);

            throw $e;
        }
    }

    /**
     * Envoie un avertissement d'inactivité
     */
    protected function sendWarning($timerData, $remainingSeconds)
    {
        $moderatorId = $timerData['moderator_id'];
        $profileId = $timerData['profile_id'];

        Log::info("⚠️ [TimeoutManagementService] Envoi d'un avertissement d'inactivité", [
            'moderator_id' => $moderatorId,
            'profile_id' => $profileId,
            'remaining_seconds' => $remainingSeconds
        ]);

        try {
            broadcast(new \App\Events\ModeratorInactivityWarning(
                $moderatorId,
                $profileId,
                $remainingSeconds
            ));

            Log::info("✅ [TimeoutManagementService] Avertissement envoyé avec succès");
        } catch (\Throwable $e) {
            Log::error("❌ [TimeoutManagementService] Erreur lors de l'envoi d'avertissement", [
                'error' => $e->getMessage(),
                'moderator_id' => $moderatorId,
                'profile_id' => $profileId
            ]);
        }

        return true;
    }

    /**
     * Méthode utilitaire pour débugger les timers actifs
     */
    public function debugActiveTimers()
    {
        $activeKeys = $this->getActiveTimers();
        $now = now();

        Log::info("🔍 [DEBUG] Liste des timers actifs", [
            'count' => count($activeKeys),
            'keys' => $activeKeys
        ]);

        foreach ($activeKeys as $key) {
            $timerData = Cache::get($key);
            if ($timerData) {
                $expiresAt = Carbon::parse($timerData['expires_at']);
                $isExpired = $now->isAfter($expiresAt);
                $remainingSeconds = $isExpired ? 0 : $expiresAt->diffInSeconds($now);

                Log::info("🔍 [DEBUG] Timer détails", [
                    'key' => $key,
                    'moderator_id' => $timerData['moderator_id'],
                    'profile_id' => $timerData['profile_id'],
                    'expires_at' => $expiresAt->toDateTimeString(),
                    'current_time' => $now->toDateTimeString(),
                    'remaining_seconds' => $remainingSeconds,
                    'is_expired' => $isExpired ? 'OUI' : 'NON'
                ]);
            } else {
                Log::warning("🔍 [DEBUG] Timer key sans données: " . $key);
            }
        }
    }

    /**
     * Retourne tous les timers actifs avec leurs données
     */
    public function getActiveTimersData()
    {
        $activeKeys = $this->getActiveTimers();
        $timers = [];

        foreach ($activeKeys as $key) {
            $timerData = Cache::get($key);
            if ($timerData) {
                $timers[] = $timerData;
            } else {
                $this->removeFromActiveTimers($key);
            }
        }

        return $timers;
    }

    /**
     * Nettoie les timers expirés ou orphelins
     */
    public function cleanupExpiredTimers()
    {
        $activeKeys = $this->getActiveTimers();
        $cleaned = 0;
        $now = now();

        foreach ($activeKeys as $key) {
            $timerData = Cache::get($key);

            if (!$timerData || $now->isAfter(Carbon::parse($timerData['expires_at']))) {
                Cache::forget($key);
                $this->removeFromActiveTimers($key);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            Log::info("🧹 [TimeoutManagementService] Nettoyage des timers expirés", [
                'cleaned_count' => $cleaned
            ]);
        }

        return $cleaned;
    }

    /**
     * Retourne des statistiques sur les timers - VERSION CORRIGÉE
     */
    public function getTimerStats()
    {
        $activeKeys = $this->getActiveTimers();
        $now = now();
        $stats = [
            'total_active' => count($activeKeys),
            'warning_zone' => 0,
            'expired' => 0,
            'by_moderator' => []
        ];

        foreach ($activeKeys as $key) {
            $timerData = Cache::get($key);
            if (!$timerData) continue;

            $moderatorId = $timerData['moderator_id'];
            $expiresAt = Carbon::parse($timerData['expires_at']);

            // Calcul correct
            $isExpired = $now->isAfter($expiresAt);
            $remainingSeconds = $isExpired ? 0 : $expiresAt->diffInSeconds($now);

            // Compter par modérateur
            if (!isset($stats['by_moderator'][$moderatorId])) {
                $stats['by_moderator'][$moderatorId] = 0;
            }
            $stats['by_moderator'][$moderatorId]++;

            // Compter les timers en zone d'avertissement ou expirés
            if ($isExpired) {
                $stats['expired']++;
            } elseif ($remainingSeconds <= $this->warningThreshold) {
                $stats['warning_zone']++;
            }
        }

        return $stats;
    }

    // Méthodes utilitaires inchangées
    protected function getTimerKey($moderatorId, $profileId)
    {
        return "inactivity_timer:{$moderatorId}:{$profileId}";
    }

    protected function addToActiveTimers($key)
    {
        $activeTimers = Cache::get($this->activeTimersKey, []);

        if (!in_array($key, $activeTimers)) {
            $activeTimers[] = $key;
            Cache::put($this->activeTimersKey, $activeTimers, now()->addHours(2));
        }
    }

    protected function removeFromActiveTimers($key)
    {
        $activeTimers = Cache::get($this->activeTimersKey, []);
        $activeTimers = array_filter($activeTimers, function ($activeKey) use ($key) {
            return $activeKey !== $key;
        });

        Cache::put($this->activeTimersKey, array_values($activeTimers), now()->addHours(2));
    }

    protected function getActiveTimers()
    {
        return Cache::get($this->activeTimersKey, []);
    }
}
