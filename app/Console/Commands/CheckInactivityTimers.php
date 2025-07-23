<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TimeoutManagementService;
use Illuminate\Support\Facades\Log;
use App\Services\ModeratorActivityService;

class CheckInactivityTimers extends Command
{
    protected $signature = 'moderator:check-timers 
                           {--cleanup : Nettoyer seulement les timers expirés}
                           {--stats : Afficher les statistiques des timers}
                           {--force : Forcer la vérification même en mode réactif}';

    protected $description = 'Vérifie les timers d\'inactivité des modérateurs (fallback pour le mode réactif)';

    protected $timeoutService;

    public function __construct(TimeoutManagementService $timeoutService)
    {
        parent::__construct();
        $this->timeoutService = $timeoutService;
    }

    public function handle()
    {
        $startTime = microtime(true);

        // Mode stats seulement
        if ($this->option('stats')) {
            $this->displayStats();
            return Command::SUCCESS;
        }

        // Mode cleanup seulement
        if ($this->option('cleanup')) {
            $this->performCleanup();
            return Command::SUCCESS;
        }

        // Vérification complète (fallback mode)
        if (!$this->option('force')) {
            $this->warn('⚠️ ATTENTION: Cette commande est un fallback pour le système réactif.');
            $this->warn('   Le système réactif devrait gérer les timers automatiquement.');
            $this->warn('   Utilisez --force pour bypass cette vérification.');
            return Command::SUCCESS;
        }

        $this->info('🔍 Vérification des timers d\'inactivité (mode fallback)...');

        try {
            // 1. Vérifier les timers actifs
            $expiredCount = $this->timeoutService->checkTimers();

            // 2. Nettoyer les timers expirés
            $cleanedCount = $this->timeoutService->cleanupExpiredTimers();
            Log::info('Nettoyage des timers expirés', ['cleaned_count' => $cleanedCount]);
            $this->info("Nettoyage des timers expirés: {$cleanedCount}");

            // Vérification de l'inactivité des modérateurs
            $activityService = app(ModeratorActivityService::class);
            $activityService->checkInactivity();

            // 3. Statistiques
            $stats = $this->timeoutService->getTimerStats();

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->info("✅ Vérification terminée en {$executionTime}ms");
            $this->table(['Métrique', 'Valeur'], [
                ['Timers expirés traités', $expiredCount['expired'] ?? 0],
                ['Timers nettoyés', $cleanedCount],
                ['Timers actifs', $stats['total_active'] ?? 0],
                ['Warnings envoyés', $stats['warning_zone'] ?? 0],
                ['Temps d\'exécution', $executionTime . 'ms']
            ]);

            // Log pour monitoring
            Log::info('Commande check-timers exécutée', [
                'expired_processed' => $expiredCount['expired'] ?? 0,
                'cleaned' => $cleanedCount,
                'active_timers' => $stats['total_active'] ?? 0,
                'execution_time_ms' => $executionTime,
                'mode' => 'fallback'
            ]);
        } catch (\Exception $e) {
            $this->error("❌ Erreur lors de la vérification: {$e->getMessage()}");
            Log::error('Erreur commande check-timers', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Affiche les statistiques des timers
     */
    protected function displayStats()
    {
        $this->info('📊 Statistiques des timers d\'inactivité');

        $stats = $this->timeoutService->getTimerStats();

        $this->table(['Métrique', 'Valeur'], [
            ['Timers actifs', $stats['active_timers']],
            ['Warnings envoyés (dernière heure)', $stats['warnings_sent'] ?? 0],
            ['Timers expirés (dernière heure)', $stats['expired_timers'] ?? 0],
            ['Moyenne temps avant expiration', ($stats['avg_remaining_time'] ?? 0) . 's'],
            ['Timer le plus ancien', $stats['oldest_timer'] ?? 'N/A'],
            ['Dernière vérification', $stats['last_check'] ?? 'N/A']
        ]);

        // Détails des timers actifs si demandé
        if ($this->option('verbose')) {
            $this->info('📝 Détails des timers actifs:');
            $activeTimers = $this->timeoutService->getActiveTimersData();

            if (empty($activeTimers)) {
                $this->comment('Aucun timer actif');
            } else {
                $headers = ['Modérateur', 'Profil', 'Temps restant', 'Créé il y a'];
                $rows = [];

                foreach ($activeTimers as $timer) {
                    $rows[] = [
                        $timer['moderator_id'],
                        $timer['profile_id'],
                        $timer['remaining_seconds'] . 's',
                        $timer['created_ago']
                    ];
                }

                $this->table($headers, $rows);
            }
        }
    }

    /**
     * Effectue le nettoyage des timers expirés
     */
    protected function performCleanup()
    {
        $this->info('🧹 Nettoyage des timers expirés...');

        $cleaned = $this->timeoutService->cleanupExpiredTimers();

        $this->info("✅ {$cleaned} timers expirés nettoyés");

        Log::info('Nettoyage timers effectué', ['cleaned_count' => $cleaned]);
    }
}
