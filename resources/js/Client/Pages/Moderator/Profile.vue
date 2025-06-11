<template>
    <MainLayout>
        <div class="py-4 sm:py-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- En-tête -->
                <div
                    class="bg-white shadow-sm rounded-lg p-4 sm:p-6 mb-4 sm:mb-6"
                >
                    <div
                        class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 sm:gap-0"
                    >
                        <div>
                            <h1
                                class="text-2xl sm:text-3xl font-bold text-gray-900"
                            >
                                Tableau de bord Modérateur
                            </h1>
                            <p class="mt-1 sm:mt-2 text-sm text-gray-600">
                                Suivez vos performances et gérez votre activité
                            </p>
                        </div>
                        <Link
                            href="/moderateur/chat"
                            class="w-full sm:w-auto px-4 py-2 bg-pink-100 text-pink-600 rounded-lg hover:bg-pink-200 transition-colors duration-200 flex items-center justify-center sm:justify-start gap-2"
                        >
                            <i class="fas fa-comments"></i>
                            Retour au chat
                        </Link>
                    </div>
                </div>

                <!-- Layout principal -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-6">
                    <!-- Barre latérale avec filtres -->
                    <div class="lg:col-span-3">
                        <div class="bg-white shadow-sm rounded-lg p-4">
                            <FilterBar
                                :date-range="filters.dateRange"
                                :profiles="profiles"
                                :show-message-type-filter="
                                    activeTab === 'messages'
                                "
                                @filter-changed="handleFilterChange"
                            />
                        </div>
                    </div>

                    <!-- Contenu principal -->
                    <div class="lg:col-span-9">
                        <div class="bg-white shadow-sm rounded-lg p-4 sm:p-6">
                            <!-- Onglets -->
                            <div class="mb-4 sm:mb-6">
                                <nav class="flex flex-wrap gap-2 sm:gap-4">
                                    <button
                                        v-for="tab in tabs"
                                        :key="tab.id"
                                        @click="activeTab = tab.id"
                                        :class="[
                                            'px-3 py-2 text-sm font-medium rounded-md flex-1 sm:flex-none text-center',
                                            activeTab === tab.id
                                                ? 'bg-pink-100 text-pink-700'
                                                : 'text-gray-500 hover:text-gray-700',
                                        ]"
                                    >
                                        <i :class="tab.icon" class="mr-2"></i>
                                        {{ tab.label }}
                                    </button>
                                </nav>
                            </div>

                            <!-- Contenu des onglets -->
                            <div class="relative">
                                <!-- Statistiques -->
                                <div v-show="activeTab === 'stats'">
                                    <StatisticsSection
                                        :statistics="statistics"
                                        :loading="loading"
                                    />
                                </div>

                                <!-- Historique des messages -->
                                <div v-show="activeTab === 'messages'">
                                    <MessageHistorySection
                                        :selected-profile-id="filters.profileId"
                                        :selected-date-range="filters.dateRange"
                                    />
                                </div>

                                <!-- Revenus mensuels -->
                                <div v-show="activeTab === 'earnings'">
                                    <div class="bg-white p-6 rounded-lg shadow-sm">
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                                            <h3 class="text-xl font-semibold">Revenus mensuels</h3>
                                            <div class="flex items-center gap-2">
                                                <button 
                                                    @click="changeYear(currentYear - 1)" 
                                                    class="p-2 bg-gray-100 hover:bg-gray-200 rounded-lg"
                                                >
                                                    <i class="fas fa-chevron-left"></i>
                                                </button>
                                                <span class="text-lg font-medium">{{ currentYear }}</span>
                                                <button 
                                                    @click="changeYear(currentYear + 1)" 
                                                    :disabled="currentYear >= new Date().getFullYear()"
                                                    class="p-2 bg-gray-100 hover:bg-gray-200 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
                                                >
                                                    <i class="fas fa-chevron-right"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                                            <p class="text-blue-700">
                                                Vos revenus sont calculés sur la base de <span class="font-bold">50 points par message reçu</span> des clients.
                                            </p>
                                        </div>
                                        
                                        <div v-if="loadingMonthlyEarnings" class="flex justify-center py-8">
                                            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-pink-500"></div>
                                        </div>
                                        
                                        <div v-else class="overflow-x-auto">
                                            <table class="min-w-full bg-white">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="py-2 px-4 border-b text-left">Mois</th>
                                                        <th class="py-2 px-4 border-b text-right">Messages reçus</th>
                                                        <th class="py-2 px-4 border-b text-right">Revenus</th>
                                                        <th class="py-2 px-4 border-b text-right">Statut</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="(month, index) in monthlyEarnings" :key="index">
                                                        <td class="py-3 px-4 border-b">{{ month.name }}</td>
                                                        <td class="py-3 px-4 border-b text-right">{{ month.messages }}</td>
                                                        <td class="py-3 px-4 border-b text-right font-medium">{{ formatCurrency(month.earnings) }}</td>
                                                        <td class="py-3 px-4 border-b text-right">
                                                            <span :class="`px-2 py-1 rounded-full text-xs ${month.status === 'Payé' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}`">
                                                                {{ month.status }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<script setup>
import { ref, onMounted, watch, computed } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import MainLayout from "@client/Layouts/MainLayout.vue";
import FilterBar from "./Components/FilterBar.vue";
import StatisticsSection from "./Partials/StatisticsSection.vue";
import MessageHistorySection from "./Partials/MessageHistorySection.vue";

// État local
const activeTab = ref("stats");
const loading = ref(false);
const statistics = ref({
    totalMessages: 0,
    shortMessages: 0,
    longMessages: 0,
    receivedMessages: 0,
    pointsReceived: 0,
    earnings: 0,
    receivedEarnings: 0,
    messageQualityRate: 0,
    dailyStats: [],
});
const profiles = ref([]);
const filters = ref({
    dateRange: "week",
    profileId: "",
    messageType: "all",
});

// Configuration des onglets
const tabs = [
    { id: "stats", label: "Statistiques", icon: "fas fa-chart-line" },
    { id: "messages", label: "Messages", icon: "fas fa-comments" },
    { id: "earnings", label: "Revenus", icon: "fas fa-coins" },
];

// Données pour les revenus mensuels (exemple)
const monthlyEarnings = ref([]);
const loadingMonthlyEarnings = ref(false);
const currentYear = ref(new Date().getFullYear());

// Formatage de la monnaie
function formatCurrency(value) {
    return new Intl.NumberFormat("fr-FR", {
        style: "currency",
        currency: "EUR",
    }).format(value);
}

// Chargement des données
async function loadData() {
    loading.value = true;
    try {
        if (activeTab.value === "stats") {
            await loadStatistics();
        } else if (activeTab.value === "earnings") {
            await loadMonthlyEarnings();
        }
    } catch (error) {
        console.error("Erreur lors du chargement des données:", error);
    } finally {
        loading.value = false;
    }
}

async function loadStatistics() {
    const response = await fetch(
        `/moderateur/profile/statistics?${new URLSearchParams({
            dateRange: filters.value.dateRange,
            profileId: filters.value.profileId,
        })}`
    );

    if (response.ok) {
        const data = await response.json();
        statistics.value = data;
    }
}

async function loadMonthlyEarnings() {
    loadingMonthlyEarnings.value = true;
    try {
        const response = await fetch(
            `/moderateur/profile/monthly-earnings?${new URLSearchParams({
                year: currentYear.value,
            })}`
        );

        if (response.ok) {
            const data = await response.json();
            monthlyEarnings.value = data.months;
        }
    } catch (error) {
        console.error("Erreur lors du chargement des revenus mensuels:", error);
    } finally {
        loadingMonthlyEarnings.value = false;
    }
}

async function loadProfiles() {
    const response = await fetch("/moderateur/profile");
    if (response.ok) {
        const data = await response.json();
        profiles.value = data.profiles || [];
    }
}

// Gestionnaires d'événements
function handleFilterChange(newFilters) {
    filters.value = { ...filters.value, ...newFilters };
    loadData();
}

// Surveillance des changements d'onglet
watch(activeTab, () => {
    loadData();
});

// Initialisation
onMounted(async () => {
    await loadProfiles();
    await loadData();
});

function changeYear(year) {
    currentYear.value = year;
    loadData();
}
</script>
