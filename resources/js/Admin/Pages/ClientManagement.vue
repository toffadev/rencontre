<template>
  <AdminLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">
        Gestion des Clients
      </h2>
    </template>

    <div class="py-12">
      <!-- Statistiques Globales -->
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <StatCard title="Total Clients" :value="stats.total_clients" icon="users" />
          <StatCard title="Clients Actifs" :value="stats.active_clients" icon="user-check" />
          <StatCard 
            title="Revenu Total" 
            :value="formatCurrency(stats.total_revenue)" 
            icon="euro-sign" 
          />
          <StatCard 
            title="Revenu Moyen/Client" 
            :value="formatCurrency(stats.average_revenue_per_client)" 
            icon="chart-line" 
          />
          <StatCard 
            title="Total Messages" 
            :value="stats.total_messages" 
            icon="message-square" 
          />
          <StatCard 
            title="Total Points Vendus" 
            :value="stats.total_points_sold" 
            icon="star" 
          />
        </div>
      </div>

      <!-- Filtres -->
      <div class="mx-auto mt-8 max-w-7xl sm:px-6 lg:px-8">
        <div class="bg-white p-6 shadow sm:rounded-lg">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">
                Recherche
              </label>
              <input
                type="text"
                v-model="filters.search"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                placeholder="Nom ou email"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">
                Date d'inscription
              </label>
              <input
                type="date"
                v-model="filters.registration_date"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">
                Statut
              </label>
              <select
                v-model="filters.status"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              >
                <option value="">Tous</option>
                <option value="active">Actif</option>
                <option value="inactive">Inactif</option>
                <option value="suspended">Suspendu</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">
                Points minimum dépensés
              </label>
              <input
                type="number"
                v-model="filters.min_spent"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              />
            </div>
          </div>
          <div class="mt-4 flex justify-end space-x-3">
            <button
              @click="resetFilters"
              class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
            >
              Réinitialiser
            </button>
            <button
              @click="applyFilters"
              class="inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
            >
              Appliquer
            </button>
          </div>
        </div>
      </div>

      <!-- Liste des Clients -->
      <div class="mx-auto mt-8 max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow sm:rounded-lg">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                  Client
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                  Statistiques
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                  Points
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                  Activité
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
              <tr v-for="client in clients.data" :key="client.id">
                <td class="whitespace-nowrap px-6 py-4">
                  <div class="flex items-center">
                    <div>
                      <div class="font-medium text-gray-900">{{ client.name }}</div>
                      <div class="text-sm text-gray-500">{{ client.email }}</div>
                    </div>
                  </div>
                </td>
                <td class="whitespace-nowrap px-6 py-4">
                  <div class="text-sm text-gray-900">
                    Messages: {{ client.total_messages }}
                  </div>
                  <div class="text-sm text-gray-500">
                    Conv. actives: {{ client.active_conversations }}
                  </div>
                </td>
                <td class="whitespace-nowrap px-6 py-4">
                  <div class="text-sm text-gray-900">
                    Solde: {{ client.points_balance }}
                  </div>
                  <div class="text-sm text-gray-500">
                    Dépensés: {{ client.points_spent }}
                  </div>
                </td>
                <td class="whitespace-nowrap px-6 py-4">
                  <div class="text-sm text-gray-900">
                    Dernière connexion: {{ client.last_login }}
                  </div>
                  <div class="text-sm text-gray-500">
                    Inscrit le: {{ client.registration_date }}
                  </div>
                </td>
                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                  <Link
                    :href="route('admin.clients.show', client.id)"
                    class="text-indigo-600 hover:text-indigo-900"
                  >
                    Détails
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
          <Pagination :links="clients.links" class="mt-6" />
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import StatCard from '@/Admin/Components/ClientManagement/StatCard.vue'
import Pagination from '@/Admin/Components/ClientManagement/Pagination.vue'

// Importer la fonction route depuis le contexte global
const route = window.route

const props = defineProps({
  clients: Object,
  stats: Object,
  filters: Object
})

const filters = ref({
  search: props.filters?.search || '',
  registration_date: props.filters?.registration_date || '',
  status: props.filters?.status || '',
  min_spent: props.filters?.min_spent || '',
})

// Surveiller tous les changements de filtres
watch(filters.value, (newFilters) => {
  router.get(route('admin.clients.index'), newFilters, {
    preserveState: true,
    preserveScroll: true,
  })
}, { deep: true })

const resetFilters = () => {
  filters.value = {
    search: '',
    registration_date: '',
    status: '',
    min_spent: '',
  }
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR'
  }).format(value)
}
</script> 