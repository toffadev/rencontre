import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const initEcho = () => {
    try {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST || '127.0.0.1',
            wsPort: parseInt(import.meta.env.VITE_REVERB_PORT || '8002'),
            forceTLS: false,
            encrypted: false,
            enabledTransports: ['ws'],
            disableStats: true,
            cluster: null,
            namespace: '',
        });

        // Add debug listeners
        if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
            window.Echo.connector.pusher.connection.bind('connected', () => {
                console.log('✅ Echo connection established successfully');
            });

            window.Echo.connector.pusher.connection.bind('error', (error) => {
                console.error('❌ Echo connection error:', error);
            });
        }

        console.log('Echo initialized with config:', {
            key: import.meta.env.VITE_REVERB_APP_KEY,
            host: import.meta.env.VITE_REVERB_HOST,
            port: import.meta.env.VITE_REVERB_PORT,
            scheme: import.meta.env.VITE_REVERB_SCHEME
        });

    } catch (error) {
        console.error('Failed to initialize Echo:', error);
    }
};

// Initialiser Echo après que le DOM est chargé
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEcho);
} else {
    initEcho();
}

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
