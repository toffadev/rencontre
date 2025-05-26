<template>
    <MainLayout>
        <div class="py-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- En-tête -->
                <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                    <div class="flex justify-between items-center">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">
                                Tableau de bord Modérateur
                            </h1>
                            <p class="mt-2 text-sm text-gray-600">
                                Suivez vos performances et gérez votre activité
                            </p>
                        </div>
                        <Link
                            href="/moderateur/chat"
                            class="px-4 py-2 bg-pink-100 text-pink-600 rounded-lg hover:bg-pink-200 transition-colors duration-200 flex items-center gap-2"
                        >
                            <i class="fas fa-comments"></i>
                            Retour au chat
                        </Link>
                    </div>
                </div>

                <!-- Layout principal -->
                <div class="grid grid-cols-12 gap-6">
                    <!-- Barre latérale avec filtres -->
                    <div class="col-span-12 lg:col-span-3">
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
                    <div class="col-span-12 lg:col-span-9">
                        <div class="bg-white shadow-sm rounded-lg p-6">
                            <!-- Onglets -->
                            <div class="mb-6">
                                <nav class="flex space-x-4">
                                    <button
                                        v-for="tab in tabs"
                                        :key="tab.id"
                                        @click="activeTab = tab.id"
                                        :class="[
                                            'px-3 py-2 text-sm font-medium rounded-md',
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
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<script setup>
import { ref, onMounted, watch } from "vue";
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
    pointsReceived: 0,
    earnings: 0,
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
];

// Chargement des données
async function loadData() {
    loading.value = true;
    try {
        if (activeTab.value === "stats") {
            await loadStatistics();
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
</script>
