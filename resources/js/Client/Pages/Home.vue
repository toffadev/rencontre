<template>
    <MainLayout>
        <div class="flex flex-col lg:flex-row gap-6">
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

            <!-- Profiles Section -->
            <div
                class="w-full lg:w-1/3 bg-white rounded-xl shadow-md overflow-hidden p-4"
            >
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">Profils à proximité</h2>
                    <div class="flex space-x-2">
                        <button
                            class="p-2 rounded-full bg-pink-100 text-pink-600 hover:bg-pink-200 transition"
                        >
                            <i class="fas fa-sliders-h"></i>
                        </button>
                        <button
                            class="p-2 rounded-full bg-pink-100 text-pink-600 hover:bg-pink-200 transition"
                        >
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>

                <!-- Points Display -->
                <div class="mb-4 p-3 bg-pink-50 rounded-lg">
                    <div class="flex flex-col space-y-3">
                        <!-- Points disponibles -->
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-sm text-pink-600">
                                    Points disponibles
                                </p>
                                <p class="text-2xl font-bold text-pink-700">
                                    {{ remainingPoints }}
                                </p>
                            </div>
                            <button
                                @click="redirectToProfile"
                                class="text-pink-600 hover:text-pink-700 flex items-center"
                                title="Acheter des points pour vous"
                            >
                                <i class="fas fa-plus-circle text-xl"></i>
                            </button>
                        </div>

                        <!-- Acheter des points pour le profil -->
                        <div
                            v-if="selectedProfile"
                            class="border-t border-pink-100 pt-3"
                        >
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="text-sm text-pink-600">
                                        Points pour {{ selectedProfile.name }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        Offrez des points à votre interlocuteur
                                    </p>
                                </div>
                                <button
                                    @click="buyPointsForProfile"
                                    class="flex items-center px-3 py-1.5 bg-pink-500 text-white rounded-lg text-sm hover:bg-pink-600 transition"
                                >
                                    <i class="fas fa-gift mr-2"></i>
                                    Offrir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <!-- Dynamic Profile Cards -->
                    <div
                        v-if="profiles.length"
                        v-for="profile in filteredProfiles"
                        :key="profile.id"
                        class="profile-card transition duration-300 cursor-pointer"
                        @click="selectProfile(profile)"
                        :class="{
                            'border-l-4 border-pink-500':
                                selectedProfile &&
                                selectedProfile.id === profile.id,
                        }"
                    >
                        <div
                            class="bg-white rounded-lg shadow-sm p-4 flex items-center space-x-3 border border-gray-100 relative"
                            :class="{
                                'border-l-4 border-pink-500':
                                    selectedProfile &&
                                    selectedProfile.id === profile.id,
                            }"
                        >
                            <div class="relative">
                                <img
                                    :src="
                                        profile.main_photo_path ||
                                        'https://via.placeholder.com/64'
                                    "
                                    :alt="profile.name"
                                    class="w-16 h-16 rounded-full object-cover"
                                />
                                <div class="online-dot"></div>
                            </div>
                            <div>
                                <h3 class="font-semibold">
                                    {{ profile.name }}
                                </h3>
                                <p class="text-sm text-gray-500">
                                    {{ formatLocation(profile) }}
                                </p>
                                <div class="flex mt-1 space-x-1">
                                    <span
                                        class="px-2 py-1 bg-pink-100 text-pink-600 text-xs rounded-full"
                                        >{{
                                            formatGender(profile.gender)
                                        }}</span
                                    >
                                </div>
                            </div>
                            <div class="ml-auto flex space-x-2">
                                <button
                                    class="p-2 rounded-full bg-pink-500 text-white hover:bg-pink-600 transition"
                                    @click.stop="selectProfile(profile)"
                                >
                                    <i class="fas fa-comment-dots"></i>
                                </button>
                                <button
                                    @click.stop="showReportModal(profile)"
                                    class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition"
                                    title="Signaler ce profil"
                                >
                                    <i class="fas fa-flag"></i>
                                </button>
                            </div>
                            <div
                                v-if="profile.isReported"
                                class="absolute top-2 right-2"
                            >
                                <span
                                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"
                                >
                                    <i class="fas fa-flag mr-1"></i> Signalé
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-if="!profiles.length" class="text-center py-8">
                        <p class="text-gray-500">
                            Aucun profil disponible pour le moment.
                        </p>
                    </div>
                </div>

                <div class="mt-4 text-center">
                    <button
                        class="px-4 py-2 bg-pink-500 text-white rounded-full hover:bg-pink-600 transition font-medium"
                    >
                        Voir plus de profils
                    </button>
                </div>
            </div>

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
                                    <i class="fas fa-user text-gray-400"></i>
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
                            v-for="(messagesForDate, date) in groupedMessages"
                            :key="date"
                        >
                            <!-- En-tête de date -->
                            <div class="text-center text-xs text-gray-500 my-4">
                                {{ formatDate(date) }}
                            </div>

                            <!-- Messages pour cette date -->
                            <div
                                v-for="(message, index) in messagesForDate"
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
                                        } ${message.failed ? 'failed' : ''}`"
                                    >
                                        {{ message.content }}
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
                                            'justify-end': message.isOutgoing,
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
                                            v-if="auth?.user?.profile_photo_url"
                                            :src="auth.user.profile_photo_url"
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

                <!-- Message Input avec compteur de caractères -->
                <div class="border-t border-gray-200 p-4">
                    <div class="flex flex-col space-y-2">
                        <div class="flex items-center space-x-2">
                            <button
                                class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition"
                                title="Ajouter un fichier"
                            >
                                <i class="fas fa-plus"></i>
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
                                    !newMessage.trim() || remainingPoints < 5
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
                        Sélectionnez un profil dans la liste pour commencer une
                        conversation
                    </p>
                    <p class="text-sm text-pink-600 mt-4">
                        Vous avez {{ remainingPoints }} points disponibles
                    </p>
                </div>
            </div>

            <!-- Modal de signalement -->
            <ProfileReportModal
                v-if="showReportModalFlag && selectedProfileForReport"
                :show="showReportModalFlag"
                :user-id="selectedProfileForReport.userId"
                :profile-id="selectedProfileForReport.profileId"
                @close="closeReportModal"
                @reported="handleReported"
            />
        </div>
    </MainLayout>
</template>

<script setup>
import { ref, onMounted, watch, computed, nextTick, onUnmounted } from "vue";
import MainLayout from "@client/Layouts/MainLayout.vue";
import axios from "axios";
import Echo from "laravel-echo";
import { router } from "@inertiajs/vue3";
import ProfileReportModal from "@client/Components/ProfileReportModal.vue";

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
    if (selectedProfile.value && selectedProfile.value.id === profile.id)
        return;

    selectedProfile.value = profile;

    // Charger les messages si nous ne les avons pas déjà
    if (!messagesMap.value[profile.id]) {
        await loadMessages(profile.id);
    }

    // Faire défiler le chat vers le bas
    nextTick(() => {
        scrollToBottom();
    });
}

// Charger les messages d'un profil
async function loadMessages(profileId) {
    try {
        loading.value = true;
        console.log(`Chargement des messages pour le profil ${profileId}`);

        const response = await axios.get("/messages", {
            params: { profile_id: profileId },
        });

        if (response.data.messages) {
            console.log(
                `Messages reçus pour le profil ${profileId}:`,
                response.data.messages
            );
            // Réaffecter complètement le tableau des messages pour ce profil
            messagesMap.value[profileId] = [...response.data.messages];

            // Vérification après chargement
            console.log(`MessagesMap après chargement:`, messagesMap.value);
        } else {
            console.log(`Aucun message reçu pour le profil ${profileId}`);
            messagesMap.value[profileId] = [];
        }
    } catch (error) {
        console.error("Erreur lors du chargement des messages:", error);
    } finally {
        loading.value = false;
    }
}

// Fonction pour rediriger vers la page de profil
function redirectToProfile() {
    router.visit("/profil");
}

// Modifier la fonction sendMessage pour gérer les points
async function sendMessage() {
    if (newMessage.value.trim() === "" || !selectedProfile.value) return;

    const profileId = selectedProfile.value.id;
    const messageContent = newMessage.value;

    // Créer le message local pour une UX plus réactive
    const localMessage = {
        id: "temp-" + Date.now(),
        content: messageContent,
        isOutgoing: true,
        time: new Date().toLocaleTimeString([], {
            hour: "2-digit",
            minute: "2-digit",
        }),
        date: new Date().toISOString().split("T")[0],
        pending: true,
    };

    // Ajouter le message localement
    if (!messagesMap.value[profileId]) {
        messagesMap.value[profileId] = [];
    }
    messagesMap.value[profileId].push(localMessage);

    // Faire défiler le chat vers le bas immédiatement
    nextTick(() => {
        scrollToBottom();
    });

    // Réinitialiser l'input
    newMessage.value = "";

    try {
        const response = await axios.post("/send-message", {
            profile_id: profileId,
            content: messageContent,
        });

        // Mettre à jour les points restants
        if (response.data.remaining_points !== undefined) {
            remainingPoints.value = response.data.remaining_points;
        }

        // Si le message est envoyé avec succès, remplacer le message temporaire
        if (response.data.success) {
            const index = messagesMap.value[profileId].findIndex(
                (msg) => msg.id === localMessage.id
            );
            if (index !== -1) {
                messagesMap.value[profileId][index] = response.data.messageData;
            }
        }
    } catch (error) {
        console.error("Erreur lors de l'envoi du message:", error);

        // Si l'erreur est due aux points insuffisants
        if (error.response?.status === 403) {
            showPointsAlert.value = true;
        }

        // Marquer le message comme échoué
        const index = messagesMap.value[profileId].findIndex(
            (msg) => msg.id === localMessage.id
        );
        if (index !== -1) {
            messagesMap.value[profileId][index].failed = true;
            messagesMap.value[profileId][index].pending = false;
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

// Configuration de Laravel Echo
onMounted(async () => {
    // Charger les profils bloqués
    await loadBlockedProfiles();

    // Vérifier que l'objet Echo est disponible globalement
    if (window.Echo) {
        // Écouter les messages entrants sur le canal privé du client
        window.Echo.private(`client.${window.clientId}`).listen(
            ".message.sent",
            async (data) => {
                const profileId = data.profile_id;

                // Recharger tous les messages pour assurer la synchronisation
                await loadMessages(profileId);

                // Si c'est la conversation actuelle, faire défiler
                if (
                    selectedProfile.value &&
                    selectedProfile.value.id === profileId
                ) {
                    nextTick(() => {
                        scrollToBottom();
                    });
                }
            }
        );
    }

    // Initial scroll
    nextTick(() => {
        scrollToBottom();
    });

    // Charger les points
    await loadPoints();
});

// Observer les changements de sélection de profil
watch(selectedProfile, (newProfile, oldProfile) => {
    if (newProfile && newProfile.id !== oldProfile?.id) {
        nextTick(() => {
            scrollToBottom();
        });
    }
});

// Charger les points au montage du composant
async function loadPoints() {
    try {
        const response = await axios.get("/points/data");
        remainingPoints.value = response.data.points;
    } catch (error) {
        console.error("Erreur lors du chargement des points:", error);
    }
}

// Fonctions pour le système de signalement
const showReportModal = async (profile) => {
    console.log("Profile complet:", profile);

    try {
        // Vérifier si le profil est en discussion active
        const response = await axios.get(
            `/check-active-discussion/${profile.id}`
        );
        const moderatorId = response.data.moderator_id; // Peut être null

        selectedProfileForReport.value = {
            userId: moderatorId, // Peut être null si pas de modérateur
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
        // On permet quand même le signalement, même en cas d'erreur
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
    // Mettre à jour la liste des profils signalés
    reportedProfiles.value.push({
        profile_id: profileId,
        status: "pending",
    });
};

// Charger les profils bloqués
async function loadBlockedProfiles() {
    try {
        const response = await axios.get("/blocked-profiles");
        blockedProfileIds.value = response.data.blocked_profiles;
        reportedProfiles.value = response.data.reported_profiles;
    } catch (error) {
        console.error("Erreur lors du chargement des profils bloqués:", error);
    }
}

// Ajouter le nom du client connecté dans la section chat
const currentUserName = computed(() => {
    return props.auth?.user?.name || "Vous";
});

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
</style>
