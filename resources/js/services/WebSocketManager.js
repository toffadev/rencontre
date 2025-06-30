/**
 * Service centralisé pour la gestion des WebSockets
 * Gère les connexions Echo, les reconnexions et les abonnements aux canaux
 */
import authService from './AuthenticationService';

class WebSocketManager {
    constructor() {
        this.connectionStatus = 'disconnected'; // 'disconnected', 'connecting', 'connected'
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 10;
        this.baseReconnectDelay = 1000; // 1 seconde
        this.subscriptions = new Map(); // Stocke les abonnements actifs
        this.channels = new Map(); // Stocke les références aux canaux
        this.listeners = new Map(); // Stocke les listeners par canal
        this.healthCheckInterval = null;
        this.isInitialized = false;
        
        // Écouter les événements Echo
        window.addEventListener('echo:ready', this.handleEchoReady.bind(this));
        window.addEventListener('echo:disconnected', this.handleEchoDisconnected.bind(this));
    }

    /**
     * Initialise le gestionnaire de WebSockets
     */
    async initialize2() {
        if (this.isInitialized) return this;
        
        console.log('🚀 Initialisation du WebSocketManager');
        
        try {
            // S'assurer que le service d'authentification est initialisé
            await authService.initialize();
            
            // Si nous sommes sur une page d'authentification, ne pas continuer
            const isAuthPage = window.location.pathname.includes('/login') || 
                            window.location.pathname.includes('/register') ||
                            window.location.pathname === '/welcome';
            
            if (isAuthPage) {
                console.log('📝 Page d\'authentification détectée, initialisation WebSocket ignorée');
                return this;
            }
            
            // Attendre que Echo soit initialisé
            if (!window.Echo) {
                return new Promise((resolve, reject) => {
                    const echoInitHandler = () => {
                        document.removeEventListener('echo:initialized', echoInitHandler);
                        this.finishInitialization().then(resolve).catch(reject);
                    };
                    
                    document.addEventListener('echo:initialized', echoInitHandler);
                    
                    // Timeout après 15 secondes
                    setTimeout(() => {
                        document.removeEventListener('echo:initialized', echoInitHandler);
                        console.warn('⚠️ Timeout lors de l\'attente de l\'initialisation d\'Echo (15s)');
                        this.finishInitialization().then(resolve).catch(reject);
                    }, 15000);
                });
            }
            
            return await this.finishInitialization();
        } catch (error) {
            console.error('❌ Erreur lors de l\'initialisation du WebSocketManager:', error);
            // Planifier une réinitialisation
            setTimeout(() => {
                this.isInitialized = false;
                this.initialize();
            }, this.calculateReconnectDelay());
            
            return this;
        }
    }

    // Modifiez la méthode initialize pour être moins bloquante
    async initialize() {
        if (this.isInitialized) return this;
        
        console.log('🚀 Initialisation du WebSocketManager');
        
        try {
            // S'assurer que le service d'authentification est initialisé - mais pas de façon bloquante
            authService.initialize();
            
            // Si nous sommes sur une page d'authentification, ne pas continuer
            const isAuthPage = window.location.pathname.includes('/login') || 
                            window.location.pathname.includes('/register') ||
                            window.location.pathname === '/welcome';
            
            if (isAuthPage) {
                console.log('📝 Page d\'authentification détectée, initialisation WebSocket ignorée');
                return this;
            }
            
            // Attendre que Echo soit initialisé, mais avec un timeout plus court
            if (!window.Echo) {
                return new Promise((resolve) => {
                    const echoInitHandler = () => {
                        document.removeEventListener('echo:initialized', echoInitHandler);
                        this.finishInitialization().then(resolve);
                    };
                    
                    document.addEventListener('echo:initialized', echoInitHandler);
                    
                    // Timeout réduit à 5 secondes au lieu de 15
                    setTimeout(() => {
                        document.removeEventListener('echo:initialized', echoInitHandler);
                        console.warn('⚠️ Timeout lors de l\'attente de l\'initialisation d\'Echo (5s)');
                        this.isInitialized = true; // Marquer comme initialisé même si Echo n'est pas prêt
                        resolve(this);
                    }, 5000);
                });
            }
            
            // Finir l'initialisation de manière non-bloquante
            this.finishInitialization();
            this.isInitialized = true;
            return this;
        } catch (error) {
            console.error('❌ Erreur lors de l\'initialisation du WebSocketManager:', error);
            this.isInitialized = true; // Marquer comme initialisé pour ne pas bloquer le reste de l'application
            return this;
        }
    }

    /**
     * Rafraîchit le token CSRF pour les requêtes d'authentification WebSocket
     */
    async refreshCSRFToken() {
    try {
        console.log('🔄 Rafraîchissement du token CSRF pour WebSocket...');
        
        // Obtenir l'état actuel du cookie XSRF-TOKEN
        const currentXsrfCookie = this.getCookie('XSRF-TOKEN');
        console.log('🍪 Cookie XSRF-TOKEN actuel:', currentXsrfCookie ? 'présent' : 'absent');
        
        // Appeler l'endpoint sanctum/csrf-cookie
        const response = await axios.get('/sanctum/csrf-cookie', {
        withCredentials: true
        });
        
        // Vérifier le cookie après la requête
        const newXsrfCookie = this.getCookie('XSRF-TOKEN');
        console.log('🍪 Nouveau cookie XSRF-TOKEN:', newXsrfCookie ? 'présent' : 'absent');
        
        // Récupérer le token depuis les meta tags
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        console.log('🔑 Token CSRF dans les meta tags:', csrfToken ? csrfToken.substring(0, 10) + '...' : 'absent');
        
        if (csrfToken) {
        // Mettre à jour le token dans Axios
        axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
        
        // Mettre à jour le token dans Echo si disponible
        if (window.Echo && window.Echo.connector && window.Echo.connector.options) {
            window.Echo.connector.options.auth.headers['X-CSRF-TOKEN'] = csrfToken;
            console.log('🔄 Token CSRF mis à jour dans Echo');
        }
        
        console.log('✅ Token CSRF rafraîchi pour WebSocket');
        return true;
        } else {
        console.warn('⚠️ Token CSRF non trouvé après rafraîchissement');
        return false;
        }
    } catch (error) {
        console.error('❌ Erreur lors du rafraîchissement du token CSRF:', error);
        return false;
    }
    }

