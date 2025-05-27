<template>
  <AdminLayout>
    <div class="p-6 space-y-6">
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold text-gray-900">Tableau de bord des performances</h1>
        <div class="flex space-x-4">
          <button 
            @click="exportData" 
            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"
          >
            Exporter
          </button>
          <button 
            @click="refreshData" 
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
          >
            Actualiser
          </button>
        </div>
      </div>

      <ModeratorPerformanceFilterBar 
        v-model:period="filters.period"
        v-model:dateRange="filters.dateRange"
        v-model:moderator="filters.moderator"
        v-model:profile="filters.profile"
        v-model:performanceLevel="filters.performanceLevel"
        @filter="loadData"
      />

      <div v-if="loading" class="flex justify-center items-center py-12">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
      </div>

      <template v-else>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <StatCard 
            title="Messages Totaux" 
            :value="stats.totalMessages"
            icon="chat"
            :trend="stats.trends.messages"
          />
          <StatCard 
            title="Temps de Réponse Moyen" 
            :value="formatDuration(stats.avgResponseTime)"
            icon="clock"
            :trend="stats.trends.responseTime"
          />
          <StatCard 
            title="Points Gagnés" 
            :value="stats.totalPoints"
            icon="star"
            :trend="stats.trends.points"
          />
          <StatCard 
            title="Gains Totaux" 
            :value="formatCurrency(stats.totalEarnings)"
            icon="currency-euro"
            :trend="stats.trends.earnings"
          />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Distribution des Messages</h2>
            <canvas ref="messageChart"></canvas>
          </div>
          <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Tendances du Temps de Réponse</h2>
            <canvas ref="responseChart"></canvas>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
          <div class="p-6">
            <h2 class="text-lg font-semibold">Tableau des Performances</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modérateur</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Messages</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Temps de Réponse</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Points</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gains</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="moderator in moderators" :key="moderator.id">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="h-10 w-10 flex-shrink-0">
                        <img class="h-10 w-10 rounded-full" :src="moderator.avatar || '/img/default-avatar.png'" :alt="moderator.name" />
                      </div>
                      <div class="ml-4">
                        <div class="text-sm font-medium text-gray-900">{{ moderator.name }}</div>
                        <div class="text-sm text-gray-500">{{ moderator.email }}</div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ moderator.stats.messages }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ formatDuration(moderator.stats.avgResponseTime) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ moderator.stats.points }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ formatCurrency(moderator.stats.earnings) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span 
                      :class="getPerformanceClass(moderator.stats.performance)"
                      class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                    >
                      {{ moderator.stats.performance }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="px-6 py-4 bg-gray-50">
            <Pagination v-model="pagination" @paginate="loadData" />
          </div>
        </div>
      </template>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Chart } from 'chart.js/auto'
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import ModeratorPerformanceFilterBar from '@/Admin/Components/ModeratorPerformanceFilterBar.vue'
import StatCard from '@/Admin/Components/StatCard.vue'
import Pagination from '@/Admin/Components/Pagination.vue'
import { formatDuration, formatCurrency } from '@/utils'

const messageChart = ref(null)
const responseChart = ref(null)
const loading = ref(true)

const filters = ref({
  period: 'week',
  dateRange: null,
  moderator: null,
  profile: null,
  performanceLevel: null
})

const stats = ref({
  totalMessages: 0,
  avgResponseTime: 0,
  totalPoints: 0,
  totalEarnings: 0,
  trends: {
    messages: '+0%',
    responseTime: '+0%',
    points: '+0%',
    earnings: '+0%'
  }
})

const moderators = ref([])
const pagination = ref({
  currentPage: 1,
  lastPage: 1,
  perPage: 10,
  total: 0
})

const loadData = async () => {
  try {
    loading.value = true
    
    // Préparer les paramètres de filtrage
    const params = {
      page: pagination.value.currentPage,
      start_date: filters.value.dateRange?.start,
      end_date: filters.value.dateRange?.end,
      moderator_id: filters.value.moderator,
      profile_id: filters.value.profile,
      performance_level: filters.value.performanceLevel,
      period: filters.value.period
    }

    console.log('Sending filters:', params) // Pour le débogage

    const response = await axios.get('/admin/moderator-performance/data', { params })
    
    if (response.data) {
      stats.value = {
        ...response.data.stats,
        trends: response.data.trends || {
          messages: '+0%',
          responseTime: '+0%',
          points: '+0%',
          earnings: '+0%'
        }
      }
      moderators.value = response.data.moderators
      pagination.value = response.data.pagination
      
      // Réinitialiser et mettre à jour les graphiques
      if (messageChart.value) {
        messageChart.value.destroy()
      }
      if (responseChart.value) {
        responseChart.value.destroy()
      }
      
      initCharts(response.data.chartData)
    }
  } catch (error) {
    console.error('Erreur lors du chargement des données:', error)
  } finally {
    loading.value = false
  }
}

const initCharts = (chartData) => {
  if (messageChart.value) {
    messageChart.value = new Chart(messageChart.value.getContext('2d'), {
      type: 'bar',
      data: {
        labels: chartData.messages.labels,
        datasets: [{
          label: 'Messages Courts',
          data: chartData.messages.short,
          backgroundColor: '#60A5FA'
        }, {
          label: 'Messages Longs',
          data: chartData.messages.long,
          backgroundColor: '#34D399'
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'top'
          }
        }
      }
    })
  }

  if (responseChart.value) {
    responseChart.value = new Chart(responseChart.value.getContext('2d'), {
      type: 'line',
      data: {
        labels: chartData.responseTime.labels,
        datasets: [{
          label: 'Temps de Réponse Moyen (minutes)',
          data: chartData.responseTime.data,
          borderColor: '#8B5CF6',
          tension: 0.1
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'top'
          }
        }
      }
    })
  }
}

const getPerformanceClass = (performance) => {
  const classes = {
    'Excellent': 'bg-green-100 text-green-800',
    'Bon': 'bg-blue-100 text-blue-800',
    'Moyen': 'bg-yellow-100 text-yellow-800',
    'Faible': 'bg-red-100 text-red-800'
  }
  return classes[performance] || 'bg-gray-100 text-gray-800'
}

const exportData = async () => {
  try {
    const response = await axios.get('/admin/moderator-performance/export', {
      params: filters.value,
      responseType: 'blob'
    })
    
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', 'performances-moderateurs.xlsx')
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (error) {
    console.error('Erreur lors de l\'export:', error)
  }
}

const refreshData = () => {
  loadData()
}

onMounted(() => {
  loadData()
})
</script> 