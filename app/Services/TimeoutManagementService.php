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
    protected $warningThreshold = 30; // Secondes avant avertissement
    protected $activeTimersKey = 'active_inactivity_timers'; // Clé pour maintenir la liste des timers actifs

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

        // Utiliser un verrou pour éviter les conflits de concurrence
        return Cache::lock($lockKey, 5)->get(function () use ($moderatorId, $profileId, $clientId) {
            // Annuler l'ancien timer
            $this->cancelTimer($moderatorId, $profileId);

            // Démarrer un nouveau timer
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

            // Prolonger le délai d'expiration
            $expiresAt = Carbon::parse($timerData['expires_at'])->addMinutes($minutes);
            $timerData['expires_at'] = $expiresAt;
            $timerData['warning_sent'] = false;

            // Mettre à jour le timer dans le cache
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

            // Marquer l'avertissement comme reconnu
            $timerData['warning_acknowledged'] = true;

            // Mettre à jour le timer dans le cache
            Cache::put($key, $timerData, Carbon::parse($timerData['expires_at'])->addMinutes(1));

            Log::info("Timer d'inactivité reconnu", [
                'moderator_id' => $moderatorId,
                'profile_id' => $profileId
            ]);

            return true;
        }

        return false;
    }

    // Ajouter cette méthode temporaire pour le debug
    public function debugActiveTimers()
    {
        $activeKeys = $this->getActiveTimers();

        Log::info("🔍 [DEBUG] Liste des timers actifs", [
            'count' => count($activeKeys),
            'keys' => $activeKeys
        ]);

        foreach ($activeKeys as $key) {
            $timerData = Cache::get($key);
            if ($timerData) {
                $expiresAt = Carbon::parse($timerData['expires_at']);
                $now = now();
                $remainingSeconds = $expiresAt->diffInSeconds($now, false);

                Log::info("🔍 [DEBUG] Timer détails", [
                    'key' => $key,
                    'moderator_id' => $timerData['moderator_id'],
                    'profile_id' => $timerData['profile_id'],
                    'expires_at' => $expiresAt->toDateTimeString(),
                    'current_time' => $now->toDateTimeString(),
                    'remaining_seconds' => $remainingSeconds,
                    'is_expired' => $remainingSeconds < 0 ? 'OUI' : 'NON'
                ]);
            } else {
                Log::warning("🔍 [DEBUG] Timer key sans données: " . $key);
            }
        }
    }
    /**
     * Vérifie les timers d'inactivité et envoie des avertissements si nécessaire
     */
    public function checkTimers()
    {
        Log::info("🚀 [DEBUG] checkTimers() DÉMARRÉ - " . now()->toDateTimeString());

        $activeKeys = $this->getActiveTimers();
        Log::info("🚀 [DEBUG] checkTimers() DÉMARRÉ - " . now()->toDateTimeString());

        $processedCount = 0;
        $expiredCount = 0;
        $warningsCount = 0;

        Log::debug("[TimeoutManagementService] Début de checkTimers", [
            'active_timers_count' => count($activeKeys),
            'current_time' => now()->toDateTimeString()
        ]);

        foreach ($activeKeys as $key) {
            $timerData = Cache::get($key);

            if (!$timerData) {
                Log::info("🔍 [DEBUG] Timer data manquant pour key: " . $key);
                $this->removeFromActiveTimers($key);
                continue;
            }

            $now = now();
            $expiresAt = Carbon::parse($timerData['expires_at']);
            // Temps restant = temps d'expiration - temps actuel
            $remainingSeconds = $expiresAt->diffInSeconds($now, false);

            // Si le résultat est négatif, le timer a expiré
            $isExpired = $remainingSeconds < 0;
            $remainingSeconds = abs($remainingSeconds); // Valeur absolue pour les logs

            Log::debug("[TimeoutManagementService] Vérification timer", [
                'key' => $key,
                'moderator_id' => $timerData['moderator_id'],
                'profile_id' => $timerData['profile_id'],
                'expires_at' => $expiresAt->toDateTimeString(),
                'current_time' => $now->toDateTimeString(),
                'remaining_seconds' => $remainingSeconds,
                'expires_at_timestamp' => $expiresAt->timestamp,
                'current_time_timestamp' => $now->timestamp,
                'is_expired' => $isExpired ? 'OUI' : 'NON',
                'calculation_method' => 'expires_at - now',
                'should_call_expiration' => $isExpired ? 'OUI' : 'NON'

            ]);

            // Si le timer a expiré
            if ($isExpired) {
                Log::info("🚨 [DEBUG] TIMER EXPIRÉ - Appel de handleTimeoutExpiration");
                $this->handleTimeoutExpiration($timerData);
                $expiredCount++;
                Log::info("🚨 [DEBUG] handleTimeoutExpiration terminé");
                continue;
            }

            // Si l'avertissement n'a pas été envoyé et qu'on est dans la période d'avertissement
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
            Log::info("🎯 [DEBUG] Vérification de l'assignation...");
            $assignment = ModeratorProfileAssignment::where('user_id', $moderatorId)
                ->where('profile_id', $profileId)
                ->where('is_active', true)
                ->first();

            Log::info("🎯 [DEBUG] Résultat assignation", [
                'found' => $assignment ? 'OUI' : 'NON',
                'assignment_id' => $assignment->id ?? 'N/A'
            ]);

            if (!$assignment) {
                Log::warning("⚠️ [TimeoutManagementService] Assignation non trouvée ou déjà inactive", [
                    'moderator_id' => $moderatorId,
                    'profile_id' => $profileId
                ]);

                // Nettoyer le timer
                $this->cancelTimer($moderatorId, $profileId);
                return false;
            }

            Log::debug("📣 [TimeoutManagementService] Création de l'événement ModeratorInactivityDetected", [
                'moderator_id' => $moderatorId,
                'profile_id' => $profileId,
                'client_id' => $clientId,
                'assignment_id' => $assignment->id
            ]);

            // Création de l'événement
            Log::info("🎯 [DEBUG] Création de l'événement...");
            $event = new ModeratorInactivityDetected($moderatorId, $profileId, $clientId);
            Log::info("🎯 [DEBUG] Événement créé: " . get_class($event));


            Log::info("📣 [TimeoutManagementService] DÉCLENCHEMENT de l'événement", [
                'event_class' => get_class($event),
                'moderator_id' => $moderatorId,
                'profile_id' => $profileId,
                'client_id' => $clientId
            ]);

            // Déclenchement de l'événement
            // Déclenchement
            Log::info("🎯 [DEBUG] DÉCLENCHEMENT de l'événement...");
            event($event);
            Log::info("🎯 [DEBUG] Événement déclenché - SUCCESS !");

            Log::info("✅ [TimeoutManagementService] Événement déclenché avec SUCCÈS", [
                'moderator_id' => $moderatorId,
                'profile_id' => $profileId
            ]);

            // Supprimer le timer après traitement réussi
            Log::info("🎯 [DEBUG] Nettoyage du timer...");
            $this->cancelTimer($moderatorId, $profileId);
            Log::info("🎯 [DEBUG] Timer nettoyé - SUCCESS !");

            Log::info("🎯 [DEBUG] handleTimeoutExpiration() TERMINÉ avec succès");
            return true;
        } catch (\Throwable $e) {
            Log::error("❌ [TimeoutManagementService] ERREUR CRITIQUE lors du traitement", [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'moderator_id' => $moderatorId,
                'profile_id' => $profileId,
                'client_id' => $clientId
            ]);

            // Ne pas supprimer le timer en cas d'erreur pour pouvoir réessayer
            throw $e;
        }

        return true;
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
            // Diffuser l'événement d'avertissement
            broadcast(new \App\Events\ModeratorInactivityWarning(
                $moderatorId,
                $profileId,
                $remainingSeconds
            ));

            Log::info("✅ [TimeoutManagementService] Avertissement envoyé avec succès", [
                'moderator_id' => $moderatorId,
                'profile_id' => $profileId
            ]);
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
                // Nettoyer les clés orphelines
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

        foreach ($activeKeys as $key) {
            $timerData = Cache::get($key);

            if (!$timerData || Carbon::parse($timerData['expires_at'])->isPast()) {
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
     * Génère la clé de cache pour un timer
     */
    protected function getTimerKey($moderatorId, $profileId)
    {
        return "inactivity_timer:{$moderatorId}:{$profileId}";
    }

    /**
     * Ajoute une clé à la liste des timers actifs
     */
    protected function addToActiveTimers($key)
    {
        $activeTimers = Cache::get($this->activeTimersKey, []);

        if (!in_array($key, $activeTimers)) {
            $activeTimers[] = $key;
            Cache::put($this->activeTimersKey, $activeTimers, now()->addHours(2));
        }
    }

    /**
     * Supprime une clé de la liste des timers actifs
     */
    protected function removeFromActiveTimers($key)
    {
        $activeTimers = Cache::get($this->activeTimersKey, []);
        $activeTimers = array_filter($activeTimers, function ($activeKey) use ($key) {
            return $activeKey !== $key;
        });

        Cache::put($this->activeTimersKey, array_values($activeTimers), now()->addHours(2));
    }

    /**
     * Retourne la liste des clés des timers actifs
     */
    protected function getActiveTimers()
    {
        return Cache::get($this->activeTimersKey, []);
    }

    /**
     * Retourne des statistiques sur les timers
     */
    public function getTimerStats()
    {
        $activeKeys = $this->getActiveTimers();
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
            $now = now();

            // Calcul correct du temps restant
            $remainingSeconds = $expiresAt->diffInSeconds($now, false);
            $isExpired = $remainingSeconds < 0;
            $remainingSeconds = abs($remainingSeconds);

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
}
