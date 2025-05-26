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
            <!-- Profile Section -->
            <div class="w-full lg:w-1/3">
                <div class="bg-white rounded-xl profile-card p-6 mb-6">
                    <div class="flex flex-col items-center">
                        <img
                            :src="auth?.user?.profile_photo_url || 'https://randomuser.me/api/portraits/women/44.jpg'"
                            :alt="auth?.user?.name"
                            class="w-24 h-24 rounded-full border-4 border-pink-100 mb-4"
                        />
                        <h2 class="text-xl font-bold">{{ auth?.user?.name }}</h2>
                        <p class="text-gray-500 text-sm mb-4">{{ auth?.user?.location || 'Paris, France' }}</p>
                        <div class="flex space-x-2 mb-6">
                            <span
                                class="bg-pink-100 text-pink-800 text-xs px-2 py-1 rounded"
                                >28 ans</span
                            >
                            <span
                                class="bg-pink-100 text-pink-800 text-xs px-2 py-1 rounded"
                                >Femme</span
                            >
                            <span
                                class="bg-pink-100 text-pink-800 text-xs px-2 py-1 rounded"
                                >Célibataire</span
                            >
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4">
                        <h3 class="font-medium mb-3">À propos de moi</h3>
                        <p class="text-gray-600 text-sm mb-4">
                            Passionnée de voyages et de cuisine. Je recherche
                            quelqu'un pour partager des moments simples et des
                            aventures extraordinaires.
                        </p>

                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-500">Inscrit depuis</span>
                            <span class="font-medium">15/03/2023</span>
                        </div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-500"
                                >Dernière connexion</span
                            >
                            <span class="font-medium">Aujourd'hui</span>
                        </div>
                    </div>
                </div>

                <!-- Bouton retour -->
                <button @click="goToHome" class="w-full bg-gray-100 text-gray-700 py-2 px-4 rounded-lg mb-4 hover:bg-gray-200 transition flex items-center justify-center">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Retour à l'accueil
                </button>

                <!-- Premium Banner -->
                <div
                    class="bg-gradient-to-r from-pink-500 to-purple-500 text-white rounded-xl p-5 mb-6"
                >
                    <h3 class="font-bold text-lg mb-2">Passez Premium</h3>
                    <p class="text-sm mb-4">
                        Obtenez plus de points et envoyez plus de messages !
                    </p>
                    <button
                        class="btn-premium w-full text-white font-medium py-2 px-4 rounded-lg transition duration-300"
                    >
                        Voir les offres <i class="fas fa-arrow-right ml-1"></i>
                    </button>
                </div>
            </div>

            <!-- Main Content -->
            <div class="w-full lg:w-2/3">
                <!-- Points and Messages Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                    <div class="stats-card bg-white rounded-xl p-5">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-gray-500 font-medium">
                                Points achetés
                            </h3>
                            <i class="fas fa-shopping-cart text-pink-500"></i>
                        </div>
                        <p class="text-2xl font-bold mb-2">{{ totalPoints }}</p>
                        <p class="text-xs text-gray-500">
                            Total des points payés
                        </p>
                    </div>

                    <div class="stats-card bg-white rounded-xl p-5">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-gray-500 font-medium">
                                Points restants
                            </h3>
                            <i class="fas fa-coins text-yellow-500"></i>
                        </div>
                        <p class="text-2xl font-bold mb-2">
                            {{ remainingPoints }}
                        </p>
                        <div class="mt-2">
                            <div class="progress-bar">
                                <div
                                    class="progress-fill"
                                    :style="`width: ${percentageUsed}%`"
                                ></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ percentageUsed }}% utilisés
                            </p>
                        </div>
                    </div>

                    <div class="stats-card bg-white rounded-xl p-5">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-gray-500 font-medium">
                                Messages envoyés
                            </h3>
                            <i class="fas fa-envelope text-blue-500"></i>
                        </div>
                        <p class="text-2xl font-bold mb-2">
                            {{ totalMessages }}
                        </p>
                        <p class="text-xs text-gray-500">
                            Depuis votre inscription
                        </p>
                    </div>
                </div>

                <!-- Buy Points Section -->
                <div class="bg-white rounded-xl p-6 mb-6">
                    <h3 class="font-bold text-lg mb-4">Acheter des points</h3>
                    <p class="text-gray-600 mb-6">
                        Chaque message envoyé coûte 5 points. Achetez des points
                        pour continuer à discuter avec vos matches.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div
                            v-for="(plan, index) in pointsPlans"
                            :key="index"
                            :class="[
                                'relative rounded-lg p-4 cursor-pointer transition',
                                plan.popular
                                    ? 'border-2 border-pink-400 bg-pink-50'
                                    : 'border border-gray-200 hover:border-pink-300',
                            ]"
                            @click="selectPlan(plan)"
                        >
                            <div
                                v-if="plan.popular"
                                class="absolute -top-2 -right-2 bg-pink-500 text-white text-xs px-2 py-1 rounded-full"
                            >
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

                    <button
                        @click="buyPoints"
                        class="btn-premium w-full text-white font-medium py-3 px-4 rounded-lg transition duration-300"
                    >
                        Acheter des points
                        <i class="fas fa-arrow-right ml-1"></i>
                    </button>
                </div>

                <!-- Tabs Navigation -->
                <div class="flex border-b border-gray-200 mb-6">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        :class="[
                            'px-4 py-2 text-sm font-medium mr-2',
                            activeTab === tab.id
                                ? 'tab-active'
                                : 'text-gray-500 hover:text-pink-500',
                        ]"
                        @click="activeTab = tab.id"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <!-- Tab Content -->
                <div v-if="activeTab === 'messages'" class="tab-content">
                    <div class="bg-white rounded-xl p-6 mb-4">
                        <h3 class="font-bold text-lg mb-4">
                            Historique des messages
                        </h3>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                        >
                                            Destinataire
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                        >
                                            Date
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                        >
                                            Points utilisés
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                        >
                                            Statut
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="bg-white divide-y divide-gray-200"
                                >
                                    <tr
                                        v-for="(
                                            message, index
                                        ) in messageHistory"
                                        :key="index"
                                    >
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div
                                                    class="flex-shrink-0 h-10 w-10"
                                                >
                                                    <img
                                                        class="h-10 w-10 rounded-full"
                                                        :src="message.avatar"
                                                        alt=""
                                                    />
                                                </div>
                                                <div class="ml-4">
                                                    <div
                                                        class="text-sm font-medium text-gray-900"
                                                    >
                                                        {{ message.name }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                        >
                                            {{ message.date }}
                                        </td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                        >
                                            {{ message.points }} points
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span
                                                :class="`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                                                    message.isRead
                                                        ? 'bg-green-100 text-green-800'
                                                        : 'bg-yellow-100 text-yellow-800'
                                                }`"
                                            >
                                                {{
                                                    message.isRead
                                                        ? "Lu"
                                                        : "Non lu"
                                                }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div v-if="activeTab === 'transactions'" class="tab-content">
                    <div class="bg-white rounded-xl p-6 mb-4">
                        <h3 class="font-bold text-lg mb-4">
                            Historique des achats
                        </h3>

                        <div class="space-y-4">
                            <div
                                v-for="(transaction, index) in transactions"
                                :key="index"
                                class="flex items-center justify-between p-4 border border-gray-100 rounded-lg"
                            >
                                <div class="flex items-center space-x-4">
                                    <div
                                        class="bg-pink-100 text-pink-600 p-3 rounded-full"
                                    >
                                        <i class="fas fa-coins"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-medium">
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
                    <div class="bg-white rounded-xl p-6 mb-4">
                        <h3 class="font-bold text-lg mb-6">
                            Paramètres du compte
                        </h3>

                        <div class="space-y-6">
                            <div>
                                <h4 class="font-medium mb-3">Notifications</h4>
                                <div
                                    class="flex items-center justify-between mb-2"
                                >
                                    <span>Nouveaux messages</span>
                                    <label
                                        class="relative inline-flex items-center cursor-pointer"
                                    >
                                        <input
                                            type="checkbox"
                                            v-model="
                                                settings.notifications.messages
                                            "
                                            class="sr-only peer"
                                        />
                                        <div
                                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-pink-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-pink-500"
                                        ></div>
                                    </label>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Nouveaux matches</span>
                                    <label
                                        class="relative inline-flex items-center cursor-pointer"
                                    >
                                        <input
                                            type="checkbox"
                                            v-model="
                                                settings.notifications.matches
                                            "
                                            class="sr-only peer"
                                        />
                                        <div
                                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-pink-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-pink-500"
                                        ></div>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-medium mb-3">
                                    Confidentialité
                                </h4>
                                <div
                                    class="flex items-center justify-between mb-2"
                                >
                                    <span>Profil visible</span>
                                    <label
                                        class="relative inline-flex items-center cursor-pointer"
                                    >
                                        <input
                                            type="checkbox"
                                            v-model="settings.privacy.visible"
                                            class="sr-only peer"
                                        />
                                        <div
                                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-pink-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-pink-500"
                                        ></div>
                                    </label>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Apparaître dans les suggestions</span>
                                    <label
                                        class="relative inline-flex items-center cursor-pointer"
                                    >
                                        <input
                                            type="checkbox"
                                            v-model="
                                                settings.privacy.suggestions
                                            "
                                            class="sr-only peer"
                                        />
                                        <div
                                            class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-pink-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-pink-500"
                                        ></div>
                                    </label>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-gray-200">
                                <button
                                    @click="confirmDeleteAccount"
                                    class="text-red-500 hover:text-red-700 font-medium"
                                >
                                    <i class="fas fa-trash-alt mr-1"></i>
                                    Supprimer mon compte
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import MainLayout from "@client/Layouts/MainLayout.vue";
import axios from "axios";
import { router } from "@inertiajs/vue3";

const props = defineProps({
    auth: {
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

// Charger les données des points
async function loadPointsData() {
    try {
        const response = await axios.get("/points/data");
        const data = response.data;

        remainingPoints.value = data.points;

        // Calculer le total des points achetés
        totalPoints.value = data.transactions
            .filter((t) => t.type === "purchase")
            .reduce((sum, t) => sum + t.points_amount, 0);

        // Calculer le total des messages envoyés
        totalMessages.value = data.consumptions.filter(
            (c) => c.type === "message_sent"
        ).length;

        // Formater l'historique des messages
        messageHistory.value = data.consumptions
            .filter((c) => c.type === "message_sent")
            .map((c) => ({
                name: c.consumable?.recipient_name || "Utilisateur",
                avatar:
                    c.consumable?.recipient_avatar ||
                    "https://randomuser.me/api/portraits/men/32.jpg",
                date: new Date(c.created_at).toLocaleDateString("fr-FR"),
                points: c.points_spent,
                isRead: true,
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
