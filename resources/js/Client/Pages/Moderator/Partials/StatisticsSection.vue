<template>
    <div>
        <!-- Notification du nouveau système de paiement -->
        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded-lg shadow-sm">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-blue-500"></i>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700 font-medium">
                        Nouveau système de rémunération
                    </p>
                    <p class="text-sm text-blue-600 mt-1">
                        Vous êtes désormais rémunéré(e) <span class="font-bold">50 points par message reçu</span> des clients, indépendamment de la longueur de vos réponses.
                    </p>
                </div>
            </div>
        </div>

        <!-- Cartes de statistiques -->
        <div
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4 sm:mb-6"
        >
            <!-- Messages reçus (NOUVEAU) -->
            <div
                class="bg-white rounded-xl p-4 sm:p-6 shadow-sm hover:shadow-md transition border-2 border-blue-200"
            >
                <div class="flex items-center justify-between mb-3 sm:mb-4">
                    <h4 class="text-base sm:text-lg font-medium">Messages reçus</h4>
                    <div class="bg-blue-100 text-blue-600 p-2 rounded-full">
                        <i class="fas fa-inbox"></i>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 text-sm sm:text-base">Total</span>
                        <span class="font-bold text-sm sm:text-base">{{
                            statistics?.totals?.received_messages || 0
                        }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 text-sm">Gains (50 pts/msg)</span>
                        <span class="font-semibold text-sm text-blue-600">{{
                            formatCurrency(statistics?.totals?.received_earnings || 0)
                        }}</span>
                    </div>
                    <div class="pt-2 border-t">
                        <span class="text-xs sm:text-sm text-gray-500">Moyenne par jour:
                            {{
                                Math.round(statistics?.averages?.received_messages_per_day || 0)
                            }} messages</span>
                    </div>
                </div>
            </div>

            <!-- Messages envoyés -->
            <div
                class="bg-white rounded-xl p-4 sm:p-6 shadow-sm hover:shadow-md transition"
            >
                <div class="flex items-center justify-between mb-3 sm:mb-4">
                    <h4 class="text-base sm:text-lg font-medium">Messages envoyés</h4>
                    <div class="bg-pink-100 text-pink-600 p-2 rounded-full">
                        <i class="fas fa-comments"></i>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 text-sm sm:text-base"
                            >Total</span
                        >
                        <span class="font-bold text-sm sm:text-base">{{
                            statistics?.totals?.short_messages +
                                statistics?.totals?.long_messages || 0
                        }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 text-sm"
                            >Messages courts</span
                        >
                        <span class="text-sm">{{
                            statistics?.totals?.short_messages || 0
                        }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 text-sm"
                            >Messages longs</span
                        >
                        <span class="text-sm">{{
                            statistics?.totals?.long_messages || 0
                        }}</span>
                    </div>
                    <div class="pt-2 border-t">
                        <span class="text-xs sm:text-sm text-gray-500"
                            >Moyenne par jour:
                            {{
                                Math.round(
                                    statistics?.averages?.messages_per_day || 0
                                )
                            }}</span
                        >
                    </div>
                </div>
            </div>

            <!-- Gains -->
            <div
                class="bg-white rounded-xl p-4 sm:p-6 shadow-sm hover:shadow-md transition"
            >
                <div class="flex items-center justify-between mb-3 sm:mb-4">
                    <h4 class="text-base sm:text-lg font-medium">Gains Mensuels</h4>
                    <div class="bg-green-100 text-green-600 p-2 rounded-full">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 text-sm sm:text-base"
                            >Total</span
                        >
                        <span class="font-bold text-sm sm:text-base">{{
                            formatCurrency(statistics?.totals?.total_earnings || 0)
                        }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 text-sm"
                            >Messages reçus</span
                        >
                        <span class="text-sm font-medium text-blue-600">{{
                            formatCurrency(statistics?.totals?.received_earnings || 0)
                        }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 text-sm"
                            >Points reçus (50%)</span
                        >
                        <span class="text-sm font-medium text-purple-600">{{
                            formatCurrency(statistics?.totals?.moderator_share || 0)
                        }}</span>
                    </div>
                    <div class="pt-2 border-t">
                        <span class="text-xs sm:text-sm text-gray-500"
                            >Moyenne par jour:
                            {{
                                formatCurrency(
                                    statistics?.averages?.received_earnings_per_day || 0
                                )
                            }}</span
                        >
                    </div>
                </div>
            </div>

            <!-- Points reçus -->
            <div
                class="bg-white rounded-xl p-4 sm:p-6 shadow-sm hover:shadow-md transition sm:col-span-3"
            >
                <div class="flex items-center justify-between mb-3 sm:mb-4">
                    <h4 class="text-base sm:text-lg font-medium">
                        Résumé de la période
                    </h4>
                    <div class="bg-purple-100 text-purple-600 p-2 rounded-full">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="space-y-2 p-3 bg-gray-50 rounded-lg">
                        <h5 class="font-medium text-gray-700">Messages</h5>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 text-sm">Reçus</span>
                            <span class="font-bold text-blue-600">{{ statistics?.totals?.received_messages || 0 }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 text-sm">Envoyés</span>
                            <span>{{ (statistics?.totals?.short_messages + statistics?.totals?.long_messages) || 0 }}</span>
                        </div>
                    </div>
                    
                    <div class="space-y-2 p-3 bg-gray-50 rounded-lg">
                        <h5 class="font-medium text-gray-700">Gains</h5>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 text-sm">Messages</span>
                            <span class="text-blue-600">{{ formatCurrency(statistics?.totals?.received_earnings || 0) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 text-sm">Points</span>
                            <span class="text-purple-600">{{ formatCurrency(statistics?.totals?.moderator_share || 0) }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-1 border-t mt-1">
                            <span class="text-gray-600 text-sm font-medium">Total</span>
                            <span class="font-bold text-green-600">{{ formatCurrency(statistics?.totals?.total_earnings || 0) }}</span>
                        </div>
                    </div>
                    
                    <div class="space-y-2 p-3 bg-gray-50 rounded-lg">
                        <h5 class="font-medium text-gray-700">Période</h5>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 text-sm">Du</span>
                            <span>{{ formatDate(statistics?.period?.start) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 text-sm">Au</span>
                            <span>{{ formatDate(statistics?.period?.end) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphique d'évolution -->
        <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm mb-6">
            <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 sm:gap-6 mb-4 sm:mb-6"
            >
                <h4 class="text-base sm:text-lg font-medium">
                    Évolution des performances
                </h4>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="metric in metrics"
                        :key="metric.id"
                        @click="selectedMetric = metric.id"
                        :class="[
                            'px-3 py-1 rounded-full text-sm flex-1 sm:flex-none',
                            selectedMetric === metric.id
                                ? 'bg-pink-500 text-white'
                                : 'bg-gray-100 text-gray-600 hover:bg-gray-200',
                        ]"
                    >
                        {{ metric.label }}
                    </button>
                </div>
            </div>

            <!-- Le graphique -->
            <div class="h-48 sm:h-64">
                <canvas ref="chartCanvas"></canvas>
            </div>
        </div>

        <!-- État de chargement -->
        <div
            v-if="loading"
            class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center"
        >
            <div
                class="animate-spin rounded-full h-10 w-10 sm:h-12 sm:w-12 border-b-2 border-pink-500"
            ></div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from "vue";
import Chart from "chart.js/auto";

const props = defineProps({
    statistics: {
        type: Object,
        default: () => ({}),
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const chartCanvas = ref(null);
let chart = null;

const selectedMetric = ref("received");
const metrics = [
    { id: "received", label: "Messages reçus" },
    { id: "messages", label: "Messages envoyés" },
    { id: "earnings", label: "Gains" },
    { id: "points", label: "Points" },
];

// Formatage des données
function formatCurrency(value) {
    return new Intl.NumberFormat("fr-FR", {
        style: "currency",
        currency: "EUR",
    }).format(value);
}

function formatDate(dateString) {
    if (!dateString) return "";
    return new Date(dateString).toLocaleDateString("fr-FR");
}

function calculatePeriodPoints() {
    if (!props.statistics?.daily_stats) return 0;
    return props.statistics.daily_stats.reduce(
        (sum, day) => sum + day.total_points,
        0
    );
}

// Gestion du graphique
function initChart() {
    if (chart) {
        chart.destroy();
    }

    const ctx = chartCanvas.value.getContext("2d");
    const data = prepareChartData();

    chart = new Chart(ctx, {
        type: "line",
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false,
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                },
            },
        },
    });
}

function prepareChartData() {
    if (!props.statistics?.daily_stats) {
        return {
            labels: [],
            datasets: [
                {
                    data: [],
                    borderColor: "#EC4899",
                    tension: 0.4,
                },
            ],
        };
    }

    const stats = props.statistics.daily_stats;
    const labels = stats.map((day) => formatDate(day.date));
    let data;
    let borderColor = "#EC4899";

    switch (selectedMetric.value) {
        case "received":
            data = stats.map((day) => day.received_messages);
            borderColor = "#3B82F6"; // Bleu pour les messages reçus
            break;
        case "messages":
            data = stats.map(
                (day) => day.total_short_messages + day.total_long_messages
            );
            break;
        case "earnings":
            data = stats.map((day) => day.received_earnings);
            borderColor = "#10B981"; // Vert pour les gains
            break;
        case "points":
            data = stats.map((day) => day.total_points);
            break;
        default:
            data = [];
    }

    return {
        labels,
        datasets: [
            {
                data,
                borderColor: borderColor,
                backgroundColor: `${borderColor}1A`, // Couleur avec 10% d'opacité
                fill: true,
                tension: 0.4,
            },
        ],
    };
}

// Surveillance des changements
watch(
    () => props.statistics,
    () => {
        if (chartCanvas.value) {
            initChart();
        }
    },
    { deep: true }
);

watch(selectedMetric, () => {
    if (chartCanvas.value) {
        initChart();
    }
});

onMounted(() => {
    if (chartCanvas.value) {
        initChart();
    }
});
</script>
