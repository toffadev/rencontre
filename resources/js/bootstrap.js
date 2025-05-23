import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo expose an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to easily build robust real-time web applications.
 */

// Nous devons d'abord définir la classe Pusher globalement
window.Pusher = Pusher;

// Options de débogage pour mieux comprendre les problèmes WebSocket
const options = {
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: import.meta.env.VITE_REVERB_PORT || 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    // Activer les logs de debug
    enableLogging: true,
    authorizer: (channel, options) => {
        return {
            authorize: (socketId, callback) => {
                console.log(`Tentative d'autorisation du canal: ${channel.name} avec socketId: ${socketId}`);
                
                axios.post('/broadcasting/auth', {
                    socket_id: socketId,
                    channel_name: channel.name
                })
                .then(response => {
                    console.log(`Autorisation réussie pour le canal: ${channel.name}`, response.data);
                    callback(null, response.data);
                })
                .catch(error => {
                    console.error(`Erreur d'autorisation pour le canal: ${channel.name}`, error);
                    callback(error);
                });
            }
        };
    }
};

console.log('Configuration Echo avec les options:', options);
window.Echo = new Echo(options);

/**
 * Store client ID for use with Echo private channels
 */
window.clientId = document.querySelector('meta[name="client-id"]')?.getAttribute('content');
console.log(`Client ID récupéré: ${window.clientId || 'Non défini'}`);
