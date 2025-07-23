<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Système de Modération Réactif
    |--------------------------------------------------------------------------
    |
    | Configuration du nouveau système de modération basé sur les événements
    | et timers réactifs au lieu du polling traditionnel.
    |
    */

    // 🚨 Fallback et sécurité
    'enable_fallback_polling' => env('MODERATOR_FALLBACK_POLLING', false),

    // ⏰ Configuration des timers d'inactivité
    'inactivity_timeout_seconds' => env('MODERATOR_INACTIVITY_TIMEOUT', 300), // 5 minutes par défaut

    // ⚠️ Avertissements avant expiration
    'warning_thresholds' => [
        'first_warning' => env('MODERATOR_WARNING_1', 60),  // 1 minute avant
        'second_warning' => env('MODERATOR_WARNING_2', 30), // 30 secondes avant
        'final_warning' => env('MODERATOR_WARNING_3', 10),  // 10 secondes avant
    ],

    // 🔄 Configuration des réassignations
    'reassignment' => [
        'max_attempts' => env('MODERATOR_REASSIGN_ATTEMPTS', 3),
        'retry_delay_seconds' => env('MODERATOR_RETRY_DELAY', 5),
        'exclude_inactive_duration' => env('MODERATOR_EXCLUDE_DURATION', 300), // 5 minutes
    ],

    // 💾 Configuration du cache
    'cache' => [
        'timer_prefix' => 'moderator_timer',
        'active_timers_key' => 'active_inactivity_timers',
        'default_ttl' => 3600, // 1 heure
        'cleanup_batch_size' => 100,
    ],

    // 🔍 Monitoring et métriques
    'monitoring' => [
        'enable_metrics' => env('MODERATOR_ENABLE_METRICS', true),
        'metrics_retention_hours' => env('MODERATOR_METRICS_RETENTION', 168), // 7 jours
        'alert_on_high_timeout_rate' => env('MODERATOR_ALERT_HIGH_TIMEOUTS', true),
        'high_timeout_threshold' => 10, // 10 timeouts par heure = alerte
    ],

    // 📡 Configuration WebSocket
    'websocket' => [
        'channel_prefix' => 'moderator',
        'warning_event' => 'inactivity.warning',
        'timeout_event' => 'inactivity.timeout',
        'retry_failed_broadcasts' => true,
        'broadcast_timeout' => 5, // 5 secondes timeout pour WebSocket
    ],

    // ⚙️ Configuration avancée
    'advanced' => [
        'enable_concurrent_safety' => env('MODERATOR_CONCURRENT_SAFETY', true),
        'lock_timeout_seconds' => 10,
        'enable_performance_logging' => env('MODERATOR_PERF_LOGGING', false),
        'max_timer_drift_seconds' => 2, // Tolérance de dérive des timers
    ],

    // 🧹 Maintenance et nettoyage
    'cleanup' => [
        'auto_cleanup_enabled' => true,
        'cleanup_interval_minutes' => 5,
        'keep_expired_timers_hours' => 24, // Garder 24h pour debug
        'archive_metrics_after_days' => 30,
    ],

    // 🚨 Gestion d'erreurs
    'error_handling' => [
        'max_queue_retries' => 3,
        'failed_job_retention_days' => 7,
        'alert_admin_on_critical_failure' => true,
        'admin_notification_channels' => ['mail', 'slack'], // Configurer selon vos besoins
    ],

    // 🧪 Mode développement
    'development' => [
        'enable_debug_logging' => env('APP_DEBUG', false),
        'simulate_slow_responses' => env('MODERATOR_SIMULATE_SLOW', false),
        'force_timeouts_for_testing' => env('MODERATOR_FORCE_TIMEOUTS', false),
        'mock_websocket_failures' => env('MODERATOR_MOCK_WS_FAILURES', false),
    ],

];
