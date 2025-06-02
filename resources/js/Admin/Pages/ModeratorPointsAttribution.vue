<template>
  <AdminLayout>
    <!-- En-tête avec statistiques -->
    <div class="mb-8">
      <h1 class="text-2xl font-semibold text-gray-900">Attribution des Points aux Modérateurs</h1>
      <p class="mt-2 text-sm text-gray-700">
        Gérez et analysez l'attribution des points aux modérateurs selon leurs performances
      </p>
    </div>

    <!-- Statistiques globales -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-blue-100 text-blue-600">
            <i class="fas fa-coins text-xl"></i>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-500">Total points attribués</p>
            <h3 class="text-lg font-semibold text-gray-900">{{ formatNumber(stats.total_points_attributed) }}</h3>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-green-100 text-green-600">
            <i class="fas fa-euro-sign text-xl"></i>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-500">Équivalent monétaire</p>
            <h3 class="text-lg font-semibold text-gray-900">{{ formatMoney(stats.total_monetary_equivalent) }}</h3>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-purple-100 text-purple-600">
            <i class="fas fa-users text-xl"></i>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-500">Modérateurs actifs</p>
            <h3 class="text-lg font-semibold text-gray-900">{{ stats.moderators_count }}</h3>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
          <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
            <i class="fas fa-chart-bar text-xl"></i>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-500">Moyenne points/modérateur</p>
            <h3 class="text-lg font-semibold text-gray-900">{{ formatNumber(stats.average_points_per_moderator) }}</h3>
          </div>
        </div>
      </div>
    </div>

    <!-- Filtres -->
    <div class="bg-white rounded-lg shadow p-6 mb-8">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Modérateur</label>
          <select
            v-model="filters.moderator_id"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm"
          >
            <option value="">Tous les modérateurs</option>
            <option v-for="moderator in moderators" :key="moderator.id" :value="moderator.id">
              {{ moderator.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Période</label>
          <div class="mt-1 flex space-x-2">
            <input
              type="date"
              v-model="filters.start_date"
              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm"
            />
            <input
              type="date"
              v-model="filters.end_date"
              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm"
            />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Type de source</label>
          <select
            v-model="filters.source_type"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm"
          >
            <option value="">Toutes les sources</option>
            <option value="message_sent">Message</option>
            {/* TODO: Ajouter l'option bonus_admin après la mise à jour de la base de données
            <!-- <option value="bonus_admin">Bonus admin</option> -->
            */}
          </select>
        </div>
      </div>

      <div class="mt-4 flex justify-between">
        <button
          @click="showAddBonusModal = true"
          class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-rose-600 hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rose-500"
        >
          <i class="fas fa-plus mr-2"></i>
          Ajouter un bonus
        </button>

        <div class="flex space-x-3">
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
    </div>

    <!-- Liste des modérateurs -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
      <div class="flex justify-between items-center p-6 border-b border-gray-200">
        <h2 class="text-lg font-medium text-gray-900">Points par modérateur</h2>
        <button
          @click="exportData"
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
                Modérateur
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Points totaux
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Messages courts
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Messages longs
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Bonus admin
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Équivalent €
              </th>
              <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="moderator in moderators" :key="moderator.id">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ moderator.name }}</div>
                <div class="text-xs text-gray-500">
                  {{ moderator.profiles.length }} profil(s)
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ formatNumber(moderator.total_points) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ formatNumber(moderator.points_details.message_short?.total_points || 0) }}
                <span class="text-xs text-gray-500">
                  ({{ moderator.points_details.message_short?.count || 0 }})
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ formatNumber(moderator.points_details.message_long?.total_points || 0) }}
                <span class="text-xs text-gray-500">
                  ({{ moderator.points_details.message_long?.count || 0 }})
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ formatNumber(moderator.points_details.bonus_admin?.total_points || 0) }}
                <span class="text-xs text-gray-500">
                  ({{ moderator.points_details.bonus_admin?.count || 0 }})
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                {{ formatMoney(moderator.monetary_equivalent) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <button
                  @click="showModeratorDetails(moderator)"
                  class="text-rose-600 hover:text-rose-700 mr-3"
                >
                  <i class="fas fa-chart-line"></i>
                </button>
                <button
                  @click="showAddBonusModal = true; selectedModerator = moderator"
                  class="text-green-600 hover:text-green-900"
                >
                  <i class="fas fa-plus-circle"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal d'ajout de bonus -->
    <Modal v-if="showAddBonusModal" @close="showAddBonusModal = false">
      <template #title>
        Ajouter un bonus
        <span v-if="selectedModerator" class="text-sm text-gray-500">
          pour {{ selectedModerator.name }}
        </span>
      </template>

      <template #content>
        <form @submit.prevent="addBonus" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Modérateur
            </label>
            <select
              v-model="bonusForm.moderator_id"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm"
              required
            >
              <option v-for="moderator in moderators" :key="moderator.id" :value="moderator.id">
                {{ moderator.name }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">
              Points
            </label>
            <input
              type="number"
              v-model="bonusForm.points"
              min="1"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm"
              required
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">
              Description
            </label>
            <textarea
              v-model="bonusForm.description"
              rows="3"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-rose-500 focus:ring-rose-500 sm:text-sm"
              required
            ></textarea>
          </div>
        </form>
      </template>

      <template #footer>
        <button
          @click="showAddBonusModal = false"
          class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rose-500"
        >
          Annuler
        </button>
        <button
          @click="addBonus"
          class="ml-3 inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-rose-600 hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rose-500"
        >
          Ajouter
        </button>
      </template>
    </Modal>

    <!-- Modal de détails du modérateur -->
    <Modal v-if="showDetailsModal" @close="showDetailsModal = false">
      <template #title>
        Détails des points - {{ selectedModerator?.name }}
      </template>

      <template #content>
        <div class="space-y-6">
          <!-- Graphique d'évolution des points -->
          <div>
            <h3 class="text-lg font-medium text-gray-900 mb-4">Évolution des points</h3>
            <div class="h-64">
              <LineChart
                v-if="moderatorStats"
                :chart-data="moderatorChartData"
                :options="chartOptions"
              />
            </div>
          </div>

          <!-- Statistiques des messages -->
          <div v-if="moderatorStats?.messageStats">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Statistiques des messages</h3>
            <div class="grid grid-cols-2 gap-4">
              <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-sm text-gray-500">Total messages</p>
                <p class="text-xl font-semibold text-gray-900">
                  {{ formatNumber(moderatorStats.messageStats.total_messages) }}
                </p>
              </div>
              <div class="bg-gray-50 p-4 rounded-lg">
                <p class="text-sm text-gray-500">Longueur moyenne</p>
                <p class="text-xl font-semibold text-gray-900">
                  {{ Math.round(moderatorStats.messageStats.avg_message_length) }} caractères
                </p>
              </div>
            </div>
          </div>
        </div>
      </template>
    </Modal>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import AdminLayout from '@admin/Layouts/AdminLayout.vue'
import Modal from '@admin/Components/Common/Modal.vue'
import LineChart from '@admin/Components/GestionFinance/LineChart.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  moderators: {
    type: Array,
    required: true
  },
  stats: {
    type: Object,
    required: true
  },
  filters: {
    type: Object,
    default: () => ({})
  }
})

