import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';


/**
 * Configuration d'Axios avec le token CSRF
 */
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Récupérer le token CSRF depuis la meta tag
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

/**
 * Configuration de Pusher globalement
 */
window.Pusher = Pusher;

/**
 * Récupération sécurisée des données utilisateur
 */
function getUserData() {
    // Méthode 1: Depuis window.Laravel (défini dans app.blade.php)
    if (window.Laravel && window.Laravel.user) {
        return window.Laravel.user;
    }
    
    // Méthode 2: Depuis les meta tags
    const userId = document.querySelector('meta[name="user-id"]')?.getAttribute('content');
    const userType = document.querySelector('meta[name="user-type"]')?.getAttribute('content');
    
    if (userId && userType) {
        return { 
            id: parseInt(userId), 
            type: userType 
        };
    }
    
    return null;
}

const userData = getUserData();
console.log('Données utilisateur récupérées:', userData);

/**
 * Configuration Echo avec autorisation personnalisée
 */
const echoOptions = {
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    enableLogging: true,
    
    // Configuration d'autorisation personnalisée
    authorizer: (channel, options) => {
        return {
            authorize: (socketId, callback) => {
                console.log(`🔐 Autorisation du canal: ${channel.name} avec socketId: ${socketId}`);
                
                // Vérifier que l'utilisateur est connecté
                if (!userData || !userData.id) {
                    console.error('❌ Utilisateur non authentifié');
                    callback(new Error('Utilisateur non authentifié'));
                    return;
                }
                
                // Préparer les données d'autorisation
                const authData = {
                    socket_id: socketId,
                    channel_name: channel.name
                };
                
                console.log('📤 Envoi des données d\'autorisation:', authData);
                
                axios.post('/broadcasting/auth', authData, {
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    console.log(`✅ Autorisation réussie pour ${channel.name}:`, response.data);
                    callback(null, response.data);
                })
                .catch(error => {
                    console.error(`❌ Erreur d'autorisation pour ${channel.name}:`, {
                        status: error.response?.status,
                        data: error.response?.data,
                        message: error.message
                    });
                    callback(error);
                });
            }
        };
    }
};

console.log('🚀 Initialisation d\'Echo avec les options:', echoOptions);
window.Echo = new Echo(echoOptions);

/**
 * Stockage des données utilisateur pour utilisation globale
 */
if (userData) {
    window.clientId = userData.id;
    window.userType = userData.type;
    console.log(`👤 Utilisateur connecté - ID: ${userData.id}, Type: ${userData.type}`);
} else {
    console.warn('⚠️ Aucune donnée utilisateur trouvée');
}

/**
 * Fonction utilitaire pour s'abonner aux canaux clients
 */
window.subscribeToClientChannel = function() {
    if (!userData || !userData.id) {
        console.error('❌ Impossible de s\'abonner au canal client: utilisateur non authentifié');
        return null;
    }
    
    const channelName = `client.${userData.id}`;
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
if (userData) {
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