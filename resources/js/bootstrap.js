import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Configuration d'Axios avec le token CSRF
 */
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Fonction pour initialiser Echo de manière sécurisée
function initializeEcho() {
    // Attendre que le DOM soit chargé
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupEcho);
    } else {
        setupEcho();
    }
}

// Fonction pour obtenir le token CSRF
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
}

// Fonction pour configurer Axios
function configureAxios() {
    const token = getCsrfToken();
    if (token) {
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        window.axios.defaults.withCredentials = true;
    }
}

// Configurer l'intercepteur Axios pour gérer les erreurs CSRF
window.axios.interceptors.response.use(
    response => response,
    async error => {
        if (error.response?.status === 419) {
            // Récupérer un nouveau token CSRF
            try {
                await window.axios.get('/sanctum/csrf-cookie');
                const newToken = getCsrfToken();
                if (newToken) {
                    // Mettre à jour le token dans les headers
                    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = newToken;
                    // Réessayer la requête originale avec le nouveau token
                    error.config.headers['X-CSRF-TOKEN'] = newToken;
                    return window.axios(error.config);
                }
            } catch (refreshError) {
                console.error('Erreur lors du rafraîchissement du token CSRF:', refreshError);
                window.location.reload(); // Recharger la page en dernier recours
            }
        }
        return Promise.reject(error);
    }
);

/* function setupEcho() {
    // Récupérer le token CSRF et les données utilisateur
    const csrfToken = getCsrfToken();
    const userId = document.querySelector('meta[name="user-id"]')?.getAttribute('content');
    const userType = document.querySelector('meta[name="user-type"]')?.getAttribute('content');

    // S'assurer que nous avons toutes les données nécessaires
    if (!csrfToken || !userId || !userType) {
        console.warn('Données d\'authentification manquantes, réessai dans 1 seconde...');
        setTimeout(setupEcho, 1000);
        return;
    }

    // Configurer Axios
    configureAxios();

    // Configuration de Pusher
    window.Pusher = Pusher;

    // Stocker les données utilisateur
    window.clientId = parseInt(userId);
    window.userType = userType;

    // Configuration Echo
    const echoOptions = {
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        enableLogging: true,
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        },
        authorizer: (channel, options) => {
            return {
                authorize: (socketId, callback) => {
                    console.log(`🔐 Tentative d'autorisation du canal: ${channel.name}`);
                    
                    axios.post('/broadcasting/auth', {
                        socket_id: socketId,
                        channel_name: channel.name
                    }, {
                        headers: {
                            'X-CSRF-TOKEN': getCsrfToken(),
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(response => {
                        console.log(`✅ Autorisation réussie pour ${channel.name}`);
                        callback(null, response.data);
                    })
                    .catch(error => {
                        console.error(`❌ Erreur d'autorisation pour ${channel.name}:`, error);
                        callback(error);
                    });
                }
            };
        }
    };

    // Initialiser Echo
    window.Echo = new Echo(echoOptions);
    console.log('🚀 Echo initialisé avec succès');
} */

