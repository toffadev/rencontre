<template>
    <MainLayout>
        <div class="flex flex-col space-y-4 mb-4">
            <div class="bg-white p-4 rounded-xl shadow-md">
                <h2 class="text-xl font-semibold text-pink-600">Espace Modérateur</h2>
                <p class="text-sm text-gray-600">Vous êtes connecté en tant que modérateur. Vous pouvez discuter avec des clients en utilisant un profil virtuel.</p>
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
            <!-- Clients Section (à gauche) -->
            <div class="w-full lg:w-1/3 bg-white rounded-xl shadow-md overflow-hidden">
                <!-- Tabs -->
                <div class="flex border-b border-gray-200">
                    <button 
                        @click="activeTab = 'assigned'" 
                        :class="['flex-1 py-3 text-sm font-medium', activeTab === 'assigned' ? 'text-pink-600 border-b-2 border-pink-500' : 'text-gray-500 hover:text-gray-700']">
                        Client attribué
                    </button>
                    <button 
                        @click="activeTab = 'available'" 
                        :class="['flex-1 py-3 text-sm font-medium', activeTab === 'available' ? 'text-pink-600 border-b-2 border-pink-500' : 'text-gray-500 hover:text-gray-700']">
                        Clients disponibles
                    </button>
                </div>
                
                <!-- Tab Content: Client attribué -->
                <div v-if="activeTab === 'assigned'" class="p-4">
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
                        <div v-if="assignedClient" class="client-card transition duration-300" @click="selectClient(assignedClient)">
                            <div :class="['bg-white rounded-lg shadow-sm p-4 flex items-center space-x-3 border border-gray-100', 
                                        selectedClient && selectedClient.id === assignedClient.id ? 'border-l-4 border-pink-500' : '']">
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
                            <p class="text-gray-400 text-sm mt-2">Le système vous attribuera automatiquement un client qui attend une réponse, ou consultez les clients disponibles.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Tab Content: Clients disponibles -->
                <div v-if="activeTab === 'available'" class="p-4">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold">Clients disponibles</h2>
                        <button @click="loadAvailableClients" class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <!-- Liste des clients disponibles -->
                        <div v-if="availableClients.length > 0">
                            <div v-for="client in availableClients" :key="client.id" 
                                class="client-card transition duration-300 cursor-pointer"
                                @click="startConversation(client)">
                                <div class="bg-white rounded-lg shadow-sm p-4 flex items-center space-x-3 border border-gray-100 hover:border-pink-200">
                                    <div class="relative">
                                        <img :src="client.avatar || 'https://via.placeholder.com/64'" 
                                            :alt="client.name" 
                                            class="w-12 h-12 rounded-full object-cover">
                                        <div class="online-dot"></div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold truncate">{{ client.name }}</h3>
                                        <p class="text-sm text-gray-500">
                                            <span v-if="client.lastMessage" class="truncate block">{{ client.lastMessage }}</span>
                                            <span v-else-if="client.hasHistory" class="text-gray-400 italic">Conversation précédente</span>
                                            <span v-else class="text-green-500 italic">Nouveau client</span>
                                        </p>
                                        <p class="text-xs text-gray-400 mt-1">
                                            {{ client.lastActivity }}
                                        </p>
                                    </div>
                                    <button class="p-2 rounded-full bg-pink-100 text-pink-600 hover:bg-pink-200 transition">
                                        <i class="fas fa-comments"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- État de chargement -->
                        <div v-else-if="loading" class="py-8">
                            <div class="animate-pulse flex space-x-4 justify-center">
                                <div class="rounded-full bg-pink-200 h-12 w-12"></div>
                                <div class="flex-1 space-y-4 max-w-md">
                                    <div class="h-4 bg-pink-200 rounded w-3/4"></div>
                                    <div class="h-4 bg-pink-200 rounded w-1/2"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- État vide -->
                        <div v-else class="text-center py-8">
                            <p class="text-gray-500">Aucun client disponible pour le moment.</p>
                            <p class="text-gray-400 text-sm mt-2">Réessayez plus tard ou attendez qu'un client soit attribué automatiquement.</p>
                        </div>
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
                <div class="bg-white rounded-xl shadow-md overflow-hidden flex-1" v-if="selectedClient">
                    <!-- Chat Header -->
                    <div class="border-b border-gray-200 p-4 flex items-center space-x-3">
                        <div class="relative">
                            <img :src="selectedClient.avatar || 'https://via.placeholder.com/64'" alt="Client" class="w-12 h-12 rounded-full object-cover">
                            <div class="online-dot"></div>
                        </div>
                        <div>
                            <h3 class="font-semibold">{{ selectedClient.name }}</h3>
                            <p class="text-sm text-gray-500">En discussion avec vous</p>
                        </div>
                        <div class="ml-auto flex items-center space-x-2">
                            <div class="text-sm text-gray-500">
                                <span class="font-medium">Client ID:</span> {{ selectedClient.id }}
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
                                <img :src="selectedClient.avatar || 'https://via.placeholder.com/64'" alt="Client" 
                                    class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                                <div>
                                    <div class="message-in px-4 py-2 max-w-xs lg:max-w-md">
                                        {{ message.content }}
                                    </div>
                                    <div class="flex items-center mt-1 text-xs text-gray-500">
                                        <span>{{ message.time }}</span>
                                        <span class="mx-1">•</span>
                                        <span>{{ selectedClient.name }}</span>
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
                                @click="sendMessage" :disabled="!currentAssignedProfile || !selectedClient">
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
                        <h3 class="text-lg font-medium text-gray-700">Sélectionnez un client pour discuter</h3>
                        <p class="text-gray-500 mt-2">Choisissez un client attribué ou disponible pour commencer une conversation</p>
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
const selectedClient = ref(null);
const availableClients = ref([]);
const newMessage = ref('');
const chatMessages = ref({});
const chatContainer = ref(null);
const loading = ref(false);
const activeTab = ref('assigned');

