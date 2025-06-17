/**
 * Composable pour surveiller la santé des connexions WebSocket
 * Fournit des métriques et des fonctionnalités de reconnexion préventive
 */
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import webSocketManager from '../services/WebSocketManager';

export function useWebSocketHealth() {
    const connectionStatus = ref('unknown');
    const connectionState = ref('disconnected');
    const isHealthy = computed(() => connectionStatus.value === 'connected');
    const reconnectAttempts = ref(0);
    const latency = ref(null);
    const lastPingTime = ref(null);
    const pingInterval = ref(null);
    const healthCheckInterval = ref(null);
    const isVisible = ref(true);

    function updateConnectionState(state) {
        connectionState.value = state;
    }

    // État calculé de la connexion
   /*  const connectionState = computed(() => {
        if (connectionStatus.value === 'connected' && isHealthy.value) {
            return 'healthy';
        } else if (connectionStatus.value === 'connected' && !isHealthy.value) {
            return 'degraded';
        } else if (connectionStatus.value === 'connecting') {
            return 'connecting';
        } else {
            return 'disconnected';
        }
    }); */

    // Surveiller les changements de visibilité de la page
    onMounted(() => {
        document.addEventListener('visibilitychange', handleVisibilityChange);
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
        
        // Démarrer la surveillance
        startHealthMonitoring();
        
        console.log('🔍 Surveillance de la santé WebSocket démarrée');
    });

    // Nettoyer les écouteurs lors du démontage
    onUnmounted(() => {
        document.removeEventListener('visibilitychange', handleVisibilityChange);
        window.removeEventListener('online', handleOnline);
        window.removeEventListener('offline', handleOffline);
        
        // Arrêter la surveillance
        stopHealthMonitoring();
        
        console.log('🛑 Surveillance de la santé WebSocket arrêtée');
    });

    // Observer les changements d'état de la connexion
    watch(connectionStatus, (newStatus, oldStatus) => {
        if (newStatus === 'connected' && oldStatus !== 'connected') {
            console.log('🟢 Connexion WebSocket rétablie');
            
            // Réinitialiser les indicateurs de santé
            isHealthy.value = true;
            reconnectAttempts.value = 0;
            
            // Émettre un événement personnalisé pour informer l'application
            window.dispatchEvent(new CustomEvent('websocket:connected'));
        } else if (newStatus === 'disconnected' && oldStatus !== 'disconnected') {
            console.warn('🔴 Connexion WebSocket perdue');
            
            // Émettre un événement personnalisé pour informer l'application
            window.dispatchEvent(new CustomEvent('websocket:disconnected'));
            
            // Tenter une reconnexion si la page est visible
            if (isVisible.value) {
                attemptReconnect();
            }
        }
    });

    // Gérer les changements de visibilité de la page
    function handleVisibilityChange() {
        isVisible.value = document.visibilityState === 'visible';
        
        if (isVisible.value) {
            console.log('📱 Page visible, vérification de la connexion WebSocket');
            checkConnection();
        } else {
            console.log('📴 Page masquée, pause de la surveillance active');
        }
    }

    // Gérer la reconnexion au réseau
    function handleOnline() {
        console.log('🌐 Connexion réseau rétablie, vérification de la connexion WebSocket');
        checkConnection();
    }

    // Gérer la perte de connexion réseau
    function handleOffline() {
        console.warn('📵 Connexion réseau perdue');
        connectionStatus.value = 'disconnected';
        isHealthy.value = false;
    }

    // Démarrer la surveillance de la santé
    function startHealthMonitoring() {
        // Vérifier immédiatement l'état de la connexion
        checkConnection();
        
        // Configurer la vérification périodique de la santé
        healthCheckInterval.value = setInterval(() => {
            if (isVisible.value) {
                checkConnection();
            }
        }, 30000); // Vérifier toutes les 30 secondes
        
        // Configurer les pings périodiques pour mesurer la latence
        pingInterval.value = setInterval(() => {
            if (isVisible.value && connectionStatus.value === 'connected') {
                measureLatency();
            }
        }, 60000); // Mesurer la latence toutes les 60 secondes
    }

    // Arrêter la surveillance de la santé
    function stopHealthMonitoring() {
        if (healthCheckInterval.value) {
            clearInterval(healthCheckInterval.value);
            healthCheckInterval.value = null;
        }
        
        if (pingInterval.value) {
            clearInterval(pingInterval.value);
            pingInterval.value = null;
        }
    }

    // Vérifier l'état de la connexion
    function checkConnection() {
        if (!window.Echo) {
            console.log('Echo non initialisé');
            return false;
        }
        
        try {
            // Pour Pusher
            if (window.Echo.connector && window.Echo.connector.pusher) {
                return window.Echo.connector.pusher.connection.state === 'connected';
            }
            
            // Pour Reverb
            /* if (window.Echo.connector && window.Echo.connector.socket) {
                return window.Echo.connector.socket.readyState === 1;
            } */
            
            console.log('Echo non initialisé ou socket manquant');
            return false;
        } catch (error) {
            console.error('Erreur lors de la vérification de la connexion:', error);
            return false;
        }
    }

    // Mesurer la latence de la connexion
    function measureLatency() {
        if (!window.Echo) {
            latency.value = null;
            return;
        }
        
        lastPingTime.value = Date.now();
        
        try {
            // Gestion pour Pusher
            if (window.Echo.connector && window.Echo.connector.pusher) {
                window.Echo.connector.pusher.connection.bind('pong', () => {
                    if (lastPingTime.value) {
                        latency.value = Date.now() - lastPingTime.value;
                        console.log(`📊 Latence WebSocket (Pusher): ${latency.value}ms`);
                        
                        // Considérer une latence élevée comme un signe de dégradation
                        if (latency.value > 1000) {
                            console.warn(`⚠️ Latence élevée: ${latency.value}ms`);
                            isHealthy.value = false;
                        }
                    }
                });
                
                // Envoyer un ping via Pusher
                window.Echo.connector.pusher.connection.ping();
                return;
            }
            
            // Gestion pour Socket.io/Reverb
            if (window.Echo.connector && window.Echo.connector.socket && 
                window.Echo.connector.socket.connection) {
                window.Echo.connector.socket.connection.ping(pong => {
                    if (lastPingTime.value) {
                        latency.value = Date.now() - lastPingTime.value;
                        console.log(`📊 Latence WebSocket: ${latency.value}ms`);
                        
                        // Considérer une latence élevée comme un signe de dégradation
                        if (latency.value > 1000) {
                            console.warn(`⚠️ Latence élevée: ${latency.value}ms`);
                            isHealthy.value = false;
                        }
                    }
                });
            }
        } catch (error) {
            console.warn('❌ Erreur lors de la mesure de latence:', error);
            latency.value = null;
        }
    }

    // Tenter une reconnexion
    function attemptReconnect() {
        if (reconnectAttempts.value >= 5) {
            console.error('❌ Nombre maximum de tentatives de reconnexion atteint');
            return;
        }
        
        reconnectAttempts.value++;
        console.log(`🔄 Tentative de reconnexion ${reconnectAttempts.value}/5...`);
        
        // Réinitialiser la connexion WebSocket
        webSocketManager.initialize();
    }

    // Forcer une reconnexion
    function forceReconnect() {
        console.log('🔄 Reconnexion forcée...');
        reconnectAttempts.value = 0;
        webSocketManager.cleanup();
        webSocketManager.initialize();
    }

    return {
        connectionStatus,
        connectionState,
        isHealthy,
        latency,
        reconnectAttempts,
        forceReconnect,
        checkConnection,
        updateConnectionState // Exposer cette nouvelle fonction
    };
}