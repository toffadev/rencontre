<template>
    <MainLayout>
        <div class="flex flex-col gap-6">
            <!-- Points Alert -->
            <div
                v-if="showPointsAlert"
                class="fixed top-4 right-4 bg-pink-100 border border-pink-400 text-pink-700 px-4 py-3 rounded-lg shadow-lg z-50"
            >
                <div class="flex items-center">
                    <div class="py-1">
                        <svg
                            class="fill-current h-6 w-6 text-pink-500 mr-4"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                        >
                            <path
                                d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM9 11V9h2v6H9v-4zm0-6h2v2H9V5z"
                            />
                        </svg>
                    </div>
                    <div>
                        <p class="font-bold">Points insuffisants</p>
                        <p class="text-sm">
                            Vous n'avez plus assez de points pour envoyer des
                            messages.
                        </p>
                        <button
                            @click="redirectToProfile"
                            class="mt-2 bg-pink-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-pink-600 transition"
                        >
                            Acheter des points
                        </button>
                    </div>
                    <button @click="showPointsAlert = false" class="ml-4">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Carousel des profils -->
            <ProfileCarousel
                :profiles="profiles"
                @showActions="showProfileActions"
            />

            <!-- Section principale -->
            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Liste des conversations -->
                <ActiveConversations
                    :profiles="filteredProfiles"
                    :selected-profile="selectedProfile"
                    :messages="messagesMap"
                    :remaining-points="remainingPoints"
                    :conversation-states="conversationStates"
                    @select="selectProfile"
                    @buyPoints="redirectToProfile"
                    @buyPointsForProfile="buyPointsForProfile"
                    @report="showReportModal"
                />

                <!-- Chat Section -->
                <div
                    v-if="selectedProfile"
                    class="w-full lg:w-2/3 bg-white rounded-xl shadow-md overflow-hidden"
                >
                    <!-- Chat Header -->
                    <div
                        class="border-b border-gray-200 p-4 flex items-center justify-between"
                    >
                        <!-- Left side - Selected Profile Info -->
                        <div class="flex items-center space-x-3">
                            <div class="relative">
                                <img
                                    :src="
                                        selectedProfile?.main_photo_path ||
                                        'https://via.placeholder.com/64'
                                    "
                                    :alt="selectedProfile?.name"
                                    class="w-12 h-12 rounded-full object-cover"
                                />
                                <div class="online-dot"></div>
                            </div>
                            <div>
                                <h3 class="font-semibold">
                                    {{ selectedProfile?.name }}
                                </h3>
                                <p class="text-sm text-gray-500">
                                    En ligne maintenant
                                </p>
                            </div>
                            <!-- Ajout du bouton d'achat de points -->
                            <button
                                @click="buyPointsForProfile"
                                class="ml-4 px-4 py-2 bg-pink-500 text-white rounded-lg text-sm hover:bg-pink-600 transition flex items-center"
                            >
                                <i class="fas fa-coins mr-2"></i>
                                Acheter des points a votre interlocuteur
                            </button>
                        </div>

                        <!-- Right side - Current User Info and Actions -->
                        <div class="flex items-center space-x-4">
                            <!-- Current User Info -->
                            <div class="flex items-center">
                                <div class="text-right mr-3">
                                    <p class="font-semibold">
                                        {{ auth?.user?.name }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ remainingPoints }} points disponibles
                                    </p>
                                </div>
                                <div class="relative">
                                    <img
                                        v-if="auth?.user?.profile_photo_url"
                                        :src="auth.user.profile_photo_url"
                                        :alt="auth.user.name"
                                        class="w-10 h-10 rounded-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center"
                                    >
                                        <i
                                            class="fas fa-user text-gray-400"
                                        ></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex space-x-2">
                                <button
                                    class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition"
                                    title="Appel audio"
                                >
                                    <i class="fas fa-phone-alt"></i>
                                </button>
                                <button
                                    class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition"
                                    title="Appel vidéo"
                                >
                                    <i class="fas fa-video"></i>
                                </button>
                                <button
                                    class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition"
                                    title="Plus d'options"
                                >
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Messages -->
                    <div
                        class="chat-container overflow-y-auto p-4 space-y-3"
                        ref="chatContainer"
                    >
                        <!-- Messages groupés par date -->
                        <div v-if="currentMessages.length">
                            <div
                                v-for="(
                                    messagesForDate, date
                                ) in groupedMessages"
                                :key="date"
                            >
                                <!-- En-tête de date -->
                                <div
                                    class="text-center text-xs text-gray-500 my-4"
                                >
                                    {{ formatDate(date) }}
                                </div>

                                <!-- Messages pour cette date -->
                                <div
                                    v-for="message in messagesForDate"
                                    :key="message.id"
                                    :class="`flex space-x-2 mb-3 ${
                                        message.isOutgoing ? 'justify-end' : ''
                                    }`"
                                >
                                    <template v-if="!message.isOutgoing">
                                        <div class="relative">
                                            <img
                                                v-if="
                                                    selectedProfile.main_photo_path
                                                "
                                                :src="
                                                    selectedProfile.main_photo_path
                                                "
                                                :alt="selectedProfile.name"
                                                class="w-8 h-8 rounded-full object-cover flex-shrink-0"
                                            />
                                            <div
                                                v-else
                                                class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center"
                                            >
                                                <i
                                                    class="fas fa-user text-gray-400"
                                                ></i>
                                            </div>
                                        </div>
                                    </template>
                                    <div>
                                        <div
                                            :class="`${
                                                message.isOutgoing
                                                    ? 'message-out'
                                                    : 'message-in'
                                            } px-4 py-2 max-w-xs lg:max-w-md ${
                                                message.pending ? 'pending' : ''
                                            } ${
                                                message.failed ? 'failed' : ''
                                            }`"
                                        >
                                            <!-- Contenu du message -->
                                            <div v-if="message.content" class="mb-2">{{ message.content }}</div>
                                            
                                            <!-- Image attachée -->
                                            <div v-if="message.attachment && message.attachment.mime_type.startsWith('image/')" class="mt-2">
                                                <img
                                                    :src="message.attachment.url"
                                                    :alt="message.attachment.file_name"
                                                    class="max-w-full rounded-lg cursor-pointer"
                                                    @click="showImagePreview(message.attachment)"
                                                />
                                            </div>

                                            <span
                                                v-if="message.pending"
                                                class="ml-2 inline-block text-xs"
                                                >⌛</span
                                            >
                                            <span
                                                v-if="message.failed"
                                                class="ml-2 inline-block text-xs"
                                                >❌</span
                                            >
                                        </div>
                                        <div
                                            class="flex items-center mt-1 text-xs text-gray-500"
                                            :class="{
                                                'justify-end':
                                                    message.isOutgoing,
                                            }"
                                        >
                                            <span class="font-medium mr-2">{{
                                                message.isOutgoing
                                                    ? auth?.user?.name || "Vous"
                                                    : selectedProfile.name
                                            }}</span>
                                            <span>{{ message.time }}</span>
                                        </div>
                                    </div>
                                    <template v-if="message.isOutgoing">
                                        <div class="relative">
                                            <img
                                                v-if="
                                                    auth?.user
                                                        ?.profile_photo_url
                                                "
                                                :src="
                                                    auth.user.profile_photo_url
                                                "
                                                :alt="auth.user.name"
                                                class="w-8 h-8 rounded-full object-cover flex-shrink-0"
                                            />
                                            <div
                                                v-else
                                                class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center"
                                            >
                                                <i
                                                    class="fas fa-user text-gray-400"
                                                ></i>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- État vide -->
                        <div v-else class="text-center py-8">
                            <p class="text-gray-500">
                                Aucun message dans cette conversation.
                            </p>
                            <p class="text-gray-400 text-sm mt-2">
                                Envoyez un message pour commencer à discuter.
                            </p>
                        </div>
                    </div>

                    <!-- Ajouter un indicateur de frappe -->
                    <div
                        v-if="isTyping"
                        class="text-xs text-gray-500 italic px-4 py-2"
                    >
                        {{ selectedProfile?.name }} est en train d'écrire...
                    </div>

                    <!-- Message Input -->
                    <div class="border-t border-gray-200 p-4">
                        <div class="flex flex-col space-y-2">
                            <!-- Prévisualisation de l'image -->
                            <div v-if="selectedFile" class="flex justify-end">
                                <div class="relative inline-block">
                                    <img
                                        :src="previewUrl"
                                        class="max-h-32 rounded-lg"
                                        alt="Preview"
                                    />
                                    <button
                                        @click="removeSelectedFile"
                                        class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center"
                                    >
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-2">
                                <input
                                    type="file"
                                    ref="fileInput"
                                    class="hidden"
                                    accept="image/*"
                                    @change="handleFileUpload"
                                />
                                <button
                                    class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition"
                                    title="Ajouter une image"
                                    @click="$refs.fileInput.click()"
                                >
                                    <i class="fas fa-image"></i>
                                </button>
                                <div class="flex-1 relative">
                                    <input
                                        v-model="newMessage"
                                        type="text"
                                        placeholder="Écrire un message..."
                                        class="w-full px-4 py-2 bg-gray-100 rounded-full focus:outline-none focus:ring-2 focus:ring-pink-500"
                                        @keyup.enter="sendMessage"
                                        maxlength="500"
                                    />
                                    <span
                                        class="absolute right-3 bottom-2 text-xs text-gray-400"
                                    >
                                        {{ newMessage.length }}/500
                                    </span>
                                </div>
                                <button
                                    class="p-2 rounded-full bg-pink-500 text-white hover:bg-pink-600 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                    @click="sendMessage"
                                    :disabled="
                                        (!newMessage.trim() && !selectedFile) ||
                                        remainingPoints < 5
                                    "
                                    :title="
                                        remainingPoints < 5
                                            ? 'Points insuffisants'
                                            : 'Envoyer le message'
                                    "
                                >
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                            <div
                                v-if="remainingPoints < 5"
                                class="text-xs text-red-500 text-center"
                            >
                                Points insuffisants pour envoyer un message.
                                <a
                                    @click="redirectToProfile"
                                    class="text-pink-600 cursor-pointer hover:underline"
                                    >Acheter des points</a
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No Profile Selected State -->
                <div
                    v-else
                    class="w-full lg:w-2/3 bg-white rounded-xl shadow-md p-8 flex items-center justify-center"
                >
                    <div class="text-center">
                        <div class="text-gray-400 mb-4">
                            <i class="fas fa-comments text-5xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-700">
                            Bienvenue, {{ auth?.user?.name }} !
                        </h3>
                        <p class="text-gray-500 mt-2">
                            Sélectionnez un profil dans la liste pour commencer
                            une conversation
                        </p>
                        <p class="text-sm text-pink-600 mt-4">
                            Vous avez {{ remainingPoints }} points disponibles
                        </p>
                    </div>
                </div>
            </div>

            <!-- Modals -->
            <ProfileActionModal
                v-if="showActionModal"
                :show="showActionModal"
                :profile="selectedProfileForActions"
                @close="closeActionModal"
                @chat="startChat"
            />

            <!-- Modal de signalement -->
            <ProfileReportModal
                v-if="showReportModalFlag && selectedProfileForReport"
                :show="showReportModalFlag"
                :user-id="selectedProfileForReport.userId"
                :profile-id="selectedProfileForReport.profileId"
                @close="closeReportModal"
                @reported="handleReported"
            />

            <!-- Modal de prévisualisation d'image -->
            <div v-if="showPreview" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50" @click="closeImagePreview">
                <div class="max-w-4xl max-h-full p-4">
                    <img :src="previewImage.url" :alt="previewImage.file_name" class="max-w-full max-h-[90vh] object-contain" />
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<script setup>
import { ref, onMounted, watch, computed, nextTick, onUnmounted } from "vue";
import MainLayout from "@client/Layouts/MainLayout.vue";
import ProfileCarousel from "@client/Components/ProfileCarousel.vue";
import ActiveConversations from "@client/Components/ActiveConversations.vue";
import ProfileActionModal from "@client/Components/ProfileActionModal.vue";
import ProfileReportModal from "@client/Components/ProfileReportModal.vue";
import axios from "axios";
import Echo from "laravel-echo";
import { router } from "@inertiajs/vue3";

// Configuration d'Axios pour inclure le CSRF token
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
axios.defaults.withCredentials = true;

// Intercepteur pour gérer le renouvellement automatique du token CSRF
let isRefreshing = false;
let failedQueue = [];

const processQueue = (error, token = null) => {
    failedQueue.forEach(prom => {
        if (error) {
            prom.reject(error);
        } else {
            prom.resolve(token);
        }
    });
    failedQueue = [];
};

axios.interceptors.response.use(
    response => response,
    async error => {
        const originalRequest = error.config;

        if (error.response?.status === 419 && !originalRequest._retry) {
            if (isRefreshing) {
                return new Promise((resolve, reject) => {
                    failedQueue.push({ resolve, reject });
                })
                .then(() => {
                    return axios(originalRequest);
                })
                .catch(err => {
                    return Promise.reject(err);
                });
            }

            originalRequest._retry = true;
            isRefreshing = true;

            try {
                await axios.get('/sanctum/csrf-cookie');
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
                originalRequest.headers['X-CSRF-TOKEN'] = token;
                processQueue(null, token);
                return axios(originalRequest);
            } catch (refreshError) {
                processQueue(refreshError, null);
                return Promise.reject(refreshError);
            } finally {
                isRefreshing = false;
            }
        }
        return Promise.reject(error);
    }
);

const props = defineProps({
    profiles: {
        type: Array,
        default: () => [],
    },
    auth: {
        type: Object,
        required: true,
    },
});

const selectedProfile = ref(null);
const newMessage = ref("");
const messagesMap = ref({}); // Map des messages par profileId
const chatContainer = ref(null);
const loading = ref(false);
const remainingPoints = ref(0);
const showPointsAlert = ref(false);
const showReportModalFlag = ref(false);
const selectedProfileForReport = ref(null);
const blockedProfileIds = ref([]);
const reportedProfiles = ref([]);
const conversationStates = ref(new Map());
const fileInput = ref(null);
const selectedFile = ref(null);
const previewUrl = ref(null);
const showPreview = ref(false);
const previewImage = ref(null);

// Messages pour la conversation courante
const currentMessages = computed(() => {
    if (!selectedProfile.value) return [];
    return messagesMap.value[selectedProfile.value.id] || [];
});

// Messages groupés par date
const groupedMessages = computed(() => {
    const grouped = {};

    if (!currentMessages.value || currentMessages.value.length === 0)
        return grouped;

    // Regrouper les messages par date
    currentMessages.value.forEach((message) => {
        const date = message.date || new Date().toISOString().split("T")[0]; // Fallback à la date du jour

        if (!grouped[date]) {
            grouped[date] = [];
        }

        grouped[date].push(message);
    });

    return grouped;
});

// Formater une date pour l'affichage
function formatDate(dateString) {
    const today = new Date().toISOString().split("T")[0];
    const yesterday = new Date(Date.now() - 86400000)
        .toISOString()
        .split("T")[0];

    if (dateString === today) {
        return "Aujourd'hui";
    } else if (dateString === yesterday) {
        return "Hier";
    } else {
        // Format: "X Month YYYY"
        const date = new Date(dateString);
        return date.toLocaleDateString("fr-FR", {
            day: "numeric",
            month: "long",
            year: "numeric",
        });
    }
}

// Sélectionner un profil et charger les messages
async function selectProfile(profile) {
    if (selectedProfile.value && selectedProfile.value.id === profile.id) return;

    selectedProfile.value = profile;
    
    // Initialiser l'état de la conversation si nécessaire
    initConversationState(profile.id);

    // Charger les messages si nous ne les avons pas déjà
    if (!messagesMap.value[profile.id]) {
        await loadMessages(profile.id);
    }

    // Marquer la conversation comme lue
    await markConversationAsRead(profile.id);

    // Faire défiler le chat vers le bas
    nextTick(() => {
        scrollToBottom();
    });
}

// Charger les messages d'un profil
async function loadMessages(profileId) {
    try {
        loading.value = true;

        const response = await axios.get("/messages", {
            params: { profile_id: profileId }
        });

        if (response.data.messages) {
            messagesMap.value = {
                ...messagesMap.value,
                [profileId]: response.data.messages
            };

            // Mettre à jour l'état de la conversation
            const state = response.data.conversation_state;
            conversationStates.value.set(profileId, {
                unreadCount: state.unread_count,
                lastReadMessageId: state.last_read_message_id,
                isOpen: selectedProfile.value?.id === profileId,
                hasBeenOpened: state.has_been_opened,
                awaitingReply: state.awaiting_reply
            });
        }
    } catch (error) {
        console.error("Erreur lors du chargement des messages:", error);
    } finally {
        loading.value = false;
    }
}

// Charger les points
async function loadPoints() {
    try {
        const response = await axios.get("/points/data", {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                'Accept': 'application/json'
            }
        });
        remainingPoints.value = response.data.points;
        return response.data.points;
    } catch (error) {
        console.error("Erreur lors du chargement des points:", error);
        return remainingPoints.value;
    }
}

// Charger les profils bloqués
async function loadBlockedProfiles() {
    try {
        const response = await axios.get("/blocked-profiles", {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                'Accept': 'application/json'
            }
        });
        blockedProfileIds.value = response.data.blocked_profiles;
        reportedProfiles.value = response.data.reported_profiles;
    } catch (error) {
        console.error("Erreur lors du chargement des profils bloqués:", error);
    }
}

// Charger toutes les conversations actives
async function loadAllConversations() {
    try {
        const response = await axios.get("/active-conversations");
        if (response.data.conversations) {
            for (const conv of response.data.conversations) {
                // Initialiser l'état de la conversation
                conversationStates.value.set(conv.profile_id, {
                    unreadCount: conv.unread_count,
                    lastReadMessageId: conv.last_read_message_id,
                    isOpen: selectedProfile.value?.id === conv.profile_id,
                    hasBeenOpened: conv.has_been_opened,
                    awaitingReply: conv.awaiting_reply
                });
                
                // Charger les messages
                await loadMessages(conv.profile_id);
            }
        }
    } catch (error) {
        console.error("Erreur lors du chargement des conversations:", error);
    }
}

// Fonction pour rediriger vers la page de profil
function redirectToProfile() {
    router.visit("/profil");
}

// Charger les points au montage du composant et configurer Echo
onMounted(async () => {
    try {
        // Charger les profils bloqués et signalés
        await loadBlockedProfiles();

        // Charger les points immédiatement
        await loadPoints();

        // Charger toutes les conversations actives
        await loadAllConversations();

        // Initialiser les états des conversations pour tous les profils
        props.profiles.forEach(profile => {
            initConversationState(profile.id);
            if (messagesMap.value[profile.id]) {
                updateUnreadCount(profile.id);
            }
        });

        // Configurer Echo pour les points et messages en temps réel
        if (window.Echo) {
            window.Echo.private(`client.${window.clientId}`)
                .listen('.message.sent', async (data) => {
                    const profileId = data.profile_id;
                    
                    // Initialiser l'état si nécessaire
                    if (!conversationStates.value.has(profileId)) {
                        conversationStates.value.set(profileId, {
                            unreadCount: 0,
                            lastReadMessageId: null,
                            isOpen: selectedProfile.value?.id === profileId,
                            hasBeenOpened: false,
                            awaitingReply: false
                        });
                    }
                    
                    // Mettre à jour les messages
                    await loadMessages(profileId);
                    await loadPoints();

                    // Mettre à jour le compteur si ce n'est pas la conversation active
                    const state = conversationStates.value.get(profileId);
                    if (state && (!selectedProfile.value || selectedProfile.value.id !== profileId)) {
                        state.unreadCount = (state.unreadCount || 0) + 1;
                        state.awaitingReply = true;
                    }

                    // Si c'est la conversation active, faire défiler vers le bas
                    if (selectedProfile.value?.id === profileId) {
                        nextTick(() => {
                            scrollToBottom();
                        });
                    }
                })
                .listen('.points.updated', (data) => {
                    remainingPoints.value = data.points;
                });
        }

        // Initial scroll
        nextTick(() => {
            scrollToBottom();
        });
    } catch (error) {
        console.error("Erreur lors de l'initialisation:", error);
    }
});

// Observer les changements de sélection de profil
watch(selectedProfile, (newProfile, oldProfile) => {
    if (newProfile && newProfile.id !== oldProfile?.id) {
        nextTick(() => {
            scrollToBottom();
        });
    }
});

// Modifier la fonction sendMessage
async function sendMessage() {
    if ((!newMessage.value.trim() && !selectedFile.value) || !selectedProfile.value) return;

    const formData = new FormData();
    formData.append('profile_id', selectedProfile.value.id);
    if (newMessage.value.trim()) {
        formData.append('content', newMessage.value);
    }
    if (selectedFile.value) {
        formData.append('attachment', selectedFile.value);
    }

    const now = new Date();
    const timeString = now.toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
    });

    // Créer le message local
    const localMessage = {
        id: "temp-" + Date.now(),
        content: newMessage.value,
        isOutgoing: true,
        time: timeString,
        date: now.toISOString().split("T")[0],
        pending: true,
    };

    // Si une image est sélectionnée, ajouter la prévisualisation
    if (selectedFile.value) {
        localMessage.attachment = {
            url: previewUrl.value,
            file_name: selectedFile.value.name,
            mime_type: selectedFile.value.type
        };
    }

    // Ajouter le message localement
    if (!messagesMap.value[selectedProfile.value.id]) {
        messagesMap.value[selectedProfile.value.id] = [];
    }
    messagesMap.value[selectedProfile.value.id].push(localMessage);

    // Réinitialiser les champs
    newMessage.value = "";
    removeSelectedFile();

    // Faire défiler vers le bas
    nextTick(() => {
        scrollToBottom();
    });

    try {
        const response = await axios.post("/send-message", formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });

        // Mettre à jour les points
        if (response.data.remaining_points !== undefined) {
            remainingPoints.value = response.data.remaining_points;
        }

        // Mettre à jour le message avec les données du serveur
        if (response.data.success) {
            const index = messagesMap.value[selectedProfile.value.id].findIndex(
                (msg) => msg.id === localMessage.id
            );
            if (index !== -1) {
                messagesMap.value[selectedProfile.value.id][index] = response.data.messageData;
            }
        }
    } catch (error) {
        console.error("Erreur lors de l'envoi du message:", error);
        
        if (error.response?.status === 403) {
            showPointsAlert.value = true;
        }

        // Marquer le message comme échoué
        const index = messagesMap.value[selectedProfile.value.id].findIndex(
            (msg) => msg.id === localMessage.id
        );
        if (index !== -1) {
            messagesMap.value[selectedProfile.value.id][index].failed = true;
            messagesMap.value[selectedProfile.value.id][index].pending = false;
        }
    }
}

// Faire défiler vers le bas du chat
function scrollToBottom() {
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
}

// Formatage des données
function formatGender(gender) {
    const genders = {
        male: "Homme",
        female: "Femme",
        other: "Autre",
    };
    return genders[gender] || "Non spécifié";
}

function formatLocation(profile) {
    // Placeholder pour les données de localisation futures
    return "À proximité";
}

// Filtrer les profils bloqués et ajouter l'indicateur de signalement
const filteredProfiles = computed(() => {
    return props.profiles
        .map((profile) => {
            const reportedProfile = reportedProfiles.value.find(
                (rp) => rp.profile_id === profile.id
            );
            return {
                ...profile,
                isReported: !!reportedProfile,
                reportStatus: reportedProfile?.status,
            };
        })
        .filter((profile) => !blockedProfileIds.value.includes(profile.id));
});

// Fonctions pour le système de signalement
const showReportModal = async (profile) => {
    console.log("Profile complet:", profile);

    try {
        // Vérifier si le profil est en discussion active
        const response = await axios.get(
            `/check-active-discussion/${profile.id}`
        );
        const moderatorId = response.data.moderator_id;

        selectedProfileForReport.value = {
            userId: moderatorId,
            profileId: profile.id,
        };

        console.log(
            "selectedProfileForReport:",
            selectedProfileForReport.value
        );
        showReportModalFlag.value = true;
    } catch (error) {
        console.error(
            "Erreur lors de la vérification de la discussion:",
            error
        );
        selectedProfileForReport.value = {
            userId: null,
            profileId: profile.id,
        };
        showReportModalFlag.value = true;
    }
};

const closeReportModal = () => {
    showReportModalFlag.value = false;
    selectedProfileForReport.value = null;
};

const handleReported = (profileId) => {
    reportedProfiles.value.push({
        profile_id: profileId,
        status: "pending",
    });
};

// Nouvelles refs pour la gestion des actions sur les profils
const showActionModal = ref(false);
const selectedProfileForActions = ref(null);

// Fonction pour afficher la modal d'actions sur un profil
function showProfileActions(profile) {
    selectedProfileForActions.value = profile;
    showActionModal.value = true;
}

// Fonction pour fermer la modal d'actions
function closeActionModal() {
    showActionModal.value = false;
    selectedProfileForActions.value = null;
}

// Fonction pour démarrer une conversation depuis la modal
function startChat(profile) {
    selectProfile(profile);
}

// Ajouter l'état de frappe
const isTyping = ref(false);
let typingTimeout;

// Surveiller la saisie de message pour l'indicateur de frappe
watch(newMessage, (val) => {
    if (val && selectedProfile.value) {
        isTyping.value = true;
        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => {
            isTyping.value = false;
        }, 2000);
    }
});

// Nettoyer le timeout lors du démontage du composant
onUnmounted(() => {
    clearTimeout(typingTimeout);
});

// Ajouter la fonction buyPointsForProfile
function buyPointsForProfile() {
    if (selectedProfile.value) {
        router.visit(`/profile/${selectedProfile.value.id}/points`);
    }
}

// Ajouter la propriété computed pour le tri des conversations
const sortedProfiles = computed(() => {
    return props.profiles.sort((a, b) => {
        const aLastMessage = messagesMap.value[a.id]?.slice(-1)[0];
        const bLastMessage = messagesMap.value[b.id]?.slice(-1)[0];
        
        const aTime = aLastMessage ? new Date(aLastMessage.created_at) : new Date(0);
        const bTime = bLastMessage ? new Date(bLastMessage.created_at) : new Date(0);
        
        return bTime - aTime;
    });
});

// Initialiser l'état d'une conversation
function initConversationState(profileId) {
    if (!conversationStates.value.has(profileId)) {
        conversationStates.value.set(profileId, {
            unreadCount: 0,
            lastReadMessageId: null,
            isOpen: false,
            hasBeenOpened: false,
            awaitingReply: false
        });
    }
}

// Mettre à jour le compteur de messages non lus
function updateUnreadCount(profileId) {
    const state = conversationStates.value.get(profileId);
    if (!state) return;

    const messages = messagesMap.value[profileId] || [];
    const lastReadId = state.lastReadMessageId;
    
    // Compter uniquement les messages non lus qui sont reçus (non envoyés par le client)
    const unreadCount = messages.filter(msg => {
        return !msg.isOutgoing && 
               (!lastReadId || msg.id > lastReadId) &&
               (!selectedProfile.value || selectedProfile.value.id !== profileId);
    }).length;

    // Mettre à jour l'état avec le nouveau compteur
    state.unreadCount = unreadCount;
    state.isOpen = selectedProfile.value?.id === profileId;
}

// Marquer une conversation comme lue
async function markConversationAsRead(profileId) {
    const state = conversationStates.value.get(profileId);
    if (!state) return;

    const messages = messagesMap.value[profileId] || [];
    const lastMessage = messages[messages.length - 1];
    
    if (lastMessage) {
        try {
            // Mettre à jour l'état local
            state.lastReadMessageId = lastMessage.id;
            state.hasBeenOpened = true;
            state.isOpen = true;
            state.unreadCount = 0; // Réinitialiser le compteur
            
            // Appeler l'API pour persister l'état
            await axios.post('/messages/mark-as-read', {
                profile_id: profileId,
                last_message_id: lastMessage.id
            });
        } catch (error) {
            console.error('Erreur lors du marquage comme lu:', error);
        }
    }
}

function isAwaitingReply(profileId) {
    const state = conversationStates.value.get(profileId);
    return state?.awaitingReply || false;
}

function getUnreadCount(profileId) {
    return conversationStates.value.get(profileId)?.unreadCount || 0;
}

function handleFileUpload(event) {
    const file = event.target.files[0];
    if (file) {
        // Vérifier le type de fichier
        if (!file.type.startsWith('image/')) {
            alert('Seules les images sont autorisées');
            return;
        }
        
        // Vérifier la taille du fichier (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            alert('La taille du fichier ne doit pas dépasser 5MB');
            return;
        }

        selectedFile.value = file;
        previewUrl.value = URL.createObjectURL(file);
    }
}

function removeSelectedFile() {
    selectedFile.value = null;
    previewUrl.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function showImagePreview(attachment) {
    previewImage.value = attachment;
    showPreview.value = true;
}

function closeImagePreview() {
    showPreview.value = false;
    previewImage.value = null;
}
</script>

<style scoped>
.profile-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1),
        0 4px 6px -2px rgba(0, 0, 0, 0.05);
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

.message-out.pending {
    opacity: 0.7;
}

.message-out.failed {
    background-color: #ef4444;
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

.points-alert-enter-active,
.points-alert-leave-active {
    transition: all 0.3s ease;
}

.points-alert-enter-from,
.points-alert-leave-to {
    transform: translateY(-20px);
    opacity: 0;
}

/* Styles pour le bouton de signalement */
.fa-flag {
    font-size: 0.875rem;
}

.profile-card {
    position: relative;
}

/* Ajouter des styles pour les icônes d'avatar */
.fas.fa-male,
.fas.fa-female {
    font-size: 1.5rem;
}

/* Mettre à jour les styles des avatars */
.fa-user {
    font-size: 1.2rem;
}

.message-in img, .message-out img {
    max-width: 200px;
    height: auto;
    border-radius: 8px;
    margin-top: 4px;
}

.message-in img:hover, .message-out img:hover {
    opacity: 0.9;
    cursor: zoom-in;
}
</style>
