import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Configuration d'Axios
 */
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Configuration de Pusher
 */
window.Pusher = Pusher;

// Créer un événement personnalisé pour signaler que Echo est prêt
window.echoReady = false;

/**
 * Fonction pour obtenir le token CSRF
 */
function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
}

/**
 * Configurer Axios avec le token CSRF
 */
function configureAxios() {
    const token = getCsrfToken();
    if (token) {
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        window.axios.defaults.withCredentials = true;
    }
}

configureAxios();

/**
 * Configurer l'intercepteur Axios pour gérer les erreurs CSRF
 */
window.axios.interceptors.response.use(
    response => response,
    async error => {
        if (error.response?.status === 419) {
            try {
                await window.axios.get('/sanctum/csrf-cookie');
                const newToken = getCsrfToken();
                if (newToken) {
                    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = newToken;
                    error.config.headers['X-CSRF-TOKEN'] = newToken;
                    return window.axios(error.config);
                }
            } catch (refreshError) {
                console.error('Erreur lors du rafraîchissement du token CSRF:', refreshError);
            }
        }
        return Promise.reject(error);
    }
);

/**
 * Initialisation d'Echo avec Pusher
 */
// Au lieu d'attendre le DOMContentLoaded, utiliser une approche plus proactive
let echoInitAttempts = 0;
const maxEchoInitAttempts = 5;

function setupEcho() {
    try {
        echoInitAttempts++;
        console.log(`🚀 Tentative d'initialisation d'Echo (${echoInitAttempts}/${maxEchoInitAttempts})...`);
        
        // Vérifier si nous sommes sur une page d'authentification
        const isAuthPage = window.location.pathname.includes('/login') || 
                          window.location.pathname.includes('/register') ||
                          window.location.pathname === '/welcome';
        
        if (isAuthPage) {
            console.log('📝 Page d\'authentification détectée, initialisation d\'Echo ignorée');
            return false;
        }
        
        // Récupérer les informations utilisateur de toutes les sources possibles
        let userInfo = null;
        
        // 1. Vérifier d'abord les props Inertia (le plus fiable après login)
        if (window.page && window.page.props && window.page.props.auth && window.page.props.auth.user) {
            userInfo = window.page.props.auth.user;
            console.log('✅ Utilisateur trouvé dans les props Inertia:', userInfo);
        }
        // 2. Ensuite vérifier window.Laravel
        else if (window.Laravel && window.Laravel.user) {
            userInfo = window.Laravel.user;
            console.log('✅ Utilisateur trouvé dans window.Laravel:', userInfo);
        }
        // 3. Enfin vérifier les meta tags
        else {
            const userId = document.querySelector('meta[name="user-id"]')?.getAttribute('content');
            const userType = document.querySelector('meta[name="user-type"]')?.getAttribute('content');
            
            if (userId && userType) {
                userInfo = { id: userId, type: userType };
                console.log('✅ Utilisateur trouvé dans les meta tags:', userInfo);
            }
        }
        
        // Si aucune information utilisateur n'est disponible et que nous n'avons pas atteint le max de tentatives
        if (!userInfo) {
            if (echoInitAttempts < maxEchoInitAttempts) {
                console.warn(`⚠️ Informations utilisateur non disponibles, nouvelle tentative dans 300ms (${echoInitAttempts}/${maxEchoInitAttempts})...`);
                setTimeout(setupEcho, 300);
                return false;
            } else {
                console.error('❌ Impossible de récupérer les informations utilisateur après plusieurs tentatives');
                return false;
            }
        }
        
        // Récupérer le token CSRF
        const csrfToken = getCsrfToken();
        if (!csrfToken) {
            console.warn('⚠️ Token CSRF non disponible');
            if (echoInitAttempts < maxEchoInitAttempts) {
                setTimeout(setupEcho, 300);
                return false;
            } else {
                return false;
            }
        }
        
        // Configurer Axios avec le token CSRF
        configureAxios();
        
        // Synchroniser les données utilisateur dans window.Laravel pour cohérence
        if (!window.Laravel) window.Laravel = {};
        window.Laravel.user = window.Laravel.user || userInfo;
        
        // Définir les variables globales
        window.clientId = parseInt(userInfo.id);
        window.userType = userInfo.type;
        
        // Configuration Echo avec Pusher
        const echoConfig = {
            broadcaster: 'pusher',
            key: '6ae46164b8889f3914b1', // Votre clé Pusher
            cluster: 'eu',
            forceTLS: true,
            encrypted: true,
            auth: {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            },
            authEndpoint: '/broadcasting/auth',
        };
        
        console.log('Configuration Echo avec Pusher:', echoConfig);
        window.Echo = new Echo(echoConfig);
        
        // Signaler que Echo est initialisé
        document.dispatchEvent(new CustomEvent('echo:initialized'));
        
        // Configurer les gestionnaires d'événements
        if (window.Echo.connector && window.Echo.connector.pusher) {
            window.Echo.connector.pusher.connection.bind('connected', () => {
                console.log('🟢 WebSocket connecté via Pusher');
                window.isWebSocketConnected = true;
                window.echoReady = true;
                document.dispatchEvent(new CustomEvent('echo:connected'));
            });
            
            window.Echo.connector.pusher.connection.bind('disconnected', () => {
                console.log('🔴 WebSocket déconnecté');
                window.isWebSocketConnected = false;
                window.echoReady = false;
                window.dispatchEvent(new Event('echo:disconnected'));
            });
            
            window.Echo.connector.pusher.connection.bind('error', (error) => {
                console.error('❌ Erreur de connexion Pusher:', error);
            });
        }
        
        console.log('✅ Echo initialisé avec Pusher, test de connexion...');
        window.isWebSocketConnected = true;
        window.echoReady = true;
        window.dispatchEvent(new Event('echo:initialized'));
        
        return true;
    } catch (error) {
        console.error('❌ Erreur lors de l\'initialisation d\'Echo avec Pusher:', error);
        if (echoInitAttempts < maxEchoInitAttempts) {
            setTimeout(setupEcho, 500);
        }
        return false;
    }
}

// Initialiser Echo dès que possible
setupEcho();

// Ajouter un événement pour Inertia.js qui se déclenche après chaque navigation
document.addEventListener('inertia:success', () => {
    // Réinitialiser le compteur et réessayer l'initialisation d'Echo
    echoInitAttempts = 0;
    if (!window.Echo) {
        console.log('🔄 Page Inertia chargée, tentative d\'initialisation d\'Echo...');
        setupEcho();
    }
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
        });
};

// Tester la connexion Pusher
setTimeout(() => {
    if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
        console.log('État de la connexion Pusher:', window.Echo.connector.pusher.connection.state);
    } else {
        console.warn('La connexion Pusher n\'est pas disponible');
    }
}, 3000);