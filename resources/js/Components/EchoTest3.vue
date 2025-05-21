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

const clearLastMessage = () => {
    lastMessage.value = null;
    lastMessageTime.value = null;
};

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

        // Faire un test en envoyant manuellement un événement au serveur
        console.log('Envoi d\'une requête au serveur pour tester...');
        fetch('/api/test-event', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify({ message: 'Test depuis le frontend à ' + new Date().toLocaleTimeString() })
        })
            .then(response => response.json())
            .then(data => {
                console.log('Événement envoyé avec succès, attendez la réponse WebSocket:', data);
            })
            .catch(err => {
                console.error('Erreur lors de l\'envoi de l\'événement:', err);
                error.value = 'Erreur lors de l\'envoi: ' + err.message;
            });

    } catch (err) {
        console.error('Erreur lors du test de connexion:', err);
        error.value = err.message;
    }
};

// Fonction pour setup le canal et les écouteurs
const setupChannel = () => {
    try {
        if (!window.Echo) {
            error.value = "Echo n'est pas disponible";
            return;
        }

        console.log("Mise en place des écouteurs d'événements...");
        const channel = window.Echo.channel('test-channel');

        // Écouteur avec le point (comme dans votre code d'origine)
        channel.listen('.TestEvent', (e) => {
            console.log('✅ Received .TestEvent with dot:', e);
            lastMessage.value = e.message || JSON.stringify(e);
            lastMessageTime.value = new Date().toLocaleTimeString();
            isConnected.value = true;
        });

        // Écouteur sans le point (pour tester l'autre format)
        channel.listen('TestEvent', (e) => {
            console.log('✅ Received TestEvent without dot:', e);
            lastMessage.value = e.message || JSON.stringify(e);
            lastMessageTime.value = new Date().toLocaleTimeString();
            isConnected.value = true;
        });

        // Écouteur générique pour tous les événements
        channel.listenToAll((eventName, e) => {
            console.log(`👂 Received ANY event [${eventName}]:`, e);
        });

        console.log('Listening for events on channel:', channel.name);
    } catch (err) {
        console.error('Erreur lors de la mise en place du canal:', err);
        error.value = 'Erreur de setup: ' + err.message;
    }
};

onMounted(() => {
    try {
        console.log('Initializing Echo test component');
        debugInfo.value = {
            VITE_REVERB_APP_KEY: import.meta.env.VITE_REVERB_APP_KEY,
            VITE_REVERB_HOST: import.meta.env.VITE_REVERB_HOST,
            VITE_REVERB_PORT: import.meta.env.VITE_REVERB_PORT,
            VITE_REVERB_SCHEME: import.meta.env.VITE_REVERB_SCHEME,
            appUrl: window.location.origin
        };
        console.log('Debug info:', debugInfo.value);

        if (!window.Echo) {
            error.value = "Echo n'est pas initialisé";
            console.error("Echo n'est pas initialisé");
            return;
        }

        console.log('Echo est initialisé:', window.Echo);

        // Mettre en place le canal et les écouteurs
        setupChannel();

        // Vérifier périodiquement la connexion (comme dans votre code d'origine)
        const checkConnection = setInterval(() => {
            try {
                const channel = window.Echo.connector.channels['test-channel'];
                const wasConnected = isConnected.value;
                isConnected.value = !!channel;

                if (wasConnected !== isConnected.value) {
                    console.log('Connection state changed:', isConnected.value ? 'connected' : 'disconnected');

                    // Si on détecte une reconnexion, remettre en place les écouteurs
                    if (isConnected.value && !wasConnected) {
                        console.log('Reconnexion détectée, réinitialisation des écouteurs');
                        setupChannel();
                    }
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