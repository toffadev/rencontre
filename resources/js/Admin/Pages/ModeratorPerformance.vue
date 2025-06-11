<template>
  <AdminLayout title="Performance des Modérateurs">
    <div class="container mx-auto px-4 py-8">
      <h1 class="text-2xl font-bold mb-6">Performance des Modérateurs</h1>

      <!-- Filtres -->
      <ModeratorPerformanceFilterBar :moderators="availableModerators" :profiles="availableProfiles"
        :selected-period="filters.period" :selected-moderator="filters.moderator_id"
        :selected-profile="filters.profile_id" :selected-performance-level="filters.performance_level"
        :selected-revenue-type="filters.revenue_type" @filter-changed="handleFilterChange" />

      <!-- Statistiques globales -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <StatCard title="Messages" :value="stats.totalMessages" :trend="trends.messages" icon="chat" />
        <StatCard title="Temps de réponse moyen" :value="formatTime(stats.avgResponseTime)" :trend="trends.responseTime"
          trend-inverse icon="clock" />
        <StatCard title="Points" :value="stats.totalPoints + stats.totalModeratorShare" :trend="trends.points"
          icon="star" />
        <StatCard title="Revenus" :value="formatCurrency(stats.totalEarnings)" :trend="trends.earnings"
          icon="currency-euro" />
      </div>

      <!-- Tableau des modérateurs -->
      <div class="bg-white rounded-lg shadow overflow-hidden mb-8">
        <div class="px-4 py-5 sm:px-6 flex justify-between items-center">
          <h2 class="text-lg font-medium text-gray-900">Liste des modérateurs</h2>
          <div>
            <button @click="exportData"
              class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
              <span class="mr-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                  stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
              </span>
              Exporter
            </button>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Modérateur
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Messages Envoyés
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Messages Reçus
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Points
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Revenus Mois Courant
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Revenus Mois Précédent
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Statut Paiement
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Performance
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="moderator in moderators" :key="moderator.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-10 w-10">
                      <img v-if="moderator.avatar" class="h-10 w-10 rounded-full" :src="moderator.avatar" alt="" />
                      <div v-else
                        class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center text-gray-500">
                        {{ moderator.name.charAt(0).toUpperCase() }}
                      </div>
                    </div>
                    <div class="ml-4">
                      <div class="text-sm font-medium text-gray-900">
                        {{ moderator.name }}
                      </div>
                      <div class="text-sm text-gray-500">
                        {{ moderator.email }}
                      </div>
                      <div class="text-xs text-gray-400 flex flex-wrap gap-1 mt-1">
                        <span v-for="profile in moderator.profiles" :key="profile.id"
                          class="inline-flex items-center bg-gray-100 rounded-full px-2 py-0.5">
                          {{ profile.name }}
                        </span>
                      </div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ moderator.stats.messages_sent }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ moderator.stats.messages_received }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ moderator.stats.profile_points }}
                  <span class="text-xs text-gray-400 block">
                    (Part: {{ moderator.stats.moderator_share }})
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ formatCurrency(moderator.stats.earnings) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  {{ formatCurrency(moderator.stats.last_month_earnings) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="{
                    'px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full': true,
                    'bg-green-100 text-green-800': moderator.stats.payment_status === 'Payé',
                    'bg-yellow-100 text-yellow-800': moderator.stats.payment_status === 'En attente'
                  }">
                    {{ moderator.stats.payment_status }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="{
                    'px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full': true,
                    'bg-green-100 text-green-800': moderator.stats.performance === 'top',
                    'bg-blue-100 text-blue-800': moderator.stats.performance === 'average',
                    'bg-red-100 text-red-800': moderator.stats.performance === 'low'
                  }">
                    {{ formatPerformance(moderator.stats.performance) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <a :href="`/admin/moderator-performance/moderator/${moderator.id}`"
                    class="text-indigo-600 hover:text-indigo-900">
                    Détails
                  </a>
                </td>
              </tr>
              <tr v-if="moderators.length === 0">
                <td colspan="9" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                  Aucun modérateur trouvé
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Pagination -->
        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
          <div class="flex justify-between items-center">
            <div class="text-sm text-gray-700">
              Affichage de {{ (pagination.currentPage - 1) * pagination.perPage + 1 }} à
              {{ Math.min(pagination.currentPage * pagination.perPage, pagination.total) }}
              sur {{ pagination.total }} modérateurs
            </div>
            <div class="flex-1 flex justify-end">
              <button @click="changePage(pagination.currentPage - 1)" :disabled="pagination.currentPage === 1"
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                Précédent
              </button>
              <button @click="changePage(pagination.currentPage + 1)"
                :disabled="pagination.currentPage === pagination.lastPage"
                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                Suivant
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Graphiques -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Messages par jour</h3>
          <LineChart :chart-data="chartData.messagesChart" :options="chartOptions" />
        </div>
        <div class="bg-white rounded-lg shadow p-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Revenus par jour</h3>
          <LineChart :chart-data="chartData.earningsChart" :options="chartOptions" />
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import ModeratorPerformanceFilterBar from '@/Admin/Components/ModeratorPerformanceFilterBar.vue';
import StatCard from '@/Admin/Components/StatCard.vue';
import LineChart from '@/Admin/Components/LineChart.vue';
import axios from 'axios';

// État local
const filters = ref({
  period: 'month',
  moderator_id: null,
  profile_id: null,
  performance_level: 'all',
  revenue_type: 'all',
  page: 1
});

const stats = ref({
  totalMessages: 0,
  sentMessages: 0,
  receivedMessages: 0,
  avgResponseTime: 0,
  totalPoints: 0,
  totalProfilePoints: 0,
  totalModeratorShare: 0,
  totalEarnings: 0
});

const trends = ref({
  messages: '+0%',
  responseTime: '+0%',
  points: '+0%',
  earnings: '+0%'
});

const chartData = ref({
  messagesChart: {
    labels: [],
    datasets: []
  },
  earningsChart: {
    labels: [],
    datasets: []
  }
});

const chartOptions = ref({
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    y: {
      beginAtZero: true
    }
  }
});

const moderators = ref([]);
const availableModerators = ref([]);
const availableProfiles = ref([]);
const pagination = ref({
  currentPage: 1,
  lastPage: 1,
  perPage: 10,
  total: 0
});
const loading = ref(false);

// Méthodes
// Méthodes
async function fetchData() {
  loading.value = true;
  try {
    const response = await axios.get('/admin/moderator-performance/data', {
      params: filters.value
    });

    console.log('Données reçues:', response.data);

    stats.value = response.data.stats || {
      totalMessages: 0,
      sentMessages: 0,
      receivedMessages: 0,
      avgResponseTime: 0,
      totalPoints: 0,
      totalProfilePoints: 0,
      totalModeratorShare: 0,
      totalEarnings: 0
    };

    trends.value = response.data.trends || {
      messages: '+0%',
      responseTime: '+0%',
      points: '+0%',
      earnings: '+0%'
    };

    chartData.value = {
      messagesChart: {
        labels: response.data.chartData?.labels || [],
        datasets: [
          {
            label: 'Messages courts',
            data: response.data.chartData?.shortMessages || [],
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
          },
          {
            label: 'Messages longs',
            data: response.data.chartData?.longMessages || [],
            backgroundColor: 'rgba(255, 99, 132, 0.2)',
            borderColor: 'rgba(255, 99, 132, 1)',
            borderWidth: 1
          },
          {
            label: 'Messages reçus',
            data: response.data.chartData?.receivedMessages || [],
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1
          }
        ]
      },
      earningsChart: {
        labels: response.data.chartData?.labels || [],
        datasets: [
          {
            label: 'Revenus',
            data: response.data.chartData?.earnings || [],
            backgroundColor: 'rgba(255, 159, 64, 0.2)',
            borderColor: 'rgba(255, 159, 64, 1)',
            borderWidth: 1
          }
        ]
      }
    };

    moderators.value = response.data.moderators || [];
    pagination.value = response.data.pagination || {
      currentPage: 1,
      lastPage: 1,
      perPage: 10,
      total: 0
    };
  } catch (error) {
    console.error('Erreur lors de la récupération des données:', error);
  } finally {
    loading.value = false;
  }
}

async function fetchAvailableModerators() {
  try {
    const response = await axios.get('/admin/api/moderators');
    availableModerators.value = response.data || [];
  } catch (error) {
    console.error('Erreur lors de la récupération des modérateurs:', error);
  }
}

async function fetchAvailableProfiles() {
  try {
    const response = await axios.get('/admin/api/profiles');
    availableProfiles.value = response.data || [];
  } catch (error) {
    console.error('Erreur lors de la récupération des profils:', error);
  }
}

function handleFilterChange(newFilters) {
  filters.value = { ...filters.value, ...newFilters, page: 1 };
  fetchData();
}

function changePage(page) {
  if (page < 1 || page > pagination.value.lastPage) return;
  filters.value.page = page;
  fetchData();
}

function formatTime(seconds) {
  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = seconds % 60;
  return `${minutes}m ${remainingSeconds}s`;
}

function formatCurrency(value) {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR'
  }).format(value);
}

function formatPerformance(performance) {
  const labels = {
    'top': 'Excellent',
    'average': 'Bon',
    'low': 'À améliorer'
  };
  return labels[performance] || performance;
}

async function exportData() {
  try {
    const response = await axios.get('/admin/moderator-performance/export', {
      params: filters.value,
      responseType: 'blob'
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `moderator-performance-${new Date().toISOString().split('T')[0]}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.remove();
  } catch (error) {
    console.error('Erreur lors de l\'exportation des données:', error);
    alert('Une erreur est survenue lors de l\'exportation des données.');
  }
}

// Initialisation
onMounted(() => {
  fetchData();
  fetchAvailableModerators();
  fetchAvailableProfiles();
});
</script> 