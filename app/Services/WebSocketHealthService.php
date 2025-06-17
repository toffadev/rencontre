<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Carbon;

class WebSocketHealthService
{
    /**
     * Préfixe pour les clés Redis
     */
    protected $redisPrefix = 'websocket:health:';

    /**
     * Délai en secondes après lequel une connexion est considérée inactive
     */
    protected $inactivityThreshold = 300; // 5 minutes

    /**
     * Enregistre une nouvelle connexion
     *
     * @param string $userId L'ID de l'utilisateur
     * @param string $userType Le type d'utilisateur (client ou modérateur)
     * @param string $connectionId L'ID de la connexion WebSocket
     * @return bool
     */
    public function registerConnection(string $userId, string $userType, string $connectionId): bool
    {
        try {
            $key = $this->redisPrefix . 'connection:' . $connectionId;
            $userKey = $this->redisPrefix . 'user:' . $userType . ':' . $userId;

            // Stocker les informations de connexion
            $data = [
                'user_id' => $userId,
                'user_type' => $userType,
                'connection_id' => $connectionId,
                'connected_at' => Carbon::now()->toDateTimeString(),
                'last_activity' => Carbon::now()->toDateTimeString(),
                'client_ip' => request()->ip(),
                'user_agent' => request()->userAgent()
            ];

            Redis::hmset($key, $data);
            Redis::expire($key, 86400); // Expire après 24 heures

            // Associer cette connexion à l'utilisateur
            Redis::sadd($userKey, $connectionId);
            Redis::expire($userKey, 86400); // Expire après 24 heures

            // Ajouter à la liste globale des connexions actives
            Redis::sadd($this->redisPrefix . 'active_connections', $connectionId);

            // Incrémenter le compteur de connexions par type d'utilisateur
            Redis::incr($this->redisPrefix . 'count:' . $userType);

            // Enregistrer dans les logs
            Log::info("WebSocket connexion enregistrée: {$connectionId} pour {$userType} #{$userId}");

            return true;
        } catch (\Exception $e) {
            Log::error("Erreur lors de l'enregistrement de la connexion WebSocket: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Met à jour l'activité d'une connexion
     *
     * @param string $connectionId L'ID de la connexion WebSocket
     * @return bool
     */
    public function updateActivity(string $connectionId): bool
    {
        try {
            $key = $this->redisPrefix . 'connection:' . $connectionId;

            // Vérifier si la connexion existe
            if (!Redis::exists($key)) {
                return false;
            }

            // Mettre à jour la date de dernière activité
            Redis::hset($key, 'last_activity', Carbon::now()->toDateTimeString());

            return true;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la mise à jour de l'activité WebSocket: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Ferme une connexion
     *
     * @param string $connectionId L'ID de la connexion WebSocket
     * @return bool
     */
    public function closeConnection(string $connectionId): bool
    {
        try {
            $key = $this->redisPrefix . 'connection:' . $connectionId;

            // Récupérer les informations de la connexion
            $connectionData = Redis::hgetall($key);

            if (empty($connectionData)) {
                return false;
            }

            $userId = $connectionData['user_id'] ?? null;
            $userType = $connectionData['user_type'] ?? null;

            if ($userId && $userType) {
                // Supprimer la connexion de la liste des connexions de l'utilisateur
                $userKey = $this->redisPrefix . 'user:' . $userType . ':' . $userId;
                Redis::srem($userKey, $connectionId);

                // Décrémenter le compteur de connexions par type d'utilisateur
                Redis::decr($this->redisPrefix . 'count:' . $userType);
            }

            // Supprimer de la liste globale des connexions actives
            Redis::srem($this->redisPrefix . 'active_connections', $connectionId);

            // Supprimer les données de la connexion
            Redis::del($key);

            // Enregistrer dans les logs
            Log::info("WebSocket connexion fermée: {$connectionId}");

            return true;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la fermeture de la connexion WebSocket: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Nettoie les connexions inactives
     *
     * @return int Nombre de connexions nettoyées
     */
    public function cleanInactiveConnections(): int
    {
        try {
            $cleanedCount = 0;
            $now = Carbon::now();
            $threshold = Carbon::now()->subSeconds($this->inactivityThreshold);

            // Récupérer toutes les connexions actives
            $connections = Redis::smembers($this->redisPrefix . 'active_connections');

            foreach ($connections as $connectionId) {
                $key = $this->redisPrefix . 'connection:' . $connectionId;
                $lastActivity = Redis::hget($key, 'last_activity');

                if ($lastActivity && Carbon::parse($lastActivity)->lt($threshold)) {
                    // La connexion est inactive depuis trop longtemps
                    $this->closeConnection($connectionId);
                    $cleanedCount++;
                }
            }

            if ($cleanedCount > 0) {
                Log::info("WebSocket: {$cleanedCount} connexions inactives nettoyées");
            }

            return $cleanedCount;
        } catch (\Exception $e) {
            Log::error("Erreur lors du nettoyage des connexions inactives: {$e->getMessage()}");
            return 0;
        }
    }

    /**
     * Récupère les statistiques de connexions WebSocket
     *
     * @return array
     */
    public function getStatistics(): array
    {
        try {
            // Nombre total de connexions actives
            $totalActive = Redis::scard($this->redisPrefix . 'active_connections');

            // Nombre de connexions par type d'utilisateur
            $clientCount = Redis::get($this->redisPrefix . 'count:client') ?: 0;
            $moderatorCount = Redis::get($this->redisPrefix . 'count:moderateur') ?: 0;

            // Calcul du temps moyen de connexion
            $totalDuration = 0;
            $connectionCount = 0;
            $connections = Redis::smembers($this->redisPrefix . 'active_connections');

            foreach ($connections as $connectionId) {
                $key = $this->redisPrefix . 'connection:' . $connectionId;
                $connectedAt = Redis::hget($key, 'connected_at');

                if ($connectedAt) {
                    $duration = Carbon::parse($connectedAt)->diffInSeconds(Carbon::now());
                    $totalDuration += $duration;
                    $connectionCount++;
                }
            }

            $averageDuration = $connectionCount > 0 ? $totalDuration / $connectionCount : 0;

            return [
                'total_active_connections' => (int)$totalActive,
                'clients_connected' => (int)$clientCount,
                'moderators_connected' => (int)$moderatorCount,
                'average_connection_time' => round($averageDuration),
                'timestamp' => Carbon::now()->toDateTimeString()
            ];
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des statistiques WebSocket: {$e->getMessage()}");

            return [
                'error' => true,
                'message' => $e->getMessage(),
                'timestamp' => Carbon::now()->toDateTimeString()
            ];
        }
    }

    /**
     * Récupère les connexions actives d'un utilisateur
     *
     * @param string $userId L'ID de l'utilisateur
     * @param string $userType Le type d'utilisateur
     * @return array
     */
    public function getUserConnections(string $userId, string $userType): array
    {
        try {
            $userKey = $this->redisPrefix . 'user:' . $userType . ':' . $userId;
            $connectionIds = Redis::smembers($userKey);
            $connections = [];

            foreach ($connectionIds as $connectionId) {
                $key = $this->redisPrefix . 'connection:' . $connectionId;
                $data = Redis::hgetall($key);

                if (!empty($data)) {
                    $connections[] = $data;
                }
            }

            return $connections;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la récupération des connexions de l'utilisateur: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Vérifie la santé des WebSockets et retourne un rapport
     *
     * @return array
     */
    public function checkHealth(): array
    {
        try {
            // Nettoyer les connexions inactives
            $cleanedCount = $this->cleanInactiveConnections();

            // Récupérer les statistiques
            $stats = $this->getStatistics();

            // Vérifier si le serveur Reverb est en cours d'exécution
            $reverbRunning = $this->isReverbRunning();

            return [
                'status' => $reverbRunning ?
                    'healthy' :
                    'unhealthy',
                'reverb_server' => $reverbRunning ?
                    'running' :
                    'not_running',
                'connections_cleaned' => $cleanedCount,
                'stats' => $stats,
                'timestamp' => Carbon::now()->toDateTimeString()
            ];
        } catch (\Exception $e) {
            Log::error("Erreur lors de la vérification de la santé WebSocket: {$e->getMessage()}");

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'timestamp' => Carbon::now()->toDateTimeString()
            ];
        }
    }

    /**
     * Vérifie si le serveur Reverb est en cours d'exécution
     *
     * @return bool
     */
    protected function isReverbRunning(): bool
    {
        try {
            // Récupérer le port configuré pour Reverb
            $reverbPort = env('REVERB_PORT', 8080);
            $reverbHost = env('REVERB_HOST', '127.0.0.1');

            // Vérifier si le port est ouvert
            $fp = @fsockopen($reverbHost, $reverbPort, $errno, $errstr, 1);
            $isRunning = $fp !== false;

            if ($fp) {
                fclose($fp);
            }

            return $isRunning;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la vérification du serveur Reverb: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Redémarre le serveur Reverb si nécessaire
     *
     * @return bool
     */
    public function restartReverbIfNeeded(): bool
    {
        // Vérifier si le serveur Reverb est en cours d'exécution
        if ($this->isReverbRunning()) {
            return true; // Déjà en cours d'exécution, pas besoin de redémarrer
        }

        try {
            // Tentative de redémarrage du serveur Reverb
            $output = [];
            $returnVar = 0;

            // Exécuter la commande Laravel pour démarrer Reverb
            $command = "cd " . base_path() . " && php artisan reverb:start --fresh > /dev/null 2>&1 &";
            exec($command, $output, $returnVar);

            // Vérifier si la commande a été exécutée avec succès
            if ($returnVar !== 0) {
                Log::error("Échec du redémarrage de Reverb: code de retour {$returnVar}");
                return false;
            }

            // Attendre que le serveur démarre
            sleep(2);

            // Vérifier à nouveau si le serveur est en cours d'exécution
            $isRunning = $this->isReverbRunning();

            if ($isRunning) {
                Log::info("Serveur Reverb redémarré avec succès");
            } else {
                Log::error("Le serveur Reverb n'a pas pu être redémarré");
            }

            return $isRunning;
        } catch (\Exception $e) {
            Log::error("Erreur lors du redémarrage de Reverb: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Envoie un ping à toutes les connexions actives pour vérifier leur état
     *
     * @return array Résultats du ping
     */
    public function pingAllConnections(): array
    {
        $results = [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'details' => []
        ];

        try {
            // Récupérer toutes les connexions actives
            $connections = Redis::smembers($this->redisPrefix . 'active_connections');
            $results['total'] = count($connections);

            foreach ($connections as $connectionId) {
                $key = $this->redisPrefix . 'connection:' . $connectionId;
                $userData = Redis::hgetall($key);

                if (empty($userData)) {
                    $results['failed']++;
                    continue;
                }

                // Mettre à jour la date de dernière activité
                $success = $this->updateActivity($connectionId);

                if ($success) {
                    $results['success']++;
                } else {
                    $results['failed']++;

                    // Si le ping a échoué, fermer la connexion
                    $this->closeConnection($connectionId);
                }

                $results['details'][] = [
                    'connection_id' => $connectionId,
                    'user_id' => $userData['user_id'] ?? null,
                    'user_type' => $userData['user_type'] ?? null,
                    'ping_success' => $success,
                    'timestamp' => Carbon::now()->toDateTimeString()
                ];
            }

            return $results;
        } catch (\Exception $e) {
            Log::error("Erreur lors du ping des connexions WebSocket: {$e->getMessage()}");

            return [
                'total' => 0,
                'success' => 0,
                'failed' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Configure une tâche cron pour le nettoyage automatique des connexions inactives
     */
    public static function setupCronJob()
    {
        // Cette méthode est appelée depuis le fichier de planification (app/Console/Kernel.php)
        // Exemple d'utilisation : 
        // $schedule->call(function() {
        //     (new WebSocketHealthService())->cleanInactiveConnections();
        // })->everyFiveMinutes();
    }

    /**
     * Ferme de force toutes les connexions d'un utilisateur spécifique
     *
     * @param string $userId
     * @param string $userType
     * @return int Nombre de connexions fermées
     */
    public function forceCloseUserConnections(string $userId, string $userType): int
    {
        try {
            $userKey = $this->redisPrefix . 'user:' . $userType . ':' . $userId;
            $connectionIds = Redis::smembers($userKey);
            $closedCount = 0;

            foreach ($connectionIds as $connectionId) {
                $success = $this->closeConnection($connectionId);
                if ($success) {
                    $closedCount++;
                }
            }

            if ($closedCount > 0) {
                Log::info("WebSocket: {$closedCount} connexions fermées de force pour {$userType} #{$userId}");
            }

            return $closedCount;
        } catch (\Exception $e) {
            Log::error("Erreur lors de la fermeture forcée des connexions: {$e->getMessage()}");
            return 0;
        }
    }
}
