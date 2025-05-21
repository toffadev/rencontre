<template>
    <div class="p-4 bg-white rounded shadow">
        <h3 class="text-lg font-semibold mb-4">Test de connexion Echo</h3>
        <div class="space-y-2">
            <div class="text-sm">
                Status:
                <span :class="{ 'text-green-600': isConnected, 'text-red-600': !isConnected }">
                    {{ isConnected ? 'Connecté' : 'Déconnecté' }}
                </span>
            </div>
            <div v-if="error" class="text-red-600 text-sm">
                Erreur: {{ error }}
            </div>
            <div v-if="lastMessage" class="text-sm bg-gray-50 p-2 rounded">
                Dernier message reçu: {{ lastMessage }}
                <span class="text-xs text-gray-500 block mt-1">Reçu à: {{ lastMessageTime }}</span>
            </div>
            <div class="text-sm bg-gray-100 p-2 mt-4">
                <pre>{{ debugInfo }}</pre>
            </div>
            <div class="flex space-x-2">
                <button @click="testConnection" class="mt-2 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                    Tester la connexion
                </button>
                <button @click="clearLastMessage"
                    class="mt-2 px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
                    Effacer message
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const isConnected = ref(false);
const error = ref(null);
const lastMessage = ref(null);
const lastMessageTime = ref(null);
const debugInfo = ref({});

// Fonction simplifiée pour effacer les messages
const clearLastMessage = () => {
    lastMessage.value = null;
    lastMessageTime.value = null;
};

// Nouvelle version optimisée de testConnection
const testConnection = async () => {
    try {
        console.log('Initialisation du test de connexion...');

        if (!window.Echo) {
            throw new Error("Echo n'est pas disponible");
        }

        console.log('Configuration Echo actuelle:', window.Echo.options);

        // Envoi de test au serveur
        const response = await fetch('/api/test-event', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            },
            body: JSON.stringify({
                message: 'Test de connexion à ' + new Date().toLocaleTimeString()
            })
        });

        const data = await response.json();
        console.log('Réponse du serveur:', data);

    } catch (err) {
        console.error('Erreur de test:', err);
        error.value = err.message;
    }
};

// Nouvelle version de setupChannel optimisée
const setupChannel = () => {
    try {
        console.log("Initialisation du canal WebSocket...");

        // Nettoyage préalable des anciennes connexions
        if (window.Echo?.connector?.channels['test-channel']) {
            window.Echo.leaveChannel('test-channel');
        }

        // Configuration améliorée de Echo (si pas déjà fait)
        if (!window.Echo) {
            window.Echo = new Echo({
                broadcaster: 'reverb',
                key: import.meta.env.VITE_REVERB_APP_KEY,
                wsHost: window.location.hostname,
                wsPort: parseInt(import.meta.env.VITE_REVERB_PORT || '8002'),
                forceTLS: false,
                enabledTransports: ['ws'],
                auth: {
                    headers: {
                        'X-Socket-ID': window.Echo?.socketId() || '',
                    },
                },
            });
        
            /* window.Echo = new Echo({
                broadcaster: 'pusher',
                key: import.meta.env.VITE_PUSHER_APP_KEY,
                cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
                forceTLS: true
            }); */
        }

        const channel = window.Echo.channel('test-channel');

        // Écouteur principal
        channel.listen('.TestEvent', (payload) => {
            console.log('🟢 Message reçu:', payload);
            lastMessage.value = payload.message;
            lastMessageTime.value = new Date().toLocaleTimeString();
            isConnected.value = true;
        });

        /* channel.listen('.TestEvent', (e) => {
            console.log('🚀 Événement TestEvent reçu directement :', e);
        }); */

        channel.listenForWhisper('debug', (data) => {
            console.log('👀 Whisper reçu :', data);
        });
        
        // Gestion des erreurs améliorée
        channel.error((error) => {
            console.error('🔴 Erreur WebSocket:', error);
            error.value = error.type + ': ' + error.message;
            isConnected.value = false;
        });

        // Surveillance de la connexion
        window.Echo.connector.pusher.connection.bind('state_change', (states) => {
            console.log('État connexion:', states.current);
            isConnected.value = states.current === 'connected';
            
            if (states.current === 'connected') {
                // Réabonnement automatique après reconnexion
                setupChannel();
            }
        });

        console.log('✅ Canal initialisé avec succès');

    } catch (err) {
        console.error('❌ Erreur critique:', err);
        error.value = 'Erreur initialisation: ' + err.message;
        isConnected.value = false;
    }
};

// Lifecycle hooks
onMounted(() => {
    console.log('Montage du composant EchoTest');

    debugInfo.value = {
        host: import.meta.env.VITE_REVERB_HOST,
        port: import.meta.env.VITE_REVERB_PORT,
        scheme: import.meta.env.VITE_REVERB_SCHEME,
        lastPing: new Date().toISOString()
    };

    if (!window.Echo) {
        error.value = "Echo n'est pas initialisé";
        return;
    }

    // Configuration des écouteurs
    setupChannel();

    // Surveillance de la connexion
    const connectionCheck = setInterval(() => {
        isConnected.value = !!window.Echo.connector.channels['test-channel'];
    }, 3000);

    onUnmounted(() => {
        clearInterval(connectionCheck);
        window.Echo?.leaveChannel('test-channel');
        console.log('Démontage du composant EchoTest');
    });
});
</script>