<template>
  <div class="bg-white p-4 rounded-lg shadow-sm mb-6">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Date Range Filter -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Période</label>
        <select 
          v-model="filters.date_range"
          class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md"
          @change="updateFilters"
        >
          <option value="">Toutes les dates</option>
          <option value="today">Aujourd'hui</option>
          <option value="yesterday">Hier</option>
          <option value="week">Cette semaine</option>
          <option value="custom">Période personnalisée</option>
        </select>

        <!-- Custom Date Range -->
        <div v-if="filters.date_range === 'custom'" class="mt-2 space-y-2">
          <input 
            type="date" 
            v-model="filters.start_date"
            class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary sm:text-sm"
            @change="updateFilters"
          >
          <input 
            type="date" 
            v-model="filters.end_date"
            class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-primary focus:border-primary sm:text-sm"
            @change="updateFilters"
          >
        </div>
      </div>

      <!-- Client Filter -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Client</label>
        <select 
          v-model="filters.client_id"
          class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md"
          @change="updateFilters"
        >
          <option value="">Tous les clients</option>
          <option v-for="client in filterOptions.clients" :key="client.id" :value="client.id">
            {{ client.name }}
          </option>
        </select>
      </div>

      <!-- Profile Filter -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Profil</label>
        <select 
          v-model="filters.profile_id"
          class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md"
          @change="updateFilters"
        >
          <option value="">Tous les profils</option>
          <option v-for="profile in filterOptions.profiles" :key="profile.id" :value="profile.id">
            {{ profile.name }}
          </option>
        </select>
      </div>

      <!-- Moderator Filter -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Modérateur</label>
        <select 
          v-model="filters.moderator_id"
          class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md"
          @change="updateFilters"
        >
          <option value="">Tous les modérateurs</option>
          <option v-for="moderator in filterOptions.moderators" :key="moderator.id" :value="moderator.id">
            {{ moderator.name }}
          </option>
        </select>
      </div>

      <!-- Message Type Filter -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Type de message</label>
        <select 
          v-model="filters.type"
          class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md"
          @change="updateFilters"
        >
          <option value="">Tous les types</option>
          <option value="client">Client → Profil</option>
          <option value="profile">Profil → Client</option>
        </select>
      </div>

      <!-- Read Status Filter -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
        <select 
          v-model="filters.read_status"
          class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md"
          @change="updateFilters"
        >
          <option value="">Tous les statuts</option>
          <option value="read">Lu</option>
          <option value="unread">Non lu</option>
        </select>
      </div>

      <!-- Reset Filters -->
      <div class="flex items-end">
        <button 
          @click="resetFilters"
          class="w-full bg-gray-100 text-gray-700 py-2 px-4 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
        >
          Réinitialiser les filtres
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const emit = defineEmits(['update:filters'])

const filterOptions = ref({
  clients: [],
  profiles: [],
  moderators: []
})

const filters = ref({
  date_range: '',
  start_date: '',
  end_date: '',
  client_id: '',
  profile_id: '',
  moderator_id: '',
  type: '',
  read_status: ''
})

const loadFilterOptions = async () => {
  try {
    const response = await axios.get('/admin/messages/filters')
    filterOptions.value = response.data
  } catch (error) {
    console.error('Erreur lors du chargement des options de filtres:', error)
  }
}

const updateFilters = () => {
  emit('update:filters', filters.value)
}

const resetFilters = () => {
  filters.value = {
    date_range: '',
    start_date: '',
    end_date: '',
    client_id: '',
    profile_id: '',
    moderator_id: '',
    type: '',
    read_status: ''
  }
  updateFilters()
}

onMounted(() => {
  loadFilterOptions()
})
</script> 