// Messages pour la conversation actuelle
const currentChatMessages = computed(() => {
    if (!selectedClient.value) return [];
    return chatMessages.value[selectedClient.value.id] || [];
});

// Charger les données réelles depuis l'API (profil et client attribués)
const loadAssignedData = async () => {
    try {
        console.log('Chargement des données du modérateur...');
        
        // Charger le profil attribué
        const profileResponse = await axios.get('/moderateur/profile');
        console.log('Réponse des profils:', profileResponse.data);
        
        if (profileResponse.data.primaryProfile) {
            currentAssignedProfile.value = profileResponse.data.primaryProfile;
            console.log('Profil principal attribué:', currentAssignedProfile.value);
            
            // Charger le client attribué
            const clientsResponse = await axios.get('/moderateur/clients');
            console.log('Réponse des clients:', clientsResponse.data);
            
            if (clientsResponse.data.clients && clientsResponse.data.clients.length > 0) {
                assignedClient.value = clientsResponse.data.clients[0]; // On prend le premier client attribué
                
                // Vérifier si c'est le client actuellement sélectionné
                if (!selectedClient.value || 
                    (selectedClient.value && selectedClient.value.id === assignedClient.value.id)) {
                    selectedClient.value = assignedClient.value; // Sélectionner le client attribué par défaut
                }
                
                // Charger les messages de la conversation si c'est le client sélectionné
                if (selectedClient.value === assignedClient.value) {
                    await loadMessages(assignedClient.value.id);
                }
                
                console.log('Client attribué:', assignedClient.value);
            } else {
                console.log('Aucun client attribué');
                assignedClient.value = null;
            }
        } else {
            console.log('Aucun profil attribué');
            currentAssignedProfile.value = null;
        }
        
        // Charger les clients disponibles
        await loadAvailableClients();
    } catch (error) {
        console.error('Erreur lors du chargement des données:', error);
    }
};

// Charger les clients disponibles
const loadAvailableClients = async () => {
    if (!currentAssignedProfile.value) return;
    
    try {
        loading.value = true;
        const response = await axios.get('/moderateur/available-clients');
        if (response.data.availableClients) {
            availableClients.value = response.data.availableClients;
        }
    } catch (error) {
        console.error('Erreur lors du chargement des clients disponibles:', error);
    } finally {
        loading.value = false;
    }
};

