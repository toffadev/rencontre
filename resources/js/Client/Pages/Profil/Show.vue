<template>
    <MainLayout>
        <!-- Loader -->
        <div v-if="isLoading" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded-lg shadow-xl text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-pink-500 mx-auto mb-4"></div>
                <p class="text-gray-700">Redirection vers la page de paiement...</p>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Profile Section - Version Desktop et Mobile -->
            <div class="w-full lg:w-1/3">
                <!-- Section Profile - Optimisée pour mobile -->
                <div class="bg-white rounded-xl profile-card p-4 lg:p-6 mb-6">
                    <div class="flex flex-col items-center">
                        <!-- Version mobile compacte -->
                        <div class="block lg:hidden w-full">
                            <div class="flex items-center space-x-4 mb-4">
                                <img :src="profileData.photo_url" :alt="profileData.name"
                                    class="w-16 h-16 rounded-full border-4 border-pink-100" />
                                <div class="flex-1">
                                    <h2 class="text-lg font-bold">{{ profileData.name }}</h2>
                                    <p class="text-gray-500 text-sm">{{ profileData.city }}, {{ profileData.country }}
                                    </p>
                                    <div class="flex space-x-2 mt-2">
                                        <span v-if="profileData.age"
                                            class="bg-pink-100 text-pink-800 text-xs px-2 py-1 rounded">{{
                                            profileData.age }} ans</span>
                                        <span class="bg-pink-100 text-pink-800 text-xs px-2 py-1 rounded">{{
                                            getRelationshipStatus }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Version desktop normale -->
                        <div class="hidden lg:block w-full text-center">
                            <img :src="profileData.photo_url" :alt="profileData.name"
                                class="w-24 h-24 rounded-full border-4 border-pink-100 mb-4 mx-auto" />
                            <h2 class="text-xl font-bold">{{ profileData.name }}</h2>
                            <p class="text-gray-500 text-sm mb-4">{{ profileData.city }}, {{ profileData.country }}</p>
                            <div class="flex justify-center space-x-2 mb-6">
                                <span v-if="profileData.age"
                                    class="bg-pink-100 text-pink-800 text-xs px-2 py-1 rounded">{{ profileData.age }}
                                    ans</span>
                                <span class="bg-pink-100 text-pink-800 text-xs px-2 py-1 rounded">{{
                                    getRelationshipStatus }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Section À propos - Compacte sur mobile -->
                    <div class="border-t border-gray-200 pt-4">
                        <h3 class="font-medium mb-2 lg:mb-3">À propos de moi</h3>
                        <p class="text-gray-600 text-sm mb-3 lg:mb-4 line-clamp-2 lg:line-clamp-none">
                            {{ profileData.bio || "Aucune description pour le moment." }}
                        </p>

                        <!-- Infos compactes sur mobile -->
                        <div class="text-xs lg:text-sm space-y-1">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Inscrit depuis</span>
                                <span class="font-medium">{{ profileData.registration_date }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Dernière connexion</span>
                                <span class="font-medium">{{ profileData.last_login }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bouton retour -->
                <button @click="goToHome"
                    class="w-full bg-gray-100 text-gray-700 py-2 px-4 rounded-lg mb-4 hover:bg-gray-200 transition flex items-center justify-center">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Retour à l'accueil
                </button>

                <!-- Premium Banner - Repositionné pour mobile -->
                <div class="hidden lg:block">
                    <div class="bg-gradient-to-r from-pink-500 to-purple-500 text-white rounded-xl p-5 mb-6">
                        <h3 class="font-bold text-lg mb-2">Passez Premium</h3>
                        <p class="text-sm mb-4">
                            Obtenez plus de points et envoyez plus de messages !
                        </p>
                        <button
                            class="btn-premium w-full text-white font-medium py-2 px-4 rounded-lg transition duration-300">
                            Voir les offres <i class="fas fa-arrow-right ml-1"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="w-full lg:w-2/3">
                <!-- Section Achat de Points - 2ème position sur mobile -->
                <div class="block lg:hidden mb-6">
                    <div class="bg-white rounded-xl p-6">
                        <h3 class="font-bold text-lg mb-4">Acheter des points</h3>
                        <p class="text-gray-600 mb-6">
                            Chaque message envoyé coûte 5 points. Achetez des points
                            pour continuer à discuter avec vos matches.
                        </p>

                        <div class="grid grid-cols-1 gap-4 mb-6">
                            <div v-for="(plan, index) in pointsPlans" :key="index" :class="[
                                'relative rounded-lg p-4 cursor-pointer transition',
                                plan.popular
                                    ? 'border-2 border-pink-400 bg-pink-50'
                                    : 'border border-gray-200 hover:border-pink-300',
                            ]" @click="selectPlan(plan)">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h4 class="font-bold text-lg">{{ plan.points }} points</h4>
                                        <p class="text-gray-500 text-sm">{{ plan.messages }} messages</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-pink-500 font-bold text-lg">{{ plan.price }}</p>
                                        <div v-if="plan.popular"
                                            class="bg-pink-500 text-white text-xs px-2 py-1 rounded-full">
                                            Populaire
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button @click="buyPoints"
                            class="btn-premium w-full text-white font-medium py-3 px-4 rounded-lg transition duration-300">
                            Acheter des points
                            <i class="fas fa-arrow-right ml-1"></i>
                        </button>
                    </div>
                </div>

                <!-- Points and Messages Stats - 3ème position sur mobile -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                    <div class="stats-card bg-white rounded-xl p-4 lg:p-5">
                        <div class="flex items-center justify-between mb-2 lg:mb-3">
                            <h3 class="text-gray-500 font-medium text-sm lg:text-base">
                                Points achetés
                            </h3>
                            <i class="fas fa-shopping-cart text-pink-500"></i>
                        </div>
                        <p class="text-xl lg:text-2xl font-bold mb-1 lg:mb-2">{{ totalPoints }}</p>
                        <p class="text-xs text-gray-500">
                            Total des points payés
                        </p>
                    </div>

                    <div class="stats-card bg-white rounded-xl p-4 lg:p-5">
                        <div class="flex items-center justify-between mb-2 lg:mb-3">
                            <h3 class="text-gray-500 font-medium text-sm lg:text-base">
                                Points restants
                            </h3>
                            <i class="fas fa-coins text-yellow-500"></i>
                        </div>
                        <p class="text-xl lg:text-2xl font-bold mb-1 lg:mb-2">
                            {{ remainingPoints }}
                        </p>
                        <div class="mt-2">
                            <div class="progress-bar">
                                <div class="progress-fill" :style="`width: ${percentageUsed}%`"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ percentageUsed }}% utilisés
                            </p>
                        </div>
                    </div>

                    <div class="stats-card bg-white rounded-xl p-4 lg:p-5">
                        <div class="flex items-center justify-between mb-2 lg:mb-3">
                            <h3 class="text-gray-500 font-medium text-sm lg:text-base">
                                Messages envoyés
                            </h3>
                            <i class="fas fa-envelope text-blue-500"></i>
                        </div>
                        <p class="text-xl lg:text-2xl font-bold mb-1 lg:mb-2">
                            {{ totalMessages }}
                        </p>
                        <p class="text-xs text-gray-500">
                            Depuis votre inscription
                        </p>
                    </div>
                </div>

                <!-- Buy Points Section - Version Desktop seulement -->
                <div class="hidden lg:block bg-white rounded-xl p-6 mb-6">
                    <h3 class="font-bold text-lg mb-4">Acheter des points</h3>
                    <p class="text-gray-600 mb-6">
                        Chaque message envoyé coûte 5 points. Achetez des points
                        pour continuer à discuter avec vos matches.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div v-for="(plan, index) in pointsPlans" :key="index" :class="[
                            'relative rounded-lg p-4 cursor-pointer transition',
                            plan.popular
                                ? 'border-2 border-pink-400 bg-pink-50'
                                : 'border border-gray-200 hover:border-pink-300',
                        ]" @click="selectPlan(plan)">
                            <div v-if="plan.popular"
                                class="absolute -top-2 -right-2 bg-pink-500 text-white text-xs px-2 py-1 rounded-full">
                                Populaire
                            </div>
                            <h4 class="font-bold text-lg text-center mb-2">
                                {{ plan.points }} points
                            </h4>
                            <p class="text-pink-500 font-bold text-center mb-2">
                                {{ plan.price }}
                            </p>
                            <p class="text-gray-500 text-sm text-center">
                                {{ plan.messages }} messages
                            </p>
                        </div>
                    </div>

                    <button @click="buyPoints"
                        class="btn-premium w-full text-white font-medium py-3 px-4 rounded-lg transition duration-300">
                        Acheter des points
                        <i class="fas fa-arrow-right ml-1"></i>
                    </button>
                </div>

                <!-- Tabs Navigation -->
                <div class="flex border-b border-gray-200 mb-6 overflow-x-auto">
                    <button v-for="tab in tabs" :key="tab.id" :class="[
                        'px-4 py-2 text-sm font-medium mr-2 whitespace-nowrap',
                        activeTab === tab.id
                            ? 'tab-active'
                            : 'text-gray-500 hover:text-pink-500',
                    ]" @click="activeTab = tab.id">
                        {{ tab.label }}
                    </button>
                </div>

                <!-- Tab Content -->
                <div v-if="activeTab === 'messages'" class="tab-content">
                    <div class="bg-white rounded-xl p-4 lg:p-6 mb-4">
                        <h3 class="font-bold text-lg mb-4">
                            Historique des messages
                        </h3>

                        <!-- Version mobile du tableau -->
                        <div class="block lg:hidden space-y-4">
                            <div v-for="(message, index) in messageHistory" :key="index"
                                class="border border-gray-100 rounded-lg p-4">
                                <div class="flex items-center mb-3">
                                    <img class="h-10 w-10 rounded-full mr-3" :src="message.avatar" alt="" />
                                    <div class="flex-1">
                                        <h4 class="font-medium text-sm">{{ message.name }}</h4>
                                        <p class="text-xs text-gray-500">{{ message.date }}</p>
                                    </div>
                                    <span :class="`px-2 py-1 text-xs font-semibold rounded-full ${message.isRead
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-yellow-100 text-yellow-800'
                                        }`">
                                        {{ message.isRead ? "Lu" : "Non lu" }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-900 mb-2">{{ message.content }}</p>
                                <p class="text-xs text-gray-500">{{ message.points }} points utilisés</p>
                            </div>
                        </div>

                        <!-- Version desktop du tableau -->
                        <div class="hidden lg:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Destinataire
                                        </th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Message
                                        </th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Date
                                        </th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Points utilisés
                                        </th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Statut
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="(message, index) in messageHistory" :key="index">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <img class="h-10 w-10 rounded-full" :src="message.avatar" alt="" />
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        {{ message.name }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900 max-w-xs truncate">
                                                {{ message.content }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ message.date }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ message.points }} points
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${message.isRead
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-yellow-100 text-yellow-800'
                                                }`">
                                                {{ message.isRead ? "Lu" : "Non lu" }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <Pagination v-if="totalItems > 0" :current-page="currentPage" :total-pages="totalPages"
                            :per-page="perPage" :total="totalItems" @page-change="handlePageChange" />
                    </div>
                </div>

                <div v-if="activeTab === 'transactions'" class="tab-content">
                    <div class="bg-white rounded-xl p-4 lg:p-6 mb-4">
                        <h3 class="font-bold text-lg mb-4">
                            Historique des achats
                        </h3>

                        <div class="space-y-4">
                            <div v-for="(transaction, index) in transactions" :key="index"
                                class="flex items-center justify-between p-4 border border-gray-100 rounded-lg">
                                <div class="flex items-center space-x-4">
                                    <div class="bg-pink-100 text-pink-600 p-3 rounded-full">
                                        <i class="fas fa-coins"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-sm lg:text-base">
                                            {{ transaction.title }}
                                        </h4>
                                        <p class="text-sm text-gray-500">
                                            {{ transaction.date }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium text-green-600">
                                        {{ transaction.amount }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ transaction.method }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="activeTab === 'settings'" class="tab-content">
                    <div class="bg-white rounded-xl p-4 lg:p-6 mb-4">
                        <h3 class="font-bold text-lg mb-6">
                            Paramètres du compte
                        </h3>

                        <div class="space-y-6">
                            <div>
                                <h4 class="font-medium mb-3">Notifications</h4>
                                <div class="flex items-center justify-between mb-2">
                                    <span>Nouveaux messages</span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" v-model="settings.notifications.messages"
                                            class="sr-only peer" />
                                        <div
                                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-pink-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-pink-500">
                                        </div>
                                    </label>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Nouveaux matches</span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" v-model="settings.notifications.matches"
                                            class="sr-only peer" />
                                        <div
                                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-pink-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-pink-500">
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-medium mb-3">Confidentialité</h4>
                                <div class="flex items-center justify-between mb-2">
                                    <span>Profil visible</span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" v-model="settings.privacy.visible"
                                            class="sr-only peer" />
                                        <div
                                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-pink-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-pink-500">
                                        </div>
                                    </label>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Apparaître dans les suggestions</span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" v-model="settings.privacy.suggestions"
                                            class="sr-only peer" />
                                        <div
                                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-pink-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-pink-500">
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-gray-200">
                                <button @click="confirmDeleteAccount"
                                    class="text-red-500 hover:text-red-700 font-medium">
                                    <i class="fas fa-trash-alt mr-1"></i>
                                    Supprimer mon compte
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Premium Banner - Repositionné en dernière position sur mobile -->
                <div class="block lg:hidden mt-8">
                    <div class="bg-gradient-to-r from-pink-500 to-purple-500 text-white rounded-xl p-5">
                        <h3 class="font-bold text-lg mb-2">Passez Premium</h3>
                        <p class="text-sm mb-4">
                            Obtenez plus de points et envoyez plus de messages !
                        </p>
                        <button
                            class="btn-premium w-full text-white font-medium py-2 px-4 rounded-lg transition duration-300">
                            Voir les offres <i class="fas fa-arrow-right ml-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import MainLayout from "@client/Layouts/MainLayout.vue";
import ProfileCarousel from "@client/Components/ProfileCarousel.vue";
import ActiveConversations from "@client/Components/ActiveConversations.vue";
import ProfileActionModal from "@client/Components/ProfileActionModal.vue";
import ProfileReportModal from "@client/Components/ProfileReportModal.vue";
import Pagination from "@client/Components/Pagination.vue";
import axios from "axios";
import { router } from "@inertiajs/vue3";

const props = defineProps({
    auth: {
        type: Object,
        required: true
    },
    profileData: {
        type: Object,
        required: true
    }
});

// Points data
const totalPoints = ref(0);
const remainingPoints = ref(0);
const totalMessages = ref(0);
const messageHistory = ref([]);
const transactions = ref([]);

// Pagination data
const currentPage = ref(1);
const totalPages = ref(1);
const perPage = ref(10);
const totalItems = ref(0);

// Computed value for progress bar
const percentageUsed = computed(() => {
    if (totalPoints.value === 0) return 0;
    return Math.round(
        ((totalPoints.value - remainingPoints.value) / totalPoints.value) * 100
    );
});

// Tabs management
const tabs = [
    { id: "messages", label: "Mes Messages" },
    { id: "transactions", label: "Transactions" },
    { id: "settings", label: "Paramètres" },
];
const activeTab = ref("messages");

// Settings data
const settings = ref({
    notifications: {
        messages: true,
        matches: true,
    },
    privacy: {
        visible: true,
        suggestions: true,
    },
});

// Points plans
const pointsPlans = [
    {
        points: 100,
        price: "2.99€",
        messages: "20 messages",
        popular: false,
    },
    {
        points: 500,
        price: "9.99€",
        messages: "100 messages",
        popular: true,
    },
    {
        points: 1000,
        price: "16.99€",
        messages: "200 messages",
        popular: false,
    },
];

// Computed pour le statut relationnel en français
const getRelationshipStatus = computed(() => {
    const statusMap = {
        'single': 'Célibataire',
        'divorced': 'Divorcé(e)',
        'widowed': 'Veuf/Veuve'
    };
    return statusMap[props.profileData.relationship_status] || props.profileData.relationship_status || 'Non renseigné';
});

// Charger Stripe
let stripe = null;
onMounted(async () => {
    loadPointsData();
    
    // Initialiser Stripe
    try {
        const stripeKey = import.meta.env.VITE_STRIPE_KEY;
        if (!stripeKey) {
            throw new Error('La clé API Stripe n\'est pas définie dans les variables d\'environnement (VITE_STRIPE_KEY)');
        }
        
        if (window.Stripe) {
            stripe = window.Stripe(stripeKey);
        } else {
            throw new Error('La bibliothèque Stripe n\'est pas chargée');
        }
    } catch (error) {
        console.error('Erreur lors de l\'initialisation de Stripe:', error.message);
    }
});

// Ajouter ce computed pour les pages à afficher
const displayedPages = computed(() => {
    const pages = [];
    const maxDisplayed = 7;

    if (totalPages.value <= maxDisplayed) {
        for (let i = 1; i <= totalPages.value; i++) {
            pages.push(i);
        }
    } else {
        pages.push(1);
        let start = Math.max(2, currentPage.value - 2);
        let end = Math.min(totalPages.value - 1, currentPage.value + 2);

        if (currentPage.value <= 4) {
            end = 5;
        }
        if (currentPage.value >= totalPages.value - 3) {
            start = totalPages.value - 4;
        }

        if (start > 2) {
            pages.push('...');
        }

        for (let i = start; i <= end; i++) {
            pages.push(i);
        }

        if (end < totalPages.value - 1) {
            pages.push('...');
        }

        pages.push(totalPages.value);
    }

    return pages;
});

// Charger les données des points
async function loadPointsData(page = 1) {
    try {
        const response = await axios.get("/points/data", {
            params: { page }
        });
        const data = response.data;

        remainingPoints.value = data.points;

        // Calculer le total des points achetés
        totalPoints.value = data.transactions
            .filter((t) => t.type === "purchase")
            .reduce((sum, t) => sum + t.points_amount, 0);

        // Mettre à jour les informations de pagination
        currentPage.value = data.consumptions.current_page;
        totalPages.value = Math.ceil(data.consumptions.total / data.consumptions.per_page);
        perPage.value = data.consumptions.per_page;
        totalItems.value = data.consumptions.total;

        // Formater l'historique des messages
        messageHistory.value = data.consumptions.data
            .filter((c) => c.type === "message_sent")
            .map((c) => ({
                name: c.consumable?.profile?.name || "Profil supprimé",
                avatar: c.consumable?.profile?.main_photo_path || "/images/default-profile.jpg",
                date: new Date(c.created_at).toLocaleDateString("fr-FR"),
                points: c.points_spent,
                isRead: c.consumable?.read_at ? true : false,
                content: c.consumable?.content || "Message non disponible"
            }));

        // Formater l'historique des transactions
        transactions.value = data.transactions.map((t) => ({
            title: t.description,
            date: new Date(t.created_at).toLocaleDateString("fr-FR"),
            amount: `${t.money_amount}€`,
            method: t.type === "purchase" ? "Carte bancaire" : "Bonus",
        }));
    } catch (error) {
        console.error("Erreur lors du chargement des données:", error);
    }
}

// Ajouter l'état du loader
const isLoading = ref(false);

// Fonction de navigation vers la page d'accueil
function goToHome() {
    router.visit('/');
}

// Modifier la fonction selectPlan pour inclure le loader
async function selectPlan(plan) {
    if (!stripe) {
        console.error("Stripe n'est pas initialisé");
        alert("Une erreur est survenue. Veuillez réessayer.");
        return;
    }

    try {
        isLoading.value = true;
        const response = await axios.post("/points/checkout", {
            pack: plan.points.toString(),
        });

        if (response.data.error) {
            throw new Error(response.data.error);
        }

        const result = await stripe.redirectToCheckout({
            sessionId: response.data.sessionId,
        });

        if (result.error) {
            throw new Error(result.error.message);
        }
    } catch (error) {
        console.error("Erreur lors de l'achat:", error);
        alert(
            error.response?.data?.error ||
                "Une erreur est survenue lors de l'achat. Veuillez réessayer."
        );
    } finally {
        isLoading.value = false;
    }
}

// Supprimer le compte
function confirmDeleteAccount() {
    if (
        confirm(
            "Êtes-vous sûr de vouloir supprimer votre compte ? Cette action est irréversible."
        )
    ) {
        // Implémenter la suppression du compte
        console.log("Account deletion requested");
    }
}

// Fonction pour gérer le changement de page
function handlePageChange(newPage) {
    loadPointsData(newPage);
}
</script>

<style scoped>
.profile-card {
    box-shadow: 0 10px 25px -5px rgba(244, 114, 182, 0.2);
}

.stats-card {
    transition: all 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-5px);
}

.progress-bar {
    height: 8px;
    border-radius: 4px;
    background-color: #f3e8ed;
}

.progress-fill {
    height: 100%;
    border-radius: 4px;
    background: linear-gradient(90deg, #f472b6 0%, #f9a8d4 100%);
    transition: width 0.5s ease;
}

.btn-premium {
    background: linear-gradient(135deg, #f9a8d4 0%, #f472b6 100%);
    box-shadow: 0 4px 15px rgba(244, 114, 182, 0.4);
}

.btn-premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 7px 20px rgba(244, 114, 182, 0.4);
}

.tab-active {
    border-bottom: 3px solid #f472b6;
    color: #f472b6;
    font-weight: 600;
}

/* Classes utilitaires pour le texte tronqué */
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-none {
    display: block;
    -webkit-line-clamp: none;
    -webkit-box-orient: initial;
    overflow: visible;
}

/* Ajouter des styles pour le loader */
.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}
</style>
