<template>
  <AdminLayout>
    <!-- En-tête avec statistiques -->
    <div class="mb-8">
      <h1 class="text-2xl font-semibold text-gray-900">Gestion des Transactions</h1>
      <p class="mt-2 text-sm text-gray-700">
        Suivez et analysez toutes les transactions d'achat de points sur la plateforme
      </p>
    </div>

    <!-- Cartes de statistiques -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-blue-100 text-blue-600">
            <i class="fas fa-euro-sign text-xl"></i>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-500">Revenu total</p>
            <h3 class="text-lg font-semibold text-gray-900">{{ formatMoney(stats.total_revenue) }}</h3>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-green-100 text-green-600">
            <i class="fas fa-coins text-xl"></i>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-500">Points vendus</p>
            <h3 class="text-lg font-semibold text-gray-900">{{ formatNumber(stats.total_points_sold) }}</h3>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-purple-100 text-purple-600">
            <i class="fas fa-shopping-cart text-xl"></i>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-500">Transactions</p>
            <h3 class="text-lg font-semibold text-gray-900">{{ formatNumber(stats.transactions_count) }}</h3>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
            <i class="fas fa-chart-line text-xl"></i>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-500">Moyenne/transaction</p>
            <h3 class="text-lg font-semibold text-gray-900">{{ formatMoney(stats.average_transaction) }}</h3>
          </div>
        </div>
      </div>
    </div>

    <!-- Filtres -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Période -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Période</label>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs text-gray-500 mb-1">Date début</label>
              <input
                type="date"
                v-model="filters.start_date"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm"
              />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Date fin</label>
              <input
                type="date"
                v-model="filters.end_date"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm"
              />
            </div>
          </div>
        </div>

        <!-- Statut -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
          <select
            v-model="filters.status"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm"
          >
            <option value="">Tous</option>
            <option value="succeeded">Réussi</option>
            <option value="pending">En attente</option>
            <option value="failed">Échoué</option>
          </select>
        </div>

        <!-- Montant minimum -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Montant minimum (€)</label>
          <input
            type="number"
            v-model="filters.min_amount"
            min="0"
            step="0.01"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm"
          />
        </div>

        <!-- Montant maximum -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Montant maximum (€)</label>
          <input
            type="number"
            v-model="filters.max_amount"
            min="0"
            step="0.01"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm"
          />
        </div>
      </div>

      <div class="mt-6 flex justify-end space-x-3">
        <button
          @click="resetFilters"
          class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rose-500"
        >
          Réinitialiser
        </button>
        <button
          @click="applyFilters"
          class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-rose-600 hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rose-500"
        >
          Appliquer
        </button>
      </div>
    </div>

    <!-- Graphique des revenus -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
      <h2 class="text-lg font-medium text-gray-900 mb-4">Évolution des revenus</h2>
      <div class="h-64">
        <LineChart
          :chart-data="revenueChartData"
          :options="chartOptions"
        />
      </div>
    </div>

    <!-- Tableau des transactions -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
      <div class="flex justify-between items-center p-6 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Transactions</h2>
        <button
          @click="exportTransactions"
          class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-rose-600 hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rose-500"
        >
          <i class="fas fa-download mr-2"></i>
          Exporter
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Date
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Client
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Type
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Points
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Montant
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Statut
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Session Stripe
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="transaction in transactions.data" :key="transaction.id">
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ formatDate(transaction.date) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ transaction.client.name }}</div>
                <div class="text-sm text-gray-500">ID: {{ transaction.client.id }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  :class="[
                    'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                    transaction.type === 'client' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'
                  ]"
                >
                  {{ transaction.type === 'client' ? 'Points Client' : 'Points Profil' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ formatNumber(transaction.points_amount) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ formatMoney(transaction.money_amount) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  :class="[
                    'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                    transaction.status === 'succeeded' ? 'bg-green-100 text-green-800' :
                    transaction.status === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                    'bg-red-100 text-red-800'
                  ]"
                >
                  {{ formatStatus(transaction.status) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                {{ transaction.stripe_session_id }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
        <Pagination :links="transactions.links" />
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AdminLayout from '@admin/Layouts/AdminLayout.vue'
import LineChart from '@admin/Components/GestionFinance/LineChart.vue'
import Pagination from '@admin/Components/GestionFinance/Pagination.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  transactions: {
    type: Object,
    required: true
  },
  stats: {
    type: Object,
    required: true
  },
  revenueByDay: {
    type: Array,
    required: true
  },
  filters: {
    type: Object,
    default: () => ({})
  }
})

// Filtres avec valeurs par défaut
const filters = ref({
  start_date: props.filters.start_date || '',
  end_date: props.filters.end_date || '',
  status: props.filters.status || '',
  min_amount: props.filters.min_amount || '',
  max_amount: props.filters.max_amount || ''
})

// Données du graphique
const revenueChartData = computed(() => ({
  labels: props.revenueByDay.map(day => day.date),
  datasets: [
    {
      label: 'Revenus (€)',
      data: props.revenueByDay.map(day => day.total_revenue),
      borderColor: '#10B981',
      tension: 0.4
    }
  ]
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        callback: value => formatMoney(value)
      }
    }
  },
  plugins: {
    tooltip: {
      callbacks: {
        label: context => `${formatMoney(context.raw)}`
      }
    }
  }
}

// Méthodes
const formatDate = (date) => {
  return new Date(date).toLocaleDateString('fr-FR', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const formatMoney = (amount) => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR'
  }).format(amount)
}

const formatNumber = (number) => {
  return new Intl.NumberFormat('fr-FR').format(number)
}

const formatStatus = (status) => {
  const statuses = {
    succeeded: 'Réussi',
    pending: 'En attente',
    failed: 'Échoué'
  }
  return statuses[status] || status
}

const applyFilters = () => {
  // Créer un objet pour les filtres non vides
  const nonEmptyFilters = Object.entries(filters.value).reduce((acc, [key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      acc[key] = value
    }
    return acc
  }, {})

  router.get('/admin/transactions', nonEmptyFilters, {
    preserveState: true,
    preserveScroll: true
  })
}

const resetFilters = () => {
  filters.value = {
    start_date: '',
    end_date: '',
    status: '',
    min_amount: '',
    max_amount: ''
  }
  applyFilters()
}

const exportTransactions = () => {
  const queryString = new URLSearchParams(filters.value).toString()
  window.location.href = `/admin/transactions/export?${queryString}`
}
</script> 