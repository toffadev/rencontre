<template>
    <MainLayout>
        <div class="flex flex-col space-y-4 mb-4">
            <div class="bg-white p-4 rounded-xl shadow-md">
                <h2 class="text-xl font-semibold text-pink-600">Espace Modérateur</h2>
                <p class="text-sm text-gray-600">Vous êtes connecté en tant que modérateur. Le système vous attribue automatiquement un profil et un client à qui répondre.</p>
            </div>
            
            <div v-if="!currentAssignedProfile" class="bg-white p-6 rounded-xl shadow-md text-center">
                <div class="text-lg font-medium text-gray-700">En attente d'attribution...</div>
                <p class="text-gray-500 mt-2">Le système vous attribuera automatiquement un profil pour discuter avec des clients.</p>
                <div class="mt-4">
                    <div class="animate-pulse flex space-x-4 justify-center">
                        <div class="rounded-full bg-pink-200 h-12 w-12"></div>
                        <div class="flex-1 space-y-4 max-w-md">
                            <div class="h-4 bg-pink-200 rounded w-3/4"></div>
                            <div class="h-4 bg-pink-200 rounded w-1/2"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Client attribué (à gauche) -->
            <div class="w-full lg:w-1/3 bg-white rounded-xl shadow-md overflow-hidden p-4">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">Client attribué</h2>
                    <div v-if="assignedClient" class="bg-green-100 text-green-600 px-3 py-1 rounded-full text-sm">
                        En attente de réponse
                    </div>
                    <div v-else class="bg-yellow-100 text-yellow-600 px-3 py-1 rounded-full text-sm">
                        En attente d'attribution
                    </div>
                </div>
                
                <div class="space-y-4">
                    <!-- Client attribué -->
                    <div v-if="assignedClient" class="client-card transition duration-300">
                        <div class="bg-white rounded-lg shadow-sm p-4 flex items-center space-x-3 border border-gray-100 border-l-4 border-pink-500">
                            <div class="relative">
                                <img :src="assignedClient.avatar || 'https://via.placeholder.com/64'" 
                                    :alt="assignedClient.name" 
                                    class="w-12 h-12 rounded-full object-cover">
                                <div class="online-dot"></div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold truncate">{{ assignedClient.name }}</h3>
                                <p class="text-sm text-gray-500">
                                    <span v-if="assignedClient.lastMessage" class="truncate block">{{ assignedClient.lastMessage }}</span>
                                    <span v-else class="text-gray-400 italic">Nouvelle conversation</span>
                                </p>
                            </div>
                            <div v-if="assignedClient.unreadCount" class="bg-pink-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">
                                {{ assignedClient.unreadCount }}
                            </div>
                        </div>
                    </div>
                    
                    <!-- État vide -->
                    <div v-if="!assignedClient" class="text-center py-8">
                        <p class="text-gray-500">Aucun client ne vous a été attribué pour le moment.</p>
                        <p class="text-gray-400 text-sm mt-2">Le système vous attribuera automatiquement un client qui attend une réponse.</p>
                    </div>
                </div>
            </div>
            
            <!-- Chat Section (à droite) -->
            <div class="w-full lg:w-2/3 flex flex-col">
                <!-- Profil attribué (en haut) -->
                <div class="bg-white rounded-xl shadow-md p-4 mb-4">
                    <div class="flex items-center space-x-4">
                        <div v-if="currentAssignedProfile" class="flex items-center space-x-4">
                            <img :src="currentAssignedProfile.main_photo_path || 'https://via.placeholder.com/80'" 
                                :alt="currentAssignedProfile.name" 
                                class="w-16 h-16 rounded-full object-cover border-2 border-pink-500">
                            <div>
                                <div class="flex items-center space-x-2">
                                    <h3 class="font-bold text-lg">{{ currentAssignedProfile.name }}</h3>
                                    <span class="px-2 py-1 bg-pink-100 text-pink-600 text-xs rounded-full">
                                        {{ formatGender(currentAssignedProfile.gender) }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-600">Profil attribué actuellement</p>
                                <div class="flex items-center mt-1">
                                    <span class="inline-block w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                                    <span class="text-xs text-green-600">Profil actif</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="w-full text-center py-4">
                            <p class="text-gray-500">Aucun profil attribué pour le moment</p>
                        </div>
                    </div>
                </div>

                <!-- Zone de chat (en bas) -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden flex-1" v-if="assignedClient">
                    <!-- Chat Header -->
                    <div class="border-b border-gray-200 p-4 flex items-center space-x-3">
                        <div class="relative">
                            <img :src="assignedClient.avatar || 'https://via.placeholder.com/64'" alt="Client" class="w-12 h-12 rounded-full object-cover">
                            <div class="online-dot"></div>
                        </div>
                        <div>
                            <h3 class="font-semibold">{{ assignedClient.name }}</h3>
                            <p class="text-sm text-gray-500">En discussion avec vous</p>
                        </div>
                        <div class="ml-auto flex items-center space-x-2">
                            <div class="text-sm text-gray-500">
                                <span class="font-medium">Client ID:</span> {{ assignedClient.id }}
                            </div>
                            <button class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition">
                                <i class="fas fa-info-circle"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Chat Messages -->
                    <div class="chat-container overflow-y-auto p-4 space-y-3" ref="chatContainer">
                        <!-- Date -->
                        <div class="text-center text-xs text-gray-500 my-4">
                            Aujourd'hui
                        </div>
                        
                        <div v-for="(message, index) in currentChatMessages" :key="index" 
                            :class="`flex space-x-2 ${message.isFromClient ? '' : 'justify-end'}`">
                            <template v-if="message.isFromClient">
                                <img :src="assignedClient.avatar || 'https://via.placeholder.com/64'" alt="Client" 
                                    class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                                <div>
                                    <div class="message-in px-4 py-2 max-w-xs lg:max-w-md">
                                        {{ message.content }}
                                    </div>
                                    <div class="flex items-center mt-1 text-xs text-gray-500">
                                        <span>{{ message.time }}</span>
                                        <span class="mx-1">•</span>
                                        <span>{{ assignedClient.name }}</span>
                                    </div>
                                </div>
                            </template>
                            <template v-else>
                                <div>
                                    <div class="message-out px-4 py-2 max-w-xs lg:max-w-md">
                                        {{ message.content }}
                                    </div>
                                    <div class="flex items-center justify-end mt-1 text-xs text-gray-500">
                                        <span>{{ message.time }}</span>
                                        <span class="mx-1">•</span>
                                        <span>{{ currentAssignedProfile?.name || 'Vous' }}</span>
                                    </div>
                                </div>
                                <img :src="currentAssignedProfile?.main_photo_path || 'https://via.placeholder.com/64'" 
                                    :alt="currentAssignedProfile?.name || 'Profil'" 
                                    class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                            </template>
                        </div>
                    </div>
                    
                    <!-- Message Input -->
                    <div class="border-t border-gray-200 p-4">
                        <div class="flex items-center space-x-2">
                            <button class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition">
                                <i class="fas fa-plus"></i>
                            </button>
                            <input v-model="newMessage" type="text" placeholder="Écrire un message..." 
                                class="flex-1 px-4 py-2 bg-gray-100 rounded-full focus:outline-none focus:ring-2 focus:ring-pink-500" 
                                @keyup.enter="sendMessage">
                            <button class="p-2 rounded-full bg-pink-500 text-white hover:bg-pink-600 transition" 
                                @click="sendMessage" :disabled="!currentAssignedProfile || !assignedClient">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                        <div v-if="!currentAssignedProfile" class="mt-2 text-center text-xs text-red-500">
                            Vous devez avoir un profil attribué pour envoyer des messages
                        </div>
                    </div>
                </div>

                <!-- État vide pour le chat -->
                <div v-else class="bg-white rounded-xl shadow-md p-8 flex-1 flex items-center justify-center">
                    <div class="text-center">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-comments text-5xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-700">En attente d'attribution d'un client</h3>
                        <p class="text-gray-500 mt-2">Les messages apparaîtront ici une fois qu'un client vous sera attribué</p>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<script setup>
import { ref, onMounted, watch, computed, nextTick } from 'vue';
import MainLayout from '@client/Layouts/MainLayout.vue';
import axios from 'axios';
import Echo from 'laravel-echo';

// État des données
const currentAssignedProfile = ref(null);
const assignedClient = ref(null);
const newMessage = ref('');
const chatMessages = ref({});
const chatContainer = ref(null);

// Messages pour la conversation actuelle
const currentChatMessages = computed(() => {
    if (!assignedClient.value) return [];
    return chatMessages.value[assignedClient.value.id] || [];
});

// Charger les données réelles depuis l'API (profil et client attribués)
const loadAssignedData = async () => {
    try {
        // Charger le profil attribué
        const profileResponse = await axios.get('/moderateur/profile');
        if (profileResponse.data.profile) {
            currentAssignedProfile.value = profileResponse.data.profile;
            
            // Charger le client attribué
            const clientsResponse = await axios.get('/moderateur/clients');
            if (clientsResponse.data.clients && clientsResponse.data.clients.length > 0) {
                assignedClient.value = clientsResponse.data.clients[0]; // On prend le premier client attribué
                
                // Charger les messages de la conversation
                await loadMessages(assignedClient.value.id);
            }
        }
    } catch (error) {
        console.error('Erreur lors du chargement des données:', error);
    }
};

// Charger les messages pour un client spécifique
const loadMessages = async (clientId) => {
    try {
        const response = await axios.get('/moderateur/messages', {
            params: { client_id: clientId }
        });
        
        if (response.data.messages) {
            chatMessages.value[clientId] = response.data.messages;
            
            // Faire défiler jusqu'au bas de la conversation
            nextTick(() => {
                if (chatContainer.value) {
                    chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
                }
            });
        }
    } catch (error) {
        console.error('Erreur lors du chargement des messages:', error);
    }
};

// Configurer l'application et charger les données initiales
onMounted(async () => {
    // Charger les données depuis l'API
    await loadAssignedData();
    
    // Si les données ne sont pas disponibles, utiliser des exemples pour la démonstration
    if (!currentAssignedProfile.value) {
        setTimeout(() => {
            // Exemple de profil attribué
            currentAssignedProfile.value = {
                id: 101,
                name: 'Sophie',
                gender: 'female',
                main_photo_path: 'https://randomuser.me/api/portraits/women/33.jpg',
                bio: "J'adore voyager et découvrir de nouvelles cultures.",
            };
            
            // Exemple de client attribué
            assignedClient.value = {
                id: 1,
                name: 'Thomas',
                avatar: 'https://randomuser.me/api/portraits/men/32.jpg',
                lastMessage: "Bonjour, j'aimerais en savoir plus sur vos intérêts",
                unreadCount: 2
            };
            
            // Exemple de messages
            chatMessages.value[1] = [
                { 
                    content: "Bonjour Sophie, je viens de voir ton profil et je suis intéressé par tes voyages.",
                    time: '11:42',
                    isFromClient: true
                },
                {
                    content: "Bonjour Thomas ! Oui, j'aime beaucoup voyager. J'ai visité l'Italie récemment.",
                    time: '11:45',
                    isFromClient: false
                },
                { 
                    content: "Oh, l'Italie ! C'est un pays magnifique, je rêve d'y aller. Quelles villes as-tu visitées ?",
                    time: '11:48',
                    isFromClient: true
                },
                { 
                    content: "Bonjour, j'aimerais en savoir plus sur vos intérêts",
                    time: '12:05',
                    isFromClient: true
                }
            ];
        }, 1500);
    }

    // Configurer Laravel Echo pour les communications en temps réel
    // Exemple de configuration (à adapter selon votre setup)
    // window.Echo = new Echo({
    //     broadcaster: 'pusher',
    //     key: process.env.MIX_PUSHER_APP_KEY,
    //     cluster: process.env.MIX_PUSHER_APP_CLUSTER,
    //     forceTLS: true
    // });
    
    // // Écouter les nouveaux messages
    // if (currentAssignedProfile.value) {
    //     window.Echo.private(`profile.${currentAssignedProfile.value.id}`)
    //         .listen('.message.sent', (data) => {
    //             // Ajouter le nouveau message à la conversation
    //             if (data.is_from_client && assignedClient.value && data.client_id === assignedClient.value.id) {
    //                 addMessageToChat(data);
    //             }
    //         });
    // }
    
    // // Écouter l'attribution d'un nouveau client
    // window.Echo.private(`moderator.${user.id}`)
    //     .listen('.client.assigned', (data) => {
    //         // Mettre à jour le client attribué
    //         assignedClient.value = data.client;
    //         loadMessages(data.client.id);
    //     });
});

// Envoyer un message
async function sendMessage() {
    if (newMessage.value.trim() === '' || !currentAssignedProfile.value || !assignedClient.value) return;
    
    const now = new Date();
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const timeString = `${hours}:${minutes}`;
    
    // Créer le message
    const message = {
        content: newMessage.value,
        time: timeString,
        isFromClient: false
    };
    
    // Ajouter le message à la conversation localement
    if (!chatMessages.value[assignedClient.value.id]) {
        chatMessages.value[assignedClient.value.id] = [];
    }
    
    chatMessages.value[assignedClient.value.id].push(message);
    
    // Mettre à jour le dernier message
    assignedClient.value.lastMessage = newMessage.value;
    
    // Vider le champ de texte
    newMessage.value = '';
    
    try {
        // Envoyer le message au serveur
        await axios.post('/moderateur/send-message', {
            client_id: assignedClient.value.id,
            content: message.content
        });
    } catch (error) {
        console.error('Erreur lors de l\'envoi du message:', error);
    }
    
    // Faire défiler jusqu'au bas de la conversation
    nextTick(() => {
        if (chatContainer.value) {
            chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
        }
    });
}

// Mettre en forme le genre
function formatGender(gender) {
    const genders = {
        'male': 'Homme',
        'female': 'Femme',
        'other': 'Autre'
    };
    return genders[gender] || 'Non spécifié';
}

// Surveiller les nouveaux messages et faire défiler vers le bas
watch(currentChatMessages, () => {
    nextTick(() => {
        if (chatContainer.value) {
            chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
        }
    });
});
</script>

<style scoped>
.client-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}
</style> 