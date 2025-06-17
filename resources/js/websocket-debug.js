/**
 * Utilitaire de diagnostic et de réparation des connexions WebSocket
 */

class WebSocketDebugger {
    constructor() {
        this.connectionStatus = {
            connected: false,
            lastConnected: null,
            reconnectAttempts: 0,
            channels: {}
        };

        this.init();
    }

    init() {
        // Vérifier si Echo est disponible
        if (!window.Echo) {
            console.error('❌ Echo n\'est pas initialisé');
            return;
        }

        // Observer l'état de la connexion
        this.setupConnectionObserver();
        
        // Ajouter des méthodes de diagnostic au window
        this.exposeGlobalMethods();
    }

    setupConnectionObserver() {
        if (window.Echo && window.Echo.connector && window.Echo.connector.socket) {
            const socket = window.Echo.connector.socket;
            
            socket.on('connected', () => {
                this.connectionStatus.connected = true;
                this.connectionStatus.lastConnected = new Date();
                this.connectionStatus.reconnectAttempts = 0;
                console.log('✅ WebSocket connecté à', new Date().toLocaleTimeString());
            });
            
            socket.on('disconnected', () => {
                this.connectionStatus.connected = false;
                console.warn('⚠️ WebSocket déconnecté à', new Date().toLocaleTimeString());
            });
            
            socket.on('connecting', () => {
                this.connectionStatus.reconnectAttempts++;
                console.log('🔄 Tentative de connexion WebSocket #' + this.connectionStatus.reconnectAttempts);
            });
        }
    }

    exposeGlobalMethods() {
        // Exposer les méthodes de diagnostic
        window.wsDebug = {
            // Afficher l'état actuel
            status: () => this.getStatus(),
            
            // Reconnecter manuellement
            reconnect: () => this.forceReconnect(),
            
            // Vérifier les canaux
            channels: () => this.listChannels(),
            
            // Diagnostiquer les problèmes
            diagnose: () => this.diagnoseProblems(),
            
            // Réparer automatiquement
            fix: () => this.fixProblems()
        };
    }

    getStatus() {
        const status = {
            ...this.connectionStatus,
            echoInitialized: !!window.Echo,
            socketConnected: !!(window.Echo?.connector?.socket?.connection?.transport?.socket?.readyState === 1),
            subscribedChannels: this.listChannels()
        };
        
        console.table({
            "Echo initialisé": status.echoInitialized ? "✅ OUI" : "❌ NON",
            "Socket connecté": status.socketConnected ? "✅ OUI" : "❌ NON",
            "Dernière connexion": status.lastConnected ? status.lastConnected.toLocaleTimeString() : "Jamais",
            "Tentatives de reconnexion": status.reconnectAttempts,
            "Canaux souscrits": Object.keys(status.subscribedChannels).length
        });
        
        if (Object.keys(status.subscribedChannels).length > 0) {
            console.log("Canaux souscrits:");
            console.table(status.subscribedChannels);
        }
        
        return status;
    }

    listChannels() {
        const channels = {};
        
        if (window.Echo && window.Echo.connector) {
            const echoChannels = window.Echo.connector.channels || {};
            
            Object.keys(echoChannels).forEach(channelName => {
                const channel = echoChannels[channelName];
                channels[channelName] = {
                    name: channelName,
                    type: channelName.startsWith('private-') ? 'privé' : 'public',
                    state: channel.state || 'unknown',
                    subscribed: !!channel.subscribed
                };
            });
        }
        
        return channels;
    }

    diagnoseProblems() {
        const problems = [];
        
        // Vérifier si Echo est initialisé
        if (!window.Echo) {
            problems.push({
                severity: 'critical',
                message: 'Echo n\'est pas initialisé',
                fix: 'Recharger la page'
            });
            return problems;
        }
        
        // Vérifier la connexion socket
        if (!window.Echo.connector || !window.Echo.connector.socket) {
            problems.push({
                severity: 'critical',
                message: 'Socket non initialisé',
                fix: 'Reconnecter manuellement'
            });
        } else if (window.Echo.connector.socket.connection?.transport?.socket?.readyState !== 1) {
            problems.push({
                severity: 'high',
                message: 'Socket déconnecté',
                fix: 'Reconnecter'
            });
        }
        
        // Vérifier les canaux privés
        const channels = this.listChannels();
        Object.keys(channels).forEach(channelName => {
            const channel = channels[channelName];
            if (channel.type === 'privé' && !channel.subscribed) {
                problems.push({
                    severity: 'medium',
                    message: `Canal ${channelName} non souscrit`,
                    fix: 'Reconnecter pour réessayer l\'autorisation'
                });
            }
        });
        
        if (problems.length === 0) {
            console.log('✅ Aucun problème détecté');
        } else {
            console.log('⚠️ Problèmes détectés:');
            console.table(problems);
        }
        
        return problems;
    }

    forceReconnect() {
        console.log('🔄 Reconnexion WebSocket forcée...');
        
        if (window.Echo && window.Echo.connector && window.Echo.connector.socket) {
            // Déconnecter
            window.Echo.connector.socket.disconnect();
            
            // Reconnecter après un court délai
            setTimeout(() => {
                window.Echo.connector.socket.connect();
            }, 1000);
        } else {
            // Si Echo n'est pas disponible, recharger la page
            console.warn('❌ Echo non disponible, rechargement de la page recommandé');
        }
        
        // Vérifier l'état après 2 secondes
        setTimeout(() => this.getStatus(), 2000);
        
        return true;
    }

    fixProblems() {
        const problems = this.diagnoseProblems();
        
        if (problems.length === 0) {
            return { fixed: 0, message: 'Aucun problème à résoudre' };
        }
        
        // Tenter de résoudre les problèmes
        console.log('🔧 Tentative de résolution des problèmes...');
        
        // Pour l'instant, la seule solution est de reconnecter
        this.forceReconnect();
        
        return { 
            fixed: problems.length, 
            message: `Tentative de résolution de ${problems.length} problème(s)` 
        };
    }
}

// Initialiser le debugger
document.addEventListener('DOMContentLoaded', () => {
    // Attendre un peu pour s'assurer que Echo est initialisé
    setTimeout(() => {
        window.wsDebugger = new WebSocketDebugger();
        console.log('🛠️ Debugger WebSocket initialisé. Utilisez window.wsDebug pour accéder aux outils de diagnostic.');
    }, 1000);
});

export default WebSocketDebugger;
