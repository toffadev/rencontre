<template>
    <div v-if="!clientStore.initialized"
        class="flex items-center justify-center min-h-screen bg-gradient-to-br from-pink-100 via-purple-100 to-white">
        <div class="flex flex-col items-center space-y-4">
            <svg class="animate-spin h-12 w-12 text-pink-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
            <div class="text-lg font-semibold text-pink-600">Chargement de votre espace client...</div>
            <div class="text-sm text-gray-500">Merci de patienter, nous préparons votre expérience ❤️</div>
        </div>
    </div>
    <div v-else>
        <MainLayout>
            <div class="flex flex-col gap-6">
                <!-- Points Alert -->
                <div v-if="showPointsAlert"
                    class="fixed top-4 right-4 bg-pink-100 border border-pink-400 text-pink-700 px-4 py-3 rounded-lg shadow-lg z-50">
                    <div class="flex items-center">
                        <div class="py-1">
                            <svg class="fill-current h-6 w-6 text-pink-500 mr-4" xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20">
                                <path
                                    d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM9 11V9h2v6H9v-4zm0-6h2v2H9V5z" />
                            </svg>
                        </div>
                        <div>
                            <p class="font-bold">Points insuffisants</p>
                            <p class="text-sm">
                                Vous n'avez plus assez de points pour envoyer
                                des messages.
                            </p>
                            <button @click="redirectToProfile"
                                class="mt-2 bg-pink-500 text-white px-4 py-2 rounded-lg text-sm hover:bg-pink-600 transition">
                                Acheter des points
                            </button>
                        </div>
                        <button @click="showPointsAlert = false" class="ml-4">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- Carousel des profils -->
                <ProfileCarousel v-if="isProfilesLoaded" :profiles="profiles" @showActions="showProfileActions" />
                <div v-else class="h-64 bg-gray-100 rounded-xl animate-pulse"></div>

                <!-- Section principale -->
                <div class="flex flex-col lg:flex-row gap-6">
                    <!-- Liste des conversations - Version desktop -->
                    <ActiveConversations v-if="isConversationsLoaded" :profiles="filteredProfiles"
                        :selected-profile="selectedProfile" :messages="messagesMap" :remaining-points="remainingPoints"
                        :conversation-states="conversationStates" @select="selectProfile" @buyPoints="redirectToProfile"
                        @buyPointsForProfile="buyPointsForProfile" @report="showReportModal"
                        class="hidden lg:block lg:w-1/3" />
                    <div v-else class="hidden lg:block lg:w-1/3 bg-gray-100 rounded-xl animate-pulse"></div>

                    <!-- Section Chat -->
                    <div class="w-full lg:w-2/3">
                        <!-- Barre de profils horizontale style Messenger (mobile uniquement) -->
                        <div class="lg:hidden bg-white rounded-xl shadow-md mb-4 overflow-hidden">
                            <!-- Points disponibles -->
                            <div class="flex items-center justify-between px-4 py-2 border-b border-gray-100">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-coins text-pink-500"></i>
                                    <span class="text-sm text-gray-600">{{ remainingPoints }} points</span>
                                </div>
                                <button @click="redirectToProfile"
                                    class="text-pink-500 text-sm hover:underline flex items-center">
                                    <i class="fas fa-plus-circle mr-1"></i>
                                    Recharger
                                </button>
                            </div>

                            <div class="overflow-x-auto scrollbar-hide">
                                <div class="flex p-3 space-x-4">
                                    <div v-for="profile in filteredProfiles" :key="profile.id"
                                        class="flex-shrink-0 relative">
                                        <!-- Container du profil -->
                                        <div class="relative">
                                            <!-- Photo de profil avec indicateur de réponse en attente -->
                                            <div class="relative cursor-pointer" @click="selectProfile(profile)">
                                                <img :src="
                                                        profile.main_photo_path ||
                                                        'https://via.placeholder.com/64'
                                                    " :alt="profile.name" class="w-14 h-14 rounded-full object-cover"
                                                    :class="{
                                                        'ring-2 ring-pink-500 ring-offset-2':
                                                            selectedProfile?.id ===
                                                            profile.id,
                                                        'border-2 border-yellow-400':
                                                            isAwaitingReply(
                                                                profile.id
                                                            ),
                                                    }" />
                                                <div class="online-dot"></div>
                                            </div>

                                            <!-- Badge de messages non lus -->
                                            <div v-if="
                                                    getUnreadCount(profile.id)
                                                "
                                                class="absolute -top-1 -right-1 bg-pink-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs z-20">
                                                {{ getUnreadCount(profile.id) }}
                                            </div>

                                            <!-- Menu d'actions -->
                                            <div class="absolute -top-2 -right-2 flex space-x-1 z-10">
                                                <!-- Bouton de signalement avec état -->
                                                <button @click.stop="
                                                        showReportModal(profile)
                                                    "
                                                    class="bg-white rounded-full w-6 h-6 flex items-center justify-center shadow-sm border border-gray-200"
                                                    :class="{
                                                        'bg-red-50':
                                                            profile.isReported,
                                                    }">
                                                    <i class="fas fa-flag" :class="
                                                            profile.isReported
                                                                ? 'text-red-500'
                                                                : 'text-gray-400 hover:text-red-500'
                                                        " :title="
                                                            profile.isReported
                                                                ? 'Déjà signalé'
                                                                : 'Signaler'
                                                        "></i>
                                                </button>
                                            </div>

                                            <!-- Indicateur de message en attente -->
                                            <div v-if="
                                                    isAwaitingReply(profile.id)
                                                "
                                                class="absolute bottom-0 right-0 bg-yellow-400 w-3 h-3 rounded-full border-2 border-white z-10"
                                                title="En attente de votre réponse"></div>
                                        </div>

                                        <!-- Nom du profil -->
                                        <div class="text-xs text-center mt-1 text-gray-600 truncate w-14">
                                            {{ profile.name }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Chat Content -->
                        <div v-if="selectedProfile"
                            class="bg-white rounded-xl shadow-md overflow-hidden flex flex-col h-[calc(100vh-theme(spacing.32))]">
                            <!-- Chat Header -->
                            <div class="border-b border-gray-200 p-4 flex items-center justify-between">
                                <!-- Left side - Selected Profile Info -->
                                <div class="flex items-center space-x-3">
                                    <div class="relative">
                                        <img :src="
                                                selectedProfile?.main_photo_path ||
                                        'https://via.placeholder.com/64'
                                            " :alt="selectedProfile?.name"
                                            class="w-12 h-12 rounded-full object-cover" />
                                        <div class="online-dot"></div>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold">
                                            {{ selectedProfile?.name }}
                                        </h3>
                                        <p class="text-sm text-gray-500">
                                            En ligne
                                        </p>
                                    </div>
                                </div>

                                <!-- Right side - Current User Info and Actions -->
                                <div class="flex items-center space-x-4">
                                    <!-- Current User Info -->
                                    <div class="flex items-center">
                                        <div class="relative mr-3">
                                            <img v-if="
                                                    auth?.user
                                                        ?.profile_photo_url
                                                " :src="
                                                    auth.user.profile_photo_url
                                                " :alt="auth.user.name" class="w-10 h-10 rounded-full object-cover" />
                                            <div v-else
                                                class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                <i class="fas fa-user text-gray-400"></i>
                                            </div>
                                        </div>
                                        <div class="text-left">
                                            <p class="font-semibold">
                                                {{ auth?.user?.name }}
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                {{ remainingPoints }} points
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="flex space-x-2">
                                        <button @click="buyPointsForProfile"
                                            class="p-2 rounded-full bg-pink-500 text-white hover:bg-pink-600 transition"
                                            title="Offrir des points">
                                            <i class="fas fa-coins"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Chat Messages -->
                            <div class="chat-container flex-1 overflow-y-auto p-4 space-y-3" ref="chatContainer">
                                <!-- Messages groupés par date -->
                                <div v-if="currentMessages.length">
                                    <div v-for="(
messagesForDate, date
                                        ) in groupedMessages" :key="date">
                                        <!-- En-tête de date -->
                                        <div class="text-center text-xs text-gray-500 my-4">
                                            {{ formatDate(date) }}
                                        </div>

                                        <!-- Messages pour cette date -->
                                        <div v-for="message in messagesForDate" :key="message.id" :class="`flex space-x-2 mb-3 ${
                                                message.isOutgoing
                                                    ? 'justify-end'
                                                    : ''
                                            }`">
                                            <template v-if="!message.isOutgoing">
                                                <div class="relative">
                                                    <img v-if="
                                                            selectedProfile.main_photo_path
                                                        " :src="
                                                            selectedProfile.main_photo_path
                                                        " :alt="
                                                            selectedProfile.name
                                                        " class="w-8 h-8 rounded-full object-cover flex-shrink-0" />
                                                    <div v-else
                                                        class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center">
                                                        <i class="fas fa-user text-gray-400"></i>
                                                    </div>
                                                </div>
                                            </template>
                                            <div>
                                                <div :class="`${
                                                        message.isOutgoing
                                                            ? 'message-out'
                                                            : 'message-in'
                                                    } px-4 py-2 max-w-xs lg:max-w-md ${
                                                        message.pending
                                                            ? 'pending'
                                                            : ''
                                                    } ${
                                                        message.failed
                                                            ? 'failed'
                                                            : ''
                                                    }`" :data-message-id="
                                                        message.id
                                                    " :data-profile-id="
                                                        selectedProfile.id
                                                    " :data-is-from-client="
                                                        message.isOutgoing
                                                    " :data-is-read="
                                                        message.read_at
                                                            ? 'true'
                                                            : 'false'
                                                    ">
                                                    <!-- Contenu du message -->
                                                    <div v-if="message.content" class="mb-2">
                                                        {{ message.content }}
                                                    </div>

                                                    <!-- Image attachée -->
                                                    <div v-if="
                                                            message.attachment &&
                                                            message.attachment.mime_type.startsWith(
                                                                'image/'
                                                            )
                                                        " class="mt-2">
                                                        <img :src="
                                                                message
                                                                    .attachment
                                                                    .url
                                                            " :alt="
                                                                message
                                                                    .attachment
                                                                    .file_name
                                                            " class="max-w-full rounded-lg cursor-pointer" @click="
                                                                showImagePreview(
                                                                    message.attachment
                                                                )
                                                            " />
                                                    </div>

                                                    <!-- Indicateur de statut du message -->
                                                    <div class="message-status">
                                                        <span v-if="message.pending" class="message-pending"></span>
                                                        <span v-if="message.failed" class="message-failed">
                                                            <i class="fas fa-exclamation-circle"></i>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center mt-1 text-xs text-gray-500" :class="{
                                                        'justify-end':
                                                            message.isOutgoing,
                                                    }">
                                                    <span class="font-medium mr-2">{{
                                                        message.isOutgoing
                                                        ? auth?.user
                                                        ?.name ||
                                                        "Vous"
                                                        : selectedProfile.name
                                                        }}</span>
                                                    <span>{{
                                                        message.time
                                                        }}</span>
                                                </div>
                                            </div>
                                            <template v-if="message.isOutgoing">
                                                <div class="relative">
                                                    <img v-if="
                                                            auth?.user
                                                                ?.profile_photo_url
                                                        " :src="
                                                            auth.user
                                                                .profile_photo_url
                                                        " :alt="auth.user.name"
                                                        class="w-8 h-8 rounded-full object-cover flex-shrink-0" />
                                                    <div v-else
                                                        class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center">
                                                        <i class="fas fa-user text-gray-400"></i>
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
                                        Envoyez un message pour commencer à
                                        discuter.
                                    </p>
                                </div>
                            </div>

                            <!-- Ajouter un indicateur de frappe -->
                            <div v-if="isTyping" class="text-xs text-gray-500 italic px-4 py-2">
                                {{ selectedProfile?.name }} est en train
                                d'écrire...
                            </div>

                            <!-- Message Input -->
                            <div class="border-t border-gray-200 sticky bottom-0 bg-white z-50 p-4 chat-input">
                                <div class="flex flex-col space-y-2">
                                    <!-- Prévisualisation de l'image -->
                                    <div v-if="selectedFile" class="flex justify-end">
                                        <div class="relative inline-block">
                                            <img :src="previewUrl" class="max-h-32 rounded-lg" alt="Preview" />
                                            <button @click="removeSelectedFile"
                                                class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="flex items-center space-x-2">
                                        <input type="file" ref="fileInput" class="hidden" accept="image/*"
                                            @change="handleFileUpload" />
                                        <button
                                            class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition flex-shrink-0"
                                            title="Ajouter une image" @click="$refs.fileInput.click()">
                                            <i class="fas fa-image"></i>
                                        </button>
                                        <div class="flex-1 relative">
                                            <input v-model="newMessage" type="text" placeholder="Écrire un message..."
                                                class="w-full px-4 py-2 bg-gray-100 rounded-full focus:outline-none focus:ring-2 focus:ring-pink-500"
                                                @keyup.enter="sendMessage" maxlength="500" />
                                            <span class="absolute right-3 bottom-2 text-xs text-gray-400">
                                                {{ newMessage.length }}/500
                                            </span>
                                        </div>
                                        <button
                                            class="p-2 rounded-full bg-pink-500 text-white hover:bg-pink-600 transition flex-shrink-0"
                                            @click="sendMessage" :disabled="
                                                (!newMessage.trim() &&
                                                    !selectedFile) ||
                                                remainingPoints < 5
                                            ">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </div>
                                    <div v-if="remainingPoints < 5" class="text-xs text-red-500 text-center">
                                        Points insuffisants pour envoyer un
                                        message.
                                        <a @click="redirectToProfile"
                                            class="text-pink-600 cursor-pointer hover:underline">Acheter des points</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- No Profile Selected State -->
                        <div v-else
                            class="bg-white rounded-xl shadow-md p-8 flex items-center justify-center h-[calc(100vh-theme(spacing.32))]">
                            <div class="text-center">
                                <div class="text-gray-400 mb-4">
                                    <i class="fas fa-comments text-5xl"></i>
                                </div>
                                <h3 class="text-lg font-medium text-gray-700">
                                    Bienvenue, {{ auth?.user?.name }} !
                                </h3>
                                <p class="text-gray-500 mt-2">
                                    Sélectionnez un profil dans la liste pour
                                    commencer une conversation
                                </p>
                                <p class="text-sm text-pink-600 mt-4">
                                    Vous avez {{ remainingPoints }} points
                                    disponibles
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modals -->
                <ProfileActionModal v-if="showActionModal" :show="showActionModal" :profile="selectedProfileForActions"
                    @close="closeActionModal" @chat="startChat" />

                <!-- Modal de signalement -->
                <ProfileReportModal v-if="showReportModalFlag && selectedProfileForReport" :show="showReportModalFlag"
                    :user-id="selectedProfileForReport.userId" :profile-id="selectedProfileForReport.profileId"
                    @close="closeReportModal" @reported="handleReported" />

                <!-- Modal de prévisualisation d'image -->
                <div v-if="showPreview"
                    class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50"
                    @click="closeImagePreview">
                    <div class="max-w-4xl max-h-full p-4">
                        <img :src="previewImage.url" :alt="previewImage.file_name"
                            class="max-w-full max-h-[90vh] object-contain" />
                    </div>
                </div>
            </div>
        </MainLayout>
    </div>
</template>

<script setup>
// Script setup section
import { ref, onMounted, watch, computed, nextTick, onUnmounted } from "vue";
import MainLayout from "@client/Layouts/MainLayout.vue";
import ProfileCarousel from "@client/Components/ProfileCarousel.vue";
import ActiveConversations from "@client/Components/ActiveConversations.vue";
import ProfileActionModal from "@client/Components/ProfileActionModal.vue";
import ProfileReportModal from "@client/Components/ProfileReportModal.vue";
import { router } from "@inertiajs/vue3";
import { useClientStore } from "@/stores/clientStore";
import webSocketManager from "@/services/WebSocketManager";
import { useWebSocketHealth } from "@/composables/useWebSocketHealth";
import { initializeWebSocketServices } from "@/websocket-bootstrap";
import authService from '@/services/AuthenticationService';

// Initialiser les stores
const clientStore = useClientStore();
const store = useClientStore();
const { connectionStatus, isHealthy } = useWebSocketHealth();

// Props
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

// État local du composant
const selectedProfile = ref(null);
const newMessage = ref("");
const chatContainer = ref(null);
const showPointsAlert = ref(false);
const showReportModalFlag = ref(false);
const selectedProfileForReport = ref(null);
const fileInput = ref(null);
const selectedFile = ref(null);
const previewUrl = ref(null);
const showPreview = ref(false);
const previewImage = ref(null);
const isTyping = ref(false);
const showActionModal = ref(false);
const selectedProfileForActions = ref(null);
const connectionReady = ref(false);

const isProfilesLoaded = ref(false);
//const isConversationsLoaded = ref(false);

let typingTimeout;

// Données du client
const messagesMap = computed(() => clientStore.messagesMap);
const remainingPoints = computed(() => clientStore.points.balance);
const conversationStates = computed(() => clientStore.conversationStates);
const blockedProfileIds = computed(() => clientStore.blockedProfileIds);
const reportedProfiles = computed(() => clientStore.reportedProfiles);

// Messages pour la conversation courante
const currentMessages = computed(() => {
    if (!selectedProfile.value) return [];
    return clientStore.getMessagesForProfile(selectedProfile.value.id) || [];
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

// Filtrer les profils bloqués et ajouter l'indicateur de signalement
const filteredProfiles = computed(() => {
    return props.profiles
        .map((profile) => {
            // Vérifier si reportedProfiles existe avant d'appeler find
            const reportedProfile =
                reportedProfiles.value && reportedProfiles.value.find
                    ? reportedProfiles.value.find(
                          (rp) => rp.profile_id === profile.id
                      )
                : null;

            return {
                ...profile,
                isReported: !!reportedProfile,
                reportStatus: reportedProfile?.status,
            };
        })
        .filter(
            (profile) =>
                !blockedProfileIds.value ||
                !blockedProfileIds.value.includes(profile.id)
        );
});

// Fonction pour initialiser ou vérifier la connexion WebSocket
async function ensureWebSocketConnection() {
    try {
        // Vérifier l'état des données utilisateur pour le debug
        console.log("🔍 État des données utilisateur:");
        console.log(
            "  → window.Laravel.user:",
            window.Laravel?.user || "Non disponible"
        );
        console.log(
            "  → meta[user-id]:",
            document
                .querySelector('meta[name="user-id"]')
                ?.getAttribute("content") || "Non disponible"
        );
        console.log("  → props.auth:", props.auth || "Non disponible");
        console.log(
            "  → CSRF Token:",
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute("content") || "Non disponible"
        );

        // Synchroniser immédiatement les données utilisateur si disponibles dans props
        if (
            props.auth &&
            props.auth.user &&
            (!window.Laravel || !window.Laravel.user)
        ) {
            console.log(
                "🔄 Synchronisation immédiate des données utilisateur depuis props..."
            );
            if (!window.Laravel) window.Laravel = {};
            window.Laravel.user = {
                id: props.auth.user.id,
                type: props.auth.user.type,
                name: props.auth.user.name,
            };

            // Définir également les variables globales
            window.clientId = parseInt(props.auth.user.id);
            window.userType = props.auth.user.type;
        }

        // Attendre un court instant pour que les scripts soient chargés
        await new Promise((resolve) => setTimeout(resolve, 100));

        // Vérifier si nous avons besoin de rafraîchir le token CSRF
        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content");
        if (!csrfToken) {
            console.warn(
                "⚠️ Token CSRF manquant, tentative de rafraîchissement..."
            );
            try {
                await axios.get("/sanctum/csrf-cookie");
                console.log("✅ Token CSRF rafraîchi");
            } catch (error) {
                console.error(
                    "❌ Échec du rafraîchissement du token CSRF:",
                    error
                );
            }
        }

        if (!window.Echo) {
            console.log(
                "🔄 Initialisation des services WebSocket depuis Home.vue..."
            );
            try {
                // Initialiser avec un timeout plus court pour une meilleure UX
                const initPromise = initializeWebSocketServices();
                const timeoutPromise = new Promise((_, reject) =>
                    setTimeout(() => reject(new Error("Timeout local")), 3000)
                );

                await Promise.race([initPromise, timeoutPromise]);
                console.log(
                    "✅ Services WebSocket initialisés avec succès depuis Home.vue"
                );
            } catch (error) {
                if (error.message === "Timeout local") {
                    console.warn(
                        "⚠️ Timeout local atteint, continuons avec fonctionnalités limitées"
                    );
                } else {
                    console.warn(
                        "⚠️ Initialisation des WebSockets échouée:",
                        error
                    );
                }

                // Continuer avec l'initialisation du store même si Echo a échoué
            }
        }

        // Vérifier si le client store est initialisé - toujours tenter ceci
        if (!clientStore.initialized) {
            console.log("🔄 Initialisation du store client depuis Home.vue...");
            try {
                await clientStore.initialize();
                console.log(
                    "✅ Store client initialisé avec succès depuis Home.vue"
                );
            } catch (error) {
                console.warn(
                    "⚠️ Initialisation du store client échouée:",
                    error
                );
            }
        }

        // Considérer la connexion comme prête même si Echo n'est pas disponible
        connectionReady.value =
            webSocketManager.isConnected() ||
            (window.Echo && window.echoReady) ||
            !!window.Laravel?.user;
        return connectionReady.value;
    } catch (error) {
        console.error(
            "❌ Erreur lors de l'initialisation WebSocket depuis Home.vue:",
            error
        );
        // Même en cas d'erreur, essayer de continuer avec les fonctionnalités de base
        connectionReady.value = !!window.Laravel?.user;
        return connectionReady.value;
    }
}
// Initialisation
onMounted(async () => {
    try {
        console.log("🚀 Initialisation du composant Home...");

        // Configurer le nettoyage d'abord, avant tout code asynchrone
        const checkInterval = setInterval(() => {
            if (!webSocketManager.isConnected()) {
                console.warn(
                    "⚠️ Connexion WebSocket perdue, tentative de reconnexion..."
                );
                ensureWebSocketConnection();
            }
        }, 30000); // Vérifier toutes les 30 secondes

        // Configurer le nettoyage
        onUnmounted(() => {
            console.log("🧹 Nettoyage du composant Home...");
            clearInterval(checkInterval);
            clearTimeout(typingTimeout);
            window.removeEventListener("resize", handleResize);
            window.removeEventListener("orientationchange", handleOrientation);
        });

        // Vérifier/établir la connexion WebSocket
        const connected = await ensureWebSocketConnection();

        if (connected) {
            console.log("✅ Connexion WebSocket établie avec succès");
        } else {
            console.warn(
                "⚠️ Connexion WebSocket non établie, fonctionnalités limitées"
            );
        }

        // Initialiser les états des conversations
        props.profiles.forEach((profile) => {
            clientStore.initConversationState(profile.id);
        });

        // Vérifier si un profil est présélectionné (par exemple, via une URL)
        const urlParams = new URLSearchParams(window.location.search);
        const profileId = urlParams.get("profile");
        if (profileId) {
            const profile = props.profiles.find(
                (p) => p.id.toString() === profileId
            );
            if (profile) {
                selectProfile(profile);
            }
        }

        // Détection de la taille de l'écran
        checkMobile();
        window.addEventListener("resize", handleResize);
        window.addEventListener("orientationchange", handleOrientation);
    } catch (error) {
        console.error(
            "❌ Erreur lors de l'initialisation du composant Home:",
            error
        );
    }
});

// Chargement progressif des composants
onMounted(async () => {
    // Attendre que le DOM soit prêt
    await nextTick();

    // Marquer les profils comme chargés pour afficher le carousel
    setTimeout(() => {
        isProfilesLoaded.value = true;
    }, 100);

    // Charger les conversations avec un délai pour permettre au reste de l'interface de se rendre
    /* setTimeout(() => {
        isConversationsLoaded.value = true;
    }, 500); */
});

// Ajoutez ce computed pour suivre l'état de chargement
const isConversationsLoaded = computed(() => !clientStore.loadingConversations);

// Sélectionner un profil et charger les messages
async function selectProfile(profile) {
    if (selectedProfile.value && selectedProfile.value.id === profile.id)
        return;

    selectedProfile.value = profile;

    try {
        // S'assurer que la connexion est établie avant de charger les messages
        if (!webSocketManager.isConnected()) {
            await ensureWebSocketConnection();
        }

        // Charger les messages
        await clientStore.loadMessages(profile.id);

        // Marquer la conversation comme lue
        await clientStore.markConversationAsRead(profile.id);

        // Faire défiler vers le chat avec un petit délai
        setTimeout(() => {
            const chatInput = document.querySelector(".chat-input");
            if (chatInput) {
                chatInput.scrollIntoView({
                    behavior: "smooth",
                    block: "center",
                });
            }
            scrollToBottom();
        }, 300);
    } catch (error) {
        console.error("❌ Erreur lors de la sélection du profil:", error);
    }
}

// Envoyer un message
// Envoyer un message
async function sendMessage() {
    if ((!newMessage.value.trim() && !selectedFile.value) || !selectedProfile.value) {
        return;
    }

    if (remainingPoints.value < 5) {
        showPointsAlert.value = true;
        return;
    }

    // Sauvegarder le message et vider immédiatement le champ
    const messageContent = newMessage.value.trim();
    const fileToSend = selectedFile.value;

    // Réinitialiser les champs IMMÉDIATEMENT pour une meilleure UX
    newMessage.value = "";
    removeSelectedFile();

    // Faire défiler vers le bas immédiatement
    scrollToBottom();

    // Envoyer le message en arrière-plan
    try {
        clientStore.sendMessage({
            profileId: selectedProfile.value.id,
            content: messageContent,
            file: fileToSend,
        }).catch(error => {
            console.error("❌ Erreur lors de l'envoi du message:", error);
            if (error.response?.status === 403) {
                showPointsAlert.value = true;
            }
        });

        // Pas besoin d'attendre la réponse pour continuer
    } catch (error) {
        console.error("❌ Erreur lors de l'envoi du message:", error);
    }
}

// Gestion des fichiers
function handleFileUpload(event) {
    const file = event.target.files[0];
    if (file) {
        // Vérifier le type de fichier
        if (!file.type.startsWith("image/")) {
            alert("Seules les images sont autorisées");
            return;
        }

        // Vérifier la taille du fichier (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            alert("La taille du fichier ne doit pas dépasser 5MB");
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
        fileInput.value.value = "";
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

// Faire défiler vers le bas du chat
function scrollToBottom(smooth = false) {
    nextTick(() => {
        if (chatContainer.value) {
            chatContainer.value.scrollTo({
                top: chatContainer.value.scrollHeight,
                behavior: smooth ? "smooth" : "auto",
            });
        }
    });
}

// Fonctions pour le système de signalement
async function showReportModal(profile) {
    try {
        // Vérifier si le profil est en discussion active
        const moderatorId = await clientStore.checkActiveDiscussion(profile.id);

        selectedProfileForReport.value = {
            userId: moderatorId,
            profileId: profile.id,
        };

        showReportModalFlag.value = true;
    } catch (error) {
        console.error(
            "❌ Erreur lors de la vérification de la discussion:",
            error
        );
        selectedProfileForReport.value = {
            userId: null,
            profileId: profile.id,
        };
        showReportModalFlag.value = true;
    }
}

function closeReportModal() {
    showReportModalFlag.value = false;
    selectedProfileForReport.value = null;
}

function handleReported(profileId) {
    clientStore.addReportedProfile(profileId);
}

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
    closeActionModal();
}

// Fonction pour rediriger vers la page de profil
function redirectToProfile() {
    router.visit("/profil");
}

// Fonction pour acheter des points pour un profil
function buyPointsForProfile() {
    if (selectedProfile.value) {
        router.visit(`/profile/${selectedProfile.value.id}/points`);
    }
}

// Utilitaires
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

function isAwaitingReply(profileId) {
    return clientStore.isAwaitingReply(profileId);
}

function getUnreadCount(profileId) {
    return clientStore.getUnreadCount(profileId);
}

// Détection mobile
const isMobile = ref(false);

function checkMobile() {
    isMobile.value = window.innerWidth < 1024;
}

function handleResize() {
    checkMobile();
    if (selectedProfile.value) {
        scrollToBottom();
    }
}

function handleOrientation() {
    setTimeout(() => {
        scrollToBottom();
    }, 100);
}

// Observer les changements de saisie pour l'indicateur de frappe
watch(newMessage, (val) => {
    if (val && selectedProfile.value) {
        isTyping.value = true;
        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => {
            isTyping.value = false;
        }, 2000);
    }
});

// Observer les changements de messages pour faire défiler
watch([currentMessages, selectedProfile], () => {
    scrollToBottom(true);
});

// Observer l'état de la connexion WebSocket
watch(
    () => webSocketManager.getConnectionStatus(),
    async (newStatus) => {
    console.log(`État de la connexion WebSocket changé: ${newStatus}`);

        if (newStatus === "connected") {
        connectionReady.value = true;

        // Si un profil est sélectionné, recharger les messages
        if (selectedProfile.value) {
            await clientStore.loadMessages(selectedProfile.value.id);
        }
    } else {
        connectionReady.value = false;
    }
    }
);
</script>

<style scoped>
.profile-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1),
        0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.chat-container {
    height: 100%;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: rgba(236, 72, 153, 0.5) rgba(243, 244, 246, 0.1);
}

.chat-container::-webkit-scrollbar {
    width: 6px;
}

.chat-container::-webkit-scrollbar-track {
    background: rgba(243, 244, 246, 0.1);
}

.chat-container::-webkit-scrollbar-thumb {
    background-color: rgba(236, 72, 153, 0.5);
    border-radius: 20px;
}

@media (max-width: 1024px) {
    .chat-container {
        height: calc(100vh - 16rem);
        padding-bottom: env(safe-area-inset-bottom);
    }
}

.message-in {
    background-color: #f3f4f6;
    border-radius: 18px 18px 18px 4px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.message-out {
    background: linear-gradient(to right, #ec4899, #db2777);
    color: white;
    border-radius: 18px 18px 4px 18px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.message-out.pending {
    opacity: 0.7;
}

.message-out.failed {
    background: linear-gradient(to right, #ef4444, #dc2626);
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
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4);
    }

    70% {
        box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
    }

    100% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
    }
}

/* Styles pour le scroll horizontal sur mobile */
.overflow-x-auto {
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.overflow-x-auto::-webkit-scrollbar {
    display: none;
}

/* Styles pour les images dans les messages */
.message-in img,
.message-out img {
    max-width: 200px;
    height: auto;
    border-radius: 8px;
    margin-top: 4px;
    transition: transform 0.3s ease;
}

.message-in img:hover,
.message-out img:hover {
    opacity: 0.9;
    cursor: zoom-in;
    transform: scale(1.05);
}

/* Support pour le safe area sur iOS */
@supports (padding: max(0px)) {
    .sticky {
        padding-bottom: max(1rem, env(safe-area-inset-bottom));
    }
}

/* Style pour la barre de défilement horizontale sur mobile */
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

/* Style amélioré pour les avatars sur mobile */
@media (max-width: 1024px) {
    .online-dot {
        width: 10px;
        height: 10px;
        border-width: 1.5px;
    }
}

/* Styles pour le menu d'actions sur mobile */
@media (max-width: 1024px) {
    .group:active .opacity-0 {
        opacity: 1;
    }

    /* Support pour le hover sur les appareils qui le supportent */
    @media (hover: hover) {
        .group:hover .opacity-0 {
            opacity: 1;
        }
    }
}

/* Animations et effets visuels améliorés */
.chat-input input:focus {
    box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.2);
}

.chat-input button:hover {
    transform: translateY(-2px);
}

/* Style pour les profils dans le carousel */
:deep(.profile-carousel .swiper-slide) {
    transition: transform 0.3s ease, filter 0.3s ease;
}

:deep(.profile-carousel .swiper-slide:hover) {
    transform: scale(1.02);
    z-index: 10;
}

:deep(.profile-carousel .swiper-pagination-bullet-active) {
    background-color: #ec4899;
}

/* Animation pour les nouveaux messages */
@keyframes newMessage {
    0% {
        transform: translateY(10px);
        opacity: 0;
    }

    100% {
        transform: translateY(0);
        opacity: 1;
    }
}

.message-animation-enter-active {
    animation: newMessage 0.3s ease-out forwards;
}

/* Style pour le fond de la page principale */
:deep(body) {
    background: linear-gradient(135deg, #111827, #1f2937);
}

/* Styles pour les indicateurs de statut des messages */
.message-in, .message-out {
    position: relative;
}

.message-status {
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
    position: absolute;
    bottom: 2px;
    right: 6px;
}

.message-pending {
    width: 10px;
    height: 10px;
    border: 2px solid rgba(255, 255, 255, 0.7);
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s linear infinite;
}

.message-out .message-pending {
    border-color: rgba(255, 255, 255, 0.9);
    border-top-color: transparent;
}

.message-failed {
    color: #ef4444;
    font-size: 12px;
}

.message-out .message-failed {
    color: #fecaca;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
                                                       