<template>
    <div>
        <!-- Cartes de statistiques -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <!-- Messages -->
            <div
                class="bg-white rounded-xl p-6 shadow-sm hover:shadow-md transition"
            >
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-medium">Messages</h4>
                    <div class="bg-pink-100 text-pink-600 p-2 rounded-full">
                        <i class="fas fa-comments"></i>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total</span>
                        <span class="font-bold">{{
                            statistics?.totals?.short_messages +
                                statistics?.totals?.long_messages || 0
                        }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Messages courts</span>
                        <span class="text-sm">{{
                            statistics?.totals?.short_messages || 0
                        }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Messages longs</span>
                        <span class="text-sm">{{
                            statistics?.totals?.long_messages || 0
                        }}</span>
                    </div>
                    <div class="pt-2 border-t">
                        <span class="text-sm text-gray-500"
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
                class="bg-white rounded-xl p-6 shadow-sm hover:shadow-md transition"
            >
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-medium">Gains</h4>
                    <div class="bg-green-100 text-green-600 p-2 rounded-full">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total</span>
                        <span class="font-bold">{{
                            formatCurrency(statistics?.totals?.earnings || 0)
                        }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Messages courts</span>
                        <span class="text-sm">{{
                            formatCurrency(
                                (statistics?.totals?.short_messages || 0) * 25
                            )
                        }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Messages longs</span>
                        <span class="text-sm">{{
                            formatCurrency(
                                (statistics?.totals?.long_messages || 0) * 50
                            )
                        }}</span>
                    </div>
                    <div class="pt-2 border-t">
                        <span class="text-sm text-gray-500"
                            >Moyenne par jour:
                            {{
                                formatCurrency(
                                    statistics?.averages?.earnings_per_day || 0
                                )
                            }}</span
                        >
                    </div>
                </div>
            </div>

            <!-- Points reçus -->
            <div
                class="bg-white rounded-xl p-6 shadow-sm hover:shadow-md transition"
            >
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-medium">Points reçus</h4>
                    <div class="bg-purple-100 text-purple-600 p-2 rounded-full">
                        <i class="fas fa-gift"></i>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Total</span>
                        <span class="font-bold">{{
                            statistics?.totals?.points_received || 0
                        }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Cette période</span>
                        <span class="text-sm">{{
                            calculatePeriodPoints()
                        }}</span>
                    </div>
                    <div class="pt-2 border-t">
                        <span class="text-sm text-gray-500">
                            Du {{ formatDate(statistics?.period?.start) }} au
                            {{ formatDate(statistics?.period?.end) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Graphique d'évolution -->
        <div class="bg-white rounded-xl p-6 shadow-sm mb-6">
            <div class="flex items-center justify-between mb-6">
                <h4 class="text-lg font-medium">Évolution des performances</h4>
                <div class="flex space-x-2">
                    <button
                        v-for="metric in metrics"
                        :key="metric.id"
                        @click="selectedMetric = metric.id"
                        :class="[
                            'px-3 py-1 rounded-full text-sm',
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
            <div class="h-64">
                <canvas ref="chartCanvas"></canvas>
            </div>
        </div>

        <!-- État de chargement -->
        <div
            v-if="loading"
            class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center"
        >
            <div
                class="animate-spin rounded-full h-12 w-12 border-b-2 border-pink-500"
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

const selectedMetric = ref("messages");
const metrics = [
    { id: "messages", label: "Messages" },
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

    switch (selectedMetric.value) {
        case "messages":
            data = stats.map(
                (day) => day.total_short_messages + day.total_long_messages
            );
            break;
        case "earnings":
            data = stats.map((day) => day.total_earnings);
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
                borderColor: "#EC4899",
                backgroundColor: "rgba(236, 72, 153, 0.1)",
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