// État local
const filters = ref({
  moderator_id: props.filters.moderator_id || '',
  start_date: props.filters.start_date || '',
  end_date: props.filters.end_date || '',
  source_type: props.filters.source_type || ''
})

const showAddBonusModal = ref(false)
const showDetailsModal = ref(false)
const selectedModerator = ref(null)
const moderatorStats = ref(null)

const bonusForm = ref({
  moderator_id: '',
  points: null,
  description: ''
})

// Données du graphique pour les détails du modérateur
const moderatorChartData = computed(() => {
  if (!moderatorStats.value?.pointsByDay) return null

  const dates = Object.keys(moderatorStats.value.pointsByDay)
  const datasets = [
    {
      label: 'Messages courts',
      data: dates.map(date => {
        const dayData = moderatorStats.value.pointsByDay[date]
        return dayData.find(d => d.type === 'message_short')?.total_points || 0
      }),
      borderColor: '#10B981',
      tension: 0.4
    },
    {
      label: 'Messages longs',
      data: dates.map(date => {
        const dayData = moderatorStats.value.pointsByDay[date]
        return dayData.find(d => d.type === 'message_long')?.total_points || 0
      }),
      borderColor: '#6366F1',
      tension: 0.4
    },
    {
      label: 'Bonus',
      data: dates.map(date => {
        const dayData = moderatorStats.value.pointsByDay[date]
        return dayData.find(d => d.type === 'bonus_admin')?.total_points || 0
      }),
      borderColor: '#F59E0B',
      tension: 0.4
    }
  ]

  return {
    labels: dates,
    datasets
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        callback: value => formatNumber(value)
      }
    }
  }
}

// Méthodes
const formatNumber = (number) => {
  return new Intl.NumberFormat('fr-FR').format(number)
}

const formatMoney = (amount) => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR'
  }).format(amount)
}

const applyFilters = () => {
  router.get('/admin/moderator-points', filters.value, {
    preserveState: true,
    preserveScroll: true
  })
}

const resetFilters = () => {
  filters.value = {
    moderator_id: '',
    start_date: '',
    end_date: '',
    source_type: ''
  }
  applyFilters()
}

const showModeratorDetails = async (moderator) => {
  selectedModerator.value = moderator
  showDetailsModal.value = true

  try {
    const response = await fetch(`/admin/moderator-points/${moderator.id}/stats`)
    moderatorStats.value = await response.json()
  } catch (error) {
    console.error('Erreur lors du chargement des statistiques:', error)
  }
}

const addBonus = async () => {
  try {
    // Vérifier que le formulaire est complet
    if (!bonusForm.value.moderator_id || !bonusForm.value.points || !bonusForm.value.description) {
      alert('Veuillez remplir tous les champs')
      return
    }

    const response = await fetch('/admin/moderator-points/bonus', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        'Accept': 'application/json'
      },
      body: JSON.stringify(bonusForm.value)
    })

    const data = await response.json()

    if (!response.ok) {
      throw new Error(data.message || 'Erreur lors de l\'ajout du bonus')
    }

    // Réinitialiser le formulaire et fermer le modal
    showAddBonusModal.value = false
    bonusForm.value = { 
      moderator_id: '', 
      points: null, 
      description: '' 
    }

    // Rafraîchir les données
    router.get('/admin/moderator-points', {}, {
      preserveState: true,
      preserveScroll: true,
      only: ['moderators', 'stats']
    })
  } catch (error) {
    console.error('Erreur:', error)
    alert(error.message)
  }
}

const exportData = () => {
  const queryString = new URLSearchParams(filters.value).toString()
  window.location.href = `/admin/moderator-points/export?${queryString}`
}
</script> 