<template>
    <div class="w-full bg-white rounded-xl shadow-md overflow-hidden p-4">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">Mes conversations</h2>
            <div class="flex space-x-2">
                <button class="p-2 rounded-full bg-pink-100 text-pink-600 hover:bg-pink-200 transition" title="Filtrer">
                    <i class="fas fa-sliders-h"></i>
                </button>
                <button class="p-2 rounded-full bg-pink-100 text-pink-600 hover:bg-pink-200 transition"
                    title="Rechercher">
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
                    <button @click="handleBuyPoints" class="text-pink-600 hover:text-pink-700 flex items-center"
                        title="Acheter des points pour vous">
                        <i class="fas fa-plus-circle text-xl"></i>
                    </button>
                </div>

                <!-- Acheter des points pour le profil -->
                <div v-if="selectedProfile" class="border-t border-pink-100 pt-3">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm text-pink-600">
                                Points pour {{ selectedProfile.name }}
                            </p>
                            <p class="text-xs text-gray-500">
                                Offrez des points à votre interlocuteur
                            </p>
                        </div>
                        <button @click="handleBuyPointsForProfile(selectedProfile)"
                            class="flex items-center px-3 py-1.5 bg-pink-500 text-white rounded-lg text-sm hover:bg-pink-600 transition">
                            <i class="fas fa-gift mr-2"></i>
                            Offrir
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des conversations -->
        <div class="space-y-2">
            <div v-for="profile in sortedConversations" :key="profile.id"
                class="conversation-card transition duration-300 cursor-pointer" @click="$emit('select', profile)"
                :class="{
                    'border-l-4 border-pink-500': selectedProfile && selectedProfile.id === profile.id,
                    'bg-pink-50': profile.unreadCount > 0
                }">
                <div
                    class="bg-white rounded-lg p-4 flex items-center space-x-3 border border-gray-100 hover:border-pink-200 transition">
                    <!-- Avatar avec badge de notification -->
                    <div class="relative">
                        <img :src="profile.main_photo_path || 'https://via.placeholder.com/48'" :alt="profile.name"
                            class="w-12 h-12 rounded-full object-cover" />
                        <div class="online-dot" v-if="profile.is_online"></div>

                        <!-- Badge de notification -->
                        <div v-if="profile.unreadCount > 0"
                            class="absolute -top-1 -right-1 bg-pink-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                            {{ profile.unreadCount }}
                        </div>

                        <!-- Indicateur de réponse en attente -->
                        <div v-else-if="profile.awaitingReply"
                            class="absolute -top-1 -right-1 bg-yellow-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"
                            title="En attente de votre réponse">
                            <i class="fas fa-reply"></i>
                        </div>
                    </div>

                    <!-- Infos conversation -->
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-start">
                            <h3 class="font-semibold truncate">{{ profile.name }}</h3>
                            <div class="flex items-center space-x-2">
                                <span class="text-xs text-gray-500">{{ formatTime(profile.lastMessage?.created_at)
                                    }}</span>
                                <!-- Icône de signalement -->
                                <button v-if="!profile.isReported" @click.stop="$emit('report', profile)"
                                    class="text-gray-400 hover:text-red-500 transition" title="Signaler ce profil">
                                    <i class="fas fa-flag"></i>
                                </button>
                                <!-- Indicateur de profil signalé -->
                                <div v-else class="text-red-500" :title="'Profil signalé - ' + profile.reportStatus">
                                    <i class="fas fa-flag"></i>
                                </div>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 truncate" :class="{ 'font-semibold': profile.unreadCount > 0 }">
                            {{ profile.lastMessage?.content || 'Aucun message' }}
                        </p>
                        <div class="flex items-center space-x-2 mt-1">
                            <span class="px-2 py-0.5 bg-pink-100 text-pink-600 text-xs rounded-full">{{
                                formatGender(profile.gender) }}</span>
                            <span class="text-xs text-gray-500">{{ formatLocation(profile) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- État vide -->
            <div v-if="!sortedConversations.length" class="text-center py-8">
                <div class="text-gray-400 mb-3">
                    <i class="fas fa-comments text-4xl"></i>
                </div>
                <p class="text-gray-500">Aucune conversation active</p>
                <p class="text-sm text-gray-400 mt-1">
                    Découvrez des profils dans la section ci-dessus et commencez à discuter !
                </p>
                <p class="text-xs text-pink-500 mt-2">
                    Les profils avec qui vous discutez apparaîtront ici
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    profiles: {
        type: Array,
        required: true
    },
    selectedProfile: {
        type: Object,
        default: null
    },
    messages: {
        type: Object,
        required: true
    },
    remainingPoints: {
        type: Number,
        required: true
    },
    conversationStates: {
        type: Object,  // Changé de Map à Object
        required: true
    }
});

const emit = defineEmits(['select', 'buyPoints', 'buyPointsForProfile', 'report']);

// Ne garder que les profils avec qui on a déjà discuté
const activeProfiles = computed(() => {
    return props.profiles.filter(profile => {
        const messages = props.messages[profile.id] || [];
        // On ne garde que les profils avec au moins un message
        return messages.length > 0;
    });
});

// Trier les conversations par date du dernier message
const sortedConversations = computed(() => {
    return activeProfiles.value
        .map(profile => {
            const messages = props.messages[profile.id] || [];
            const lastMessage = messages[messages.length - 1];
            // Utiliser la notation d'objet standard au lieu de Map.get()
            const state = props.conversationStates[profile.id] || {
                unreadCount: 0,
                awaitingReply: false,
                hasBeenOpened: false
            };

            return {
                ...profile,
                lastMessage,
                unreadCount: state.unreadCount || 0,
                awaitingReply: state.awaitingReply || false,
                hasBeenOpened: state.hasBeenOpened || false,
                lastMessageDate: lastMessage ? new Date(lastMessage.created_at) : new Date(0)
            };
        })
        .sort((a, b) => b.lastMessageDate - a.lastMessageDate);
});

function formatTime(timestamp) {
    if (!timestamp) return '';

    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    const oneDay = 24 * 60 * 60 * 1000;

    // Si c'est aujourd'hui, afficher l'heure
    if (diff < oneDay && date.getDate() === now.getDate()) {
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    // Si c'est hier
    if (diff < 2 * oneDay && date.getDate() === now.getDate() - 1) {
        return 'Hier';
    }

    // Si c'est cette année
    if (date.getFullYear() === now.getFullYear()) {
        return date.toLocaleDateString([], { day: 'numeric', month: 'short' });
    }

    // Sinon date complète
    return date.toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatGender(gender) {
    const genders = {
        male: "Homme",
        female: "Femme",
        other: "Autre"
    };
    return genders[gender] || "Non spécifié";
}

function formatLocation(profile) {
    return "À proximité";
}

// Ajouter la fonction pour gérer le clic sur le bouton d'achat de points
function handleBuyPoints() {
    emit('buyPoints');
}

// Ajouter la fonction pour gérer l'achat de points pour un profil
function handleBuyPointsForProfile(profile) {
    emit('buyPointsForProfile', profile);
}
</script>

<style scoped>
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

.conversation-card {
    transition: all 0.2s ease;
}

.conversation-card:hover {
    transform: translateY(-1px);
}
</style>