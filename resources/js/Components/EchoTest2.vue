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
            </div>
            <div class="text-sm bg-gray-100 p-2 mt-4">
                <pre>{{ debugInfo }}</pre>
            </div>
            <button @click="testConnection" class="mt-2 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                Tester la connexion
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const isConnected = ref(false);
const error = ref(null);
const lastMessage = ref(null);
const debugInfo = ref({});

const testConnection = () => {
    console.log('Test de connexion manuel...');
    try {
        if (!window.Echo) {
            error.value = "Echo n'est pas disponible";
            return;
        }

        console.log('Echo est disponible');
        console.log('Configuration Echo:', {
            broadcaster: window.Echo.options.broadcaster,
            host: window.Echo.options.wsHost,
            port: window.Echo.options.wsPort,
        });

        // Test d'abonnement au canal
        const channel = window.Echo.channel('test-channel');
        console.log('Canal test-channel:', channel);

    } catch (err) {
        console.error('Erreur lors du test de connexion:', err);
        error.value = err.message;
    }
};



onMounted(() => {
    try {
        console.log('Initializing Echo test component');
        debugInfo.value = {
            VITE_REVERB_APP_KEY: import.meta.env.VITE_REVERB_APP_KEY,
            VITE_REVERB_HOST: import.meta.env.VITE_REVERB_HOST,
            VITE_REVERB_PORT: import.meta.env.VITE_REVERB_PORT,
            VITE_REVERB_SCHEME: import.meta.env.VITE_REVERB_SCHEME
        };
        console.log('Debug info:', debugInfo.value);

        if (!window.Echo) {
            error.value = "Echo n'est pas initialisé";
            console.error("Echo n'est pas initialisé");
            return;
        }

        console.log('Echo est initialisé:', window.Echo);
        const channel = window.Echo.channel('test-channel');
        console.log('Canal créé:', channel);

        // Remplacez les deux écouteurs par :
        channel.listen('.TestEvent', (e) => {
            console.log('Received TestEvent:', e);
            lastMessage.value = e.message;
            isConnected.value = true;
        });

        // Écouter tous les événements sur le canal (pour le débogage)
        channel.listenToAll((eventName, e) => {
            console.log(`Received event ${eventName}:`, e);
        });

        console.log('Listening for events on channel:', channel.name);

        // Vérifier périodiquement la connexion
        const checkConnection = setInterval(() => {
            try {
                const channel = window.Echo.connector.channels['test-channel'];
                const wasConnected = isConnected.value;
                isConnected.value = !!channel;

                if (wasConnected !== isConnected.value) {
                    console.log('Connection state changed:', isConnected.value ? 'connected' : 'disconnected');
                }
            } catch (err) {
                console.error('Erreur lors de la vérification de la connexion:', err);
                isConnected.value = false;
            }
        }, 5000);

        // Nettoyage
        onUnmounted(() => {
            console.log('Cleaning up Echo test component');
            clearInterval(checkConnection);
            if (window.Echo) {
                window.Echo.leaveChannel('test-channel');
            }
        });

    } catch (err) {
        console.error('Error in EchoTest component:', err);
        error.value = err.message;
    }
});
</script>