// Sélectionner un client pour discussion
const selectClient = (client) => {
    selectedClient.value = client;
    
    // Charger les messages si nécessaire
    if (!chatMessages.value[client.id]) {
        loadMessages(client.id);
    } else {
        // Faire défiler vers le bas
        nextTick(() => {
            if (chatContainer.value) {
                chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
            }
        });
    }
};

// Démarrer une conversation avec un client disponible
const startConversation = async (client) => {
    try {
        loading.value = true;
        
        // Vérifier qu'un profil est attribué
        if (!currentAssignedProfile.value) {
            console.error('Impossible de démarrer une conversation: aucun profil attribué');
            return;
        }
        
        const profileId = currentAssignedProfile.value.id;
        console.log(`Démarrage d'une conversation avec client_id=${client.id} et profile_id=${profileId}`);
        
        const response = await axios.post('/moderateur/start-conversation', {
            client_id: client.id,
            profile_id: profileId
        });
        
        if (response.data.success) {
            console.log('Conversation démarrée avec succès:', response.data);
            // Stocker les messages
            chatMessages.value[client.id] = response.data.messages;
            
            // Sélectionner ce client
            selectedClient.value = {
                ...client,
                ...response.data.client
            };
            
            // Changer l'onglet
            activeTab.value = 'assigned';
            
            // Faire défiler vers le bas
            nextTick(() => {
                if (chatContainer.value) {
                    chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
                }
            });
        }
    } catch (error) {
        console.error('Erreur lors du démarrage de la conversation:', error);
        console.error('Détails:', {
            status: error.response?.status,
            data: error.response?.data
        });
    } finally {
        loading.value = false;
    }
};

// Charger les messages pour un client spécifique
const loadMessages = async (clientId) => {
    try {
        // Vérifier qu'un profil est bien attribué
        if (!currentAssignedProfile.value) {
            console.error('Impossible de charger les messages: aucun profil attribué');
            return;
        }
        
        const profileId = currentAssignedProfile.value.id;
        console.log(`Chargement des messages pour client_id=${clientId} et profile_id=${profileId}`);
        
        const response = await axios.get('/moderateur/messages', {
            params: { 
                client_id: clientId,
                profile_id: profileId 
            }
        });
        
        if (response.data.messages) {
            console.log(`${response.data.messages.length} messages chargés`);
            chatMessages.value[clientId] = response.data.messages;
            
            // Faire défiler jusqu'au bas de la conversation
            nextTick(() => {
                if (chatContainer.value) {
                    chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
                }
            });
        } else {
            console.log('Aucun message trouvé');
        }
    } catch (error) {
        console.error('Erreur lors du chargement des messages:', error);
        console.error('Détails:', {
            status: error.response?.status,
            data: error.response?.data
        });
    }
};

// Configurer l'application et charger les données initiales
onMounted(async () => {
    // Charger les données depuis l'API
    await loadAssignedData();

    // Configurer Laravel Echo pour les communications en temps réel
    if (window.Echo) {
        console.log('Configuration de Laravel Echo pour recevoir les notifications en temps réel');
        
        const moderatorId = window.clientId;
        console.log(`ID du modérateur connecté: ${moderatorId}`);
        
        // Écouter les notifications d'attribution de profil
        console.log(`Souscription au canal: moderator.${moderatorId}`);
        
        window.Echo.private(`moderator.${moderatorId}`)
            .listen('.profile.assigned', (data) => {
                console.log('Événement profile.assigned reçu:', data);
                // Recharger les données après l'attribution d'un profil
                loadAssignedData();
            })
            .listen('.client.assigned', (data) => {
                console.log('Événement client.assigned reçu:', data);
                // Recharger les clients après l'attribution d'un client
                loadAssignedData();
            })
            .error((error) => {
                console.error(`Erreur sur le canal moderator.${moderatorId}:`, error);
            });
        
        // Si un profil est déjà attribué, écouter les messages sur son canal
        if (currentAssignedProfile.value) {
            listenToProfileMessages(currentAssignedProfile.value.id);
        }
    } else {
        console.error('Laravel Echo n\'est pas disponible, les notifications en temps réel ne fonctionneront pas');
    }
});

