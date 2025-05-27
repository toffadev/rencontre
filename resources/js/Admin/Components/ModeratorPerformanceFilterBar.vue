<template>
  <div class="bg-white rounded-lg shadow p-6">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Période</label>
        <select 
          v-model="localPeriod"
          class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
        >
          <option value="today">Aujourd'hui</option>
          <option value="yesterday">Hier</option>
          <option value="week">Cette Semaine</option>
          <option value="month">Ce Mois</option>
          <option value="custom">Période Personnalisée</option>
        </select>
      </div>

      <div v-if="localPeriod === 'custom'" class="lg:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-2">Plage de dates</label>
        <div class="flex space-x-4">
          <input 
            type="date" 
            v-model="localDateRange.start"
            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
          />
          <input 
            type="date" 
            v-model="localDateRange.end"
            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
          />
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Modérateur</label>
        <select 
          v-model="localModerator"
          class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
        >
          <option value="">Tous les modérateurs</option>
          <option v-for="mod in moderators" :key="mod.id" :value="mod.id">
            {{ mod.name }}
          </option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Profil</label>
        <select 
          v-model="localProfile"
          class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
        >
          <option value="">Tous les profils</option>
          <option v-for="profile in profiles" :key="profile.id" :value="profile.id">
            {{ profile.name }}
          </option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Niveau de performance</label>
        <select 
          v-model="localPerformanceLevel"
          class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
        >
          <option value="">Tous les niveaux</option>
          <option value="excellent">Excellent</option>
          <option value="bon">Bon</option>
          <option value="moyen">Moyen</option>
          <option value="faible">Faible</option>
        </select>
      </div>
    </div>

    <div class="mt-4 flex justify-end space-x-4">
      <button 
        @click="resetFilters"
        class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
      >
        Réinitialiser
      </button>
      <button 
        @click="applyFilters"
        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
      >
        Appliquer les filtres
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  period: {
    type: String,
    default: 'week'
  },
  dateRange: {
    type: Object,
    default: () => ({ start: null, end: null })
  },
  moderator: {
    type: [String, Number],
    default: ''
  },
  profile: {
    type: [String, Number],
    default: ''
  },
  performanceLevel: {
    type: String,
    default: ''
  }
})

const emit = defineEmits([
  'update:period',
  'update:dateRange',
  'update:moderator',
  'update:profile',
  'update:performanceLevel',
  'filter'
])

const localPeriod = ref(props.period)
const localDateRange = ref(props.dateRange)
const localModerator = ref(props.moderator)
const localProfile = ref(props.profile)
const localPerformanceLevel = ref(props.performanceLevel)

const moderators = ref([])
const profiles = ref([])

// Load moderators and profiles
const loadOptions = async () => {
  try {
    const [moderatorsResponse, profilesResponse] = await Promise.all([
      axios.get('/admin/moderator-performance/moderators'),
      axios.get('/admin/moderator-performance/profiles')
    ])
    
    moderators.value = moderatorsResponse.data
    profiles.value = profilesResponse.data
  } catch (error) {
    console.error('Erreur lors du chargement des options de filtre:', error)
  }
}

watch(localPeriod, (newValue) => {
  if (newValue !== 'custom') {
    localDateRange.value = { start: null, end: null }
  }
  emit('update:period', newValue)
})

watch(localDateRange, (newValue) => emit('update:dateRange', newValue), { deep: true })
watch(localModerator, (newValue) => emit('update:moderator', newValue))
watch(localProfile, (newValue) => emit('update:profile', newValue))
watch(localPerformanceLevel, (newValue) => emit('update:performanceLevel', newValue))

const resetFilters = () => {
  localPeriod.value = 'week'
  localDateRange.value = { start: null, end: null }
  localModerator.value = ''
  localProfile.value = ''
  localPerformanceLevel.value = ''
  emit('filter')
}

const applyFilters = () => {
  emit('filter')
}

loadOptions()
</script> 