    // Ajouter cette fonction utilitaire
    getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
    }

    /**
     * Termine l'initialisation après que Echo soit disponible
     */
    async finishInitialization() {
        try {
            // Vérifier que tous les prérequis sont disponibles avant de continuer
            await this.checkPrerequisites();
            
            // Rafraîchir le token CSRF avant de configurer les gestionnaires
            await this.refreshCSRFToken();
            
            // Vérifier si Echo est déjà initialisé
            if (window.Echo && (window.Echo.connector.pusher || window.Echo.connector.socket)) {
                this.setupConnectionHandlers();
            }
            
            // Démarrer la vérification périodique de l'état de la connexion
            this.startHealthCheck();
            
            this.isInitialized = true;
            console.log('✅ WebSocketManager initialisé avec succès');
            
            // Si Echo est déjà prêt, mettre à jour le statut
            if (window.echoReady) {
                this.handleEchoReady();
            }
        } catch (error) {
            console.error('❌ Erreur lors de la finalisation de l\'initialisation:', error);
        }
        
        return this;
    }
    
    /**
     * Gère l'événement echo:ready
     */
    handleEchoReady() {
        console.log('🟢 Événement echo:ready reçu');
        this.connectionStatus = 'connected';
        this.reconnectAttempts = 0;
        
        // Réabonner aux canaux précédemment souscrits
        this.resubscribeToChannels();
    }
    
    /**
     * Gère l'événement echo:disconnected
     */
    handleEchoDisconnected() {
        console.log('🔴 Événement echo:disconnected reçu');
        this.connectionStatus = 'disconnected';
    }

    /**
     * Vérifie que tous les prérequis sont disponibles
     * Attend avec retry si certains ne sont pas prêts
     */
    async checkPrerequisites() {
        return new Promise((resolve, reject) => {
            const check = () => {
                // Vérifier si nous sommes sur une page d'authentification
                const isAuthPage = window.location.pathname.includes('/login') || 
                                window.location.pathname.includes('/register') ||
                                window.location.pathname === '/welcome';
                
                // Sur les pages d'authentification, ne pas vérifier les infos utilisateur
                if (isAuthPage) {
                    console.log('📝 Page d\'authentification détectée, vérification des infos utilisateur ignorée');
                    return resolve();
                }
                
                // Récupérer les infos utilisateur
                const userInfo = this.getUserInfo();
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                
                if (!csrfToken) {
                    console.warn('⚠️ CSRF Token non disponible, nouvelle tentative dans 1s...');
                    setTimeout(check, 1000);
                    return;
                }
                
                // Si nous ne sommes pas sur une page d'auth mais qu'aucun utilisateur n'est connecté
                if (!userInfo) {
                    // Si nous avons déjà essayé plusieurs fois, arrêter les tentatives
                    if (this.reconnectAttempts > 3) {
                        console.warn('⚠️ Impossible de récupérer les infos utilisateur après plusieurs tentatives');
                        return resolve();
                    }
                    
                    this.reconnectAttempts++;
                    console.warn('⚠️ Informations utilisateur non disponibles, nouvelle tentative dans 1s...');
                    setTimeout(check, 1000);
                    return;
                }
                
                console.log('✅ Tous les prérequis sont disponibles');
                
                // Configurer Axios avec le token CSRF
                window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
                window.axios.defaults.withCredentials = true;
                
                // Définir les variables globales
                if (userInfo) {
                    window.clientId = userInfo.id;
                    window.userType = userInfo.type;
                }
                
                resolve();
            };
            
            // Démarrer la vérification
            check();
        });
    }
    
    /**
     * Vérifie l'état de la connexion WebSocket
     */
    async checkConnectionHealth() {
        // Si nous sommes déjà déconnectés, ne rien faire
        if (this.connectionStatus === 'disconnected') return;
        
        // Si Echo n'est pas initialisé, le signaler
        if (!window.Echo || !window.Echo.connector || !window.Echo.connector.socket) {
            console.warn('🟡 Echo non initialisé ou socket manquant');
            this.connectionStatus = 'disconnected';
            window.isWebSocketConnected = false;
            return;
        }
        
        try {
            // Vérifier l'état de la connexion
            const isConnected = window.Echo.connector.socket.connection.state === 'connected';
            
            if (!isConnected && this.connectionStatus === 'connected') {
                console.warn('🟡 Connexion WebSocket perdue');
                this.connectionStatus = 'disconnected';
                window.isWebSocketConnected = false;
            }
        } catch (error) {
            console.error('❌ Erreur lors de la vérification de l\'état de la connexion:', error);
            this.connectionStatus = 'disconnected';
            window.isWebSocketConnected = false;
        }
    }

    /**
     * Configure les gestionnaires d'événements pour la connexion
     */
    setupConnectionHandlers() {
        if (!window.Echo || !window.Echo.connector) {
            console.error('❌ Impossible de configurer les gestionnaires d\'événements: Echo non initialisé');
            return;
        }
        
        try {
            if (window.Echo.connector.socket) {
                // Événement de connexion établie
                window.Echo.connector.socket.on('connected', () => {
                    console.log('🟢 WebSocket connecté');
                    this.connectionStatus = 'connected';
                    this.reconnectAttempts = 0;
                    window.isWebSocketConnected = true;
                    
                    // Réabonner aux canaux précédemment souscrits
                    this.resubscribeToChannels();
                });
                
                // Événement d'erreur
                window.Echo.connector.socket.on('error', (error) => {
                    console.error('🔴 Erreur WebSocket:', error);
                    this.connectionStatus = 'disconnected';
                    window.isWebSocketConnected = false;
                });
                
                // Événement de déconnexion
                window.Echo.connector.socket.on('disconnected', () => {
                    console.warn('🟡 WebSocket déconnecté');
                    this.connectionStatus = 'disconnected';
                    window.isWebSocketConnected = false;
                });
                
                console.log('✅ Gestionnaires d\'événements configurés avec succès');
            } else {
                console.log('⚠️ Socket Echo non disponible pour configurer les gestionnaires d\'événements');
            }
        } catch (error) {
            console.error('❌ Erreur lors de la configuration des gestionnaires d\'événements:', error);
        }

        // Intercepter les erreurs d'authentification
        if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
            const originalAuthorizer = window.Echo.connector.pusher.config.authorizer;
            
            if (originalAuthorizer) {
                window.Echo.connector.pusher.config.authorizer = function(channel) {
                    return {
                        authorize: async (socketId, callback) => {
                            try {
                                // Utiliser l'authorizer original
                                const originalAuth = originalAuthorizer(channel);
                                
                                originalAuth.authorize(socketId, async (error, data) => {
                                    if (error && (error.status === 419 || error.code === 4019)) {
                                        console.warn('⚠️ Erreur CSRF 419 détectée, rafraîchissement du token...');
                                        
                                        try {
                                            // Rafraîchir le token CSRF
                                            await this.refreshCSRFToken();
                                            
                                            // Réessayer l'autorisation avec le nouveau token
                                            const retryAuth = originalAuthorizer(channel);
                                            retryAuth.authorize(socketId, (retryError, retryData) => {
                                                callback(retryError, retryData);
                                            });
                                        } catch (refreshError) {
                                            console.error('❌ Échec du rafraîchissement du token CSRF:', refreshError);
                                            callback(error, null);
                                        }
                                    } else {
                                        callback(error, data);
                                    }
                                });
                            } catch (err) {
                                console.error('❌ Erreur dans l\'authorizer:', err);
                                callback(err, null);
                            }
                        }
                    };
                };
            }
        }
    }

    /**
     * S'abonne à un canal privé et stocke la référence
     */
    subscribeToPrivateChannel(channelName, events = {}) {
        if (!window.Echo) {
            console.error('❌ Impossible de s\'abonner: Echo non initialisé');
            return null;
        }
        
        // Normaliser le nom du canal
        const normalizedName = channelName.startsWith('private-') 
            ? channelName 
            : `private-${channelName}`;
        
        console.log(`📡 Abonnement au canal: ${normalizedName}`);
        
        try {
            // Créer l'abonnement
            const channel = window.Echo.private(normalizedName.replace('private-', ''));
            
            // Stocker la référence au canal
            this.channels.set(normalizedName, channel);
            
            // Initialiser la collection de listeners pour ce canal
            if (!this.listeners.has(normalizedName)) {
                this.listeners.set(normalizedName, new Map());
            }
            
            // Ajouter les listeners d'événements
            Object.entries(events).forEach(([event, callback]) => {
                channel.listen(event, callback);
                this.listeners.get(normalizedName).set(event, callback);
            });
            
            // Stocker les informations d'abonnement
            this.subscriptions.set(normalizedName, {
                events,
                timestamp: Date.now()
            });
            
            return channel;
        } catch (error) {
            console.error(`❌ Erreur lors de l'abonnement au canal ${normalizedName}:`, error);
            return null;
        }
    }

    /**
     * Réabonne aux canaux précédemment souscrits
     */
    resubscribeToChannels() {
        console.log(`🔄 Réabonnement à ${this.subscriptions.size} canaux`);
        
        // Copier les abonnements pour éviter les modifications pendant l'itération
        const subscriptionsCopy = new Map(this.subscriptions);
        
        // Vider les collections actuelles
        this.channels.clear();
        this.listeners.clear();
        
        // Réabonner à chaque canal
        subscriptionsCopy.forEach((subscription, channelName) => {
            console.log(`🔄 Réabonnement au canal: ${channelName}`);
            this.subscribeToPrivateChannel(channelName, subscription.events);
        });
    }

    /**
     * Démarre la vérification périodique de l'état de la connexion
     */
    startHealthCheck() {
        // Nettoyer l'intervalle existant si nécessaire
        if (this.healthCheckInterval) {
            clearInterval(this.healthCheckInterval);
        }
        
        // Vérifier l'état toutes les 30 secondes
        this.healthCheckInterval = setInterval(() => {
            this.checkConnectionHealth();
        }, 30000);
    }

    /**
     * Calcule le délai de reconnexion avec backoff exponentiel
     */
    calculateReconnectDelay() {
        // Formule de backoff exponentiel avec jitter
        const expBackoff = this.baseReconnectDelay * Math.pow(2, this.reconnectAttempts);
        const jitter = Math.random() * 0.5 * expBackoff; // Ajouter jusqu'à 50% de jitter
        return Math.min(expBackoff + jitter, 30000); // Maximum 30 secondes
    }

    /**
     * Se désabonne d'un canal
     */
    unsubscribeFromChannel(channelName) {
        if (!window.Echo) return;
        
        // Normaliser le nom du canal
        const normalizedName = channelName.startsWith('private-') 
            ? channelName 
            : `private-${channelName}`;
        
        try {
            console.log(`❌ Désabonnement du canal: ${normalizedName}`);
            
            // Quitter le canal
            window.Echo.leave(normalizedName.replace('private-', ''));
            
            // Supprimer les références
            this.channels.delete(normalizedName);
            this.listeners.delete(normalizedName);
            this.subscriptions.delete(normalizedName);
        } catch (error) {
            console.error(`❌ Erreur lors du désabonnement du canal ${normalizedName}:`, error);
        }
    }

    /**
     * Récupère l'état de la connexion
     */
    getConnectionStatus() {
        return this.connectionStatus;
    }

    /**
     * Vérifie si la connexion est active
     */
    isConnected() {
        return this.connectionStatus === 'connected' || window.echoReady === true;
    }

    /**
     * Nettoie toutes les ressources
     */
    cleanup() {
        // Arrêter la vérification périodique
        if (this.healthCheckInterval) {
            clearInterval(this.healthCheckInterval);
            this.healthCheckInterval = null;
        }
        
        // Réinitialiser l'état
        this.connectionStatus = 'disconnected';
        this.reconnectAttempts = 0;
        this.isInitialized = false;
        
        console.log('🧹 WebSocketManager nettoyé');
    }

    getUserInfo() {
        // Vérifier d'abord dans window.Laravel
        if (window.Laravel && window.Laravel.user) {
            return {
                id: window.Laravel.user.id,
                type: window.Laravel.user.type,
                name: window.Laravel.user.name
            };
        }
        
        // Sinon, essayer les meta tags
        const userId = document.querySelector('meta[name="user-id"]')?.getAttribute('content');
        const userType = document.querySelector('meta[name="user-type"]')?.getAttribute('content');
        
        if (!userId || !userType) {
            return null;
        }
        
        return {
            id: parseInt(userId),
            type: userType
        };
    }
}

// Créer une instance singleton
const webSocketManager = new WebSocketManager();

export default webSocketManager;