// Fonction pour configurer l'écoute des messages d'un profil spécifique
const listenToProfileMessages = (profileId) => {
    console.log(`Écoute des messages pour le profil ${profileId}`);
    console.log(`Souscription au canal: profile.${profileId}`);
    
    window.Echo.private(`profile.${profileId}`)
        .listen('.message.sent', (data) => {
            console.log('Nouveau message reçu sur le canal profile:', data);
            // Ajouter le nouveau message à la conversation
            if (data.is_from_client) {
                const clientId = data.client_id;
                
                // Formater le message
                const message = {
                    id: data.id,
                    content: data.content,
                    isFromClient: true,
                    time: new Date(data.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})
                };
                
                // Ajouter à la conversation
                if (!chatMessages.value[clientId]) {
                    chatMessages.value[clientId] = [];
                    console.log(`Initialisation d'une nouvelle conversation pour le client ${clientId}`);
                }
                
                chatMessages.value[clientId].push(message);
                console.log(`Message ajouté à la conversation du client ${clientId}`);
                
                // Mettre à jour la liste des clients (peut-être un nouveau client)
                loadAssignedData();
                
                // Faire défiler si c'est la conversation actuelle
                if (selectedClient.value && selectedClient.value.id === clientId) {
                    nextTick(() => {
                        if (chatContainer.value) {
                            chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
                        }
                    });
                }
            } else {
                console.log('Message ignoré car non provenant d\'un client');
            }
        })
        .error((error) => {
            console.error(`Erreur sur le canal profile.${profileId}:`, error);
        });
};

// Surveiller les changements du profil pour reconfigurer l'écoute des messages
watch(currentAssignedProfile, (newProfile) => {
    if (newProfile && window.Echo) {
        listenToProfileMessages(newProfile.id);
    }
});

// Envoyer un message
async function sendMessage() {
    if (newMessage.value.trim() === '' || !currentAssignedProfile.value || !selectedClient.value) return;
    
    const now = new Date();
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const timeString = `${hours}:${minutes}`;
    
    // Créer le message local pour une UX plus réactive
    const localMessage = {
        id: 'temp-' + Date.now(),
        content: newMessage.value,
        time: timeString,
        isFromClient: false,
        date: new Date().toISOString().split('T')[0],
    };
    
    // Ajouter le message à la conversation localement
    if (!chatMessages.value[selectedClient.value.id]) {
        chatMessages.value[selectedClient.value.id] = [];
    }
    
    chatMessages.value[selectedClient.value.id].push(localMessage);
    
    // Mettre à jour le dernier message s'il s'agit du client attribué
    if (assignedClient.value && assignedClient.value.id === selectedClient.value.id) {
        assignedClient.value.lastMessage = newMessage.value;
    }
    
    // Vider le champ de texte
    newMessage.value = '';
    
    try {
        console.log('Envoi du message au serveur:', {
            client_id: selectedClient.value.id,
            profile_id: currentAssignedProfile.value.id,
            content: localMessage.content
        });
        
        // Envoyer le message au serveur
        const response = await axios.post('/moderateur/send-message', {
            client_id: selectedClient.value.id,
            profile_id: currentAssignedProfile.value.id,
            content: localMessage.content
        });
        
        console.log('Message envoyé avec succès:', response.data);
    } catch (error) {
        console.error('Erreur lors de l\'envoi du message:', error);
        console.error('Détails:', {
            status: error.response?.status,
            data: error.response?.data
        });
        
        // Marquer le message comme échoué
        const index = chatMessages.value[selectedClient.value.id].findIndex(msg => msg.id === localMessage.id);
        if (index !== -1) {
            chatMessages.value[selectedClient.value.id][index].failed = true;
        }
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

// Surveiller les onglets pour recharger les données si nécessaire
watch(activeTab, (newTab) => {
    if (newTab === 'available') {
        loadAvailableClients();
    }
});

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

.chat-container {
    height: 400px;
    overflow-y: auto;
}

.message-in {
    background-color: #f3f4f6;
    border-radius: 18px 18px 18px 4px;
}

.message-out {
    background-color: #ec4899;
    color: white;
    border-radius: 18px 18px 4px 18px;
}

.online-dot {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 12px;
    height: 12px;
    background-color: #10b981;
    border-radius: 50%;
    border: 2px solid white;
}
</style> 