function setupEcho() {
    const csrfToken = getCsrfToken();
    const userId = document.querySelector('meta[name="user-id"]')?.getAttribute('content');
    const userType = document.querySelector('meta[name="user-type"]')?.getAttribute('content');

    if (!csrfToken || !userId || !userType) {
        console.warn('Données d\'authentification manquantes, réessai dans 1 seconde...');
        setTimeout(setupEcho, 1000);
        return;
    }

    configureAxios();
    window.Pusher = Pusher;
    window.clientId = parseInt(userId);
    window.userType = userType;

    // Configuration Echo AMÉLIORÉE avec reconnexion automatique
    const echoOptions = {
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        enableLogging: true,
        // AJOUT: Options de reconnexion
        reconnectOnDisconnect: true,
        maxReconnectionAttempts: 5,
        reconnectionDelay: 1000,
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        },
        authorizer: (channel, options) => {
            return {
                authorize: (socketId, callback) => {
                    console.log(`🔐 Tentative d'autorisation du canal: ${channel.name}`);
                    
                    axios.post('/broadcasting/auth', {
                        socket_id: socketId,
                        channel_name: channel.name
                    }, {
                        headers: {
                            'X-CSRF-TOKEN': getCsrfToken(), // Toujours récupérer le token frais
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/json'
                        },
                        timeout: 10000 // AJOUT: Timeout de 10 secondes
                    })
                    .then(response => {
                        console.log(`✅ Autorisation réussie pour ${channel.name}`);
                        callback(null, response.data);
                    })
                    .catch(error => {
                        console.error(`❌ Erreur d'autorisation pour ${channel.name}:`, error);
                        
                        // AJOUT: Retry une fois avec un nouveau token CSRF
                        if (error.response?.status === 419 || error.response?.status === 500) {
                            console.log('🔄 Retry avec nouveau token CSRF...');
                            axios.get('/sanctum/csrf-cookie').then(() => {
                                const newToken = getCsrfToken();
                                if (newToken) {
                                    axios.post('/broadcasting/auth', {
                                        socket_id: socketId,
                                        channel_name: channel.name
                                    }, {
                                        headers: {
                                            'X-CSRF-TOKEN': newToken,
                                            'Accept': 'application/json',
                                            'X-Requested-With': 'XMLHttpRequest',
                                            'Content-Type': 'application/json'
                                        }
                                    })
                                    .then(response => {
                                        console.log(`✅ Autorisation réussie après retry pour ${channel.name}`);
                                        callback(null, response.data);
                                    })
                                    .catch(retryError => {
                                        console.error(`❌ Échec définitif pour ${channel.name}:`, retryError);
                                        callback(retryError);
                                    });
                                } else {
                                    callback(error);
                                }
                            }).catch(() => callback(error));
                        } else {
                            callback(error);
                        }
                    });
                }
            };
        }
    };

    window.Echo = new Echo(echoOptions);
    
    // AJOUT: Gestion des événements de connexion/déconnexion
    if (window.Echo.connector && window.Echo.connector.socket) {
        window.Echo.connector.socket.bind('pusher:connection_established', () => {
            console.log('🟢 WebSocket connecté');
            window.isWebSocketConnected = true;
        });
        
        window.Echo.connector.socket.bind('pusher:error', (error) => {
            console.error('🔴 Erreur WebSocket:', error);
            window.isWebSocketConnected = false;
        });
        
        window.Echo.connector.socket.bind('pusher:disconnected', () => {
            console.warn('🟡 WebSocket déconnecté');
            window.isWebSocketConnected = false;
        });
    }
    
    console.log('🚀 Echo initialisé avec succès');
}
//fin de la fonction setupEcho

// Démarrer l'initialisation
initializeEcho();

// Reconfigurer Axios après chaque navigation
document.addEventListener('inertia:navigate', () => {
    configureAxios();
});

// Fonction utilitaire pour s'abonner aux canaux clients
window.subscribeToClientChannel = function() {
    if (!window.clientId) {
        console.error('❌ Impossible de s\'abonner au canal client: ID client non disponible');
        return null;
    }
    
    const channelName = `client.${window.clientId}`;
    console.log(`📡 Abonnement au canal: ${channelName}`);
    
    return window.Echo.private(channelName)
        .listen('.message.sent', (e) => {
            console.log('💬 Nouveau message reçu:', e);
        })
        .error((error) => {
            console.error(`❌ Erreur sur le canal ${channelName}:`, error);
        });
};

/**
 * Test de connexion Echo
 */
if (window.clientId) {
    setTimeout(() => {
        console.log('🧪 Test de la connexion Echo...');
        try {
            window.Echo.private('test-channel')
                .listen('.test', () => {})
                .error((error) => {
                    console.warn('⚠️ Erreur sur le canal de test (normal):', error);
                });
        } catch (error) {
            console.error('❌ Erreur lors du test Echo:', error);
        }
    }, 1000);
}