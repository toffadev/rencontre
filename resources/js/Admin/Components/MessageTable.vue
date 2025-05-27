<template>
  <div class="bg-white rounded-lg shadow-sm overflow-hidden">
    <!-- Table Header -->
    <div class="flex items-center justify-between p-4 border-b">
      <div class="flex items-center space-x-2">
        <button 
          v-if="selectedMessages.length > 0"
          class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
          @click="markAsRead"
        >
          <i class="fas fa-check-circle mr-1"></i>
          Marquer comme lu
        </button>
        <button 
          v-if="selectedMessages.length > 0"
          class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
          @click="markAsUnread"
        >
          <i class="fas fa-times-circle mr-1"></i>
          Marquer comme non lu
        </button>
        <button 
          v-if="selectedMessages.length > 0"
          class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
          @click="deleteSelected"
        >
          <i class="fas fa-trash mr-1"></i>
          Supprimer
        </button>
      </div>
      <div class="flex items-center space-x-4">
        <select 
          v-model="perPage"
          class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md"
          @change="$emit('update:per-page', perPage)"
        >
          <option value="15">15 par page</option>
          <option value="30">30 par page</option>
          <option value="50">50 par page</option>
          <option value="100">100 par page</option>
        </select>
      </div>
    </div>

    <!-- Table -->
    <table class="min-w-full divide-y divide-gray-200">
      <thead class="bg-gray-50">
        <tr>
          <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-8">
            <input 
              type="checkbox" 
              :checked="selectedMessages.length === messages.length"
              @change="toggleSelectAll"
              class="focus:ring-primary h-4 w-4 text-primary border-gray-300 rounded"
            >
          </th>
          <th 
            v-for="column in columns" 
            :key="column.key"
            scope="col" 
            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
            @click="sort(column.key)"
          >
            {{ column.label }}
            <i 
              v-if="sortField === column.key" 
              :class="[
                'fas ml-1',
                sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down'
              ]"
            ></i>
          </th>
        </tr>
      </thead>
      <tbody class="bg-white divide-y divide-gray-200">
        <tr v-for="message in messages" :key="message.id" :class="{ 'bg-gray-50': selectedMessages.includes(message.id) }">
          <td class="px-6 py-4 whitespace-nowrap w-8">
            <input 
              type="checkbox" 
              :value="message.id"
              v-model="selectedMessages"
              class="focus:ring-primary h-4 w-4 text-primary border-gray-300 rounded"
            >
          </td>
          <td class="px-6 py-4 whitespace-nowrap">
            {{ formatDate(message.created_at) }}
          </td>
          <td class="px-6 py-4">
            <div class="text-sm text-gray-900 max-w-md truncate">{{ message.content }}</div>
          </td>
          <td class="px-6 py-4 whitespace-nowrap">
            <div class="flex items-center">
              <div class="text-sm font-medium text-gray-900">
                {{ message.client.name }}
              </div>
              <span class="ml-1 text-xs text-gray-500">(#{{ message.client.id }})</span>
            </div>
          </td>
          <td class="px-6 py-4 whitespace-nowrap">
            <div class="flex items-center">
              <img 
                :src="message.profile.main_photo_path || 'https://via.placeholder.com/32'" 
                :alt="message.profile.name"
                class="h-8 w-8 rounded-full object-cover mr-2"
              >
              <div class="text-sm font-medium text-gray-900">
                {{ message.profile.name }}
              </div>
            </div>
          </td>
          <td class="px-6 py-4 whitespace-nowrap">
            <div v-if="message.moderator" class="flex items-center">
              <div class="text-sm font-medium text-gray-900">
                {{ message.moderator.name }}
              </div>
              <span class="ml-1 text-xs text-gray-500">(#{{ message.moderator.id }})</span>
            </div>
            <div v-else class="text-sm text-gray-500">-</div>
          </td>
          <td class="px-6 py-4 whitespace-nowrap">
            <span :class="[
              'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
              message.is_from_client ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'
            ]">
              {{ message.is_from_client ? 'Client → Profil' : 'Profil → Client' }}
            </span>
          </td>
          <td class="px-6 py-4 whitespace-nowrap">
            <span :class="[
              'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
              message.read_at ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
            ]">
              {{ message.read_at ? 'Lu' : 'Non lu' }}
            </span>
          </td>
          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
            {{ message.points_consumed }} points
          </td>
          <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
            <button 
              @click="$emit('view', message)"
              class="text-primary hover:text-primary-dark mr-3"
            >
              <i class="fas fa-eye"></i>
            </button>
            <button 
              @click="deleteMessage(message.id)"
              class="text-red-600 hover:text-red-900"
            >
              <i class="fas fa-trash"></i>
            </button>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Pagination -->
    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
      <div class="flex items-center justify-between">
        <div class="flex-1 flex justify-between sm:hidden">
          <button
            @click="$emit('page-change', currentPage - 1)"
            :disabled="currentPage === 1"
            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
          >
            Précédent
          </button>
          <button
            @click="$emit('page-change', currentPage + 1)"
            :disabled="currentPage === lastPage"
            class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
          >
            Suivant
          </button>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
          <div>
            <p class="text-sm text-gray-700">
              Affichage de
              <span class="font-medium">{{ from }}</span>
              à
              <span class="font-medium">{{ to }}</span>
              sur
              <span class="font-medium">{{ total }}</span>
              résultats
            </p>
          </div>
          <div>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
              <button
                @click="$emit('page-change', currentPage - 1)"
                :disabled="currentPage === 1"
                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
              >
                <span class="sr-only">Précédent</span>
                <i class="fas fa-chevron-left"></i>
              </button>
              <button
                v-for="page in pages"
                :key="page"
                @click="$emit('page-change', page)"
                :class="[
                  'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                  page === currentPage
                    ? 'z-10 bg-primary border-primary text-white'
                    : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                ]"
              >
                {{ page }}
              </button>
              <button
                @click="$emit('page-change', currentPage + 1)"
                :disabled="currentPage === lastPage"
                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
              >
                <span class="sr-only">Suivant</span>
                <i class="fas fa-chevron-right"></i>
              </button>
            </nav>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
  messages: {
    type: Array,
    required: true
  },
  currentPage: {
    type: Number,
    required: true
  },
  lastPage: {
    type: Number,
    required: true
  },
  from: {
    type: Number,
    required: true
  },
  to: {
    type: Number,
    required: true
  },
  total: {
    type: Number,
    required: true
  }
})

const emit = defineEmits(['update:sort', 'page-change', 'view', 'update:per-page'])

const selectedMessages = ref([])
const perPage = ref(15)
const sortField = ref('created_at')
const sortDirection = ref('desc')

const columns = [
  { key: 'created_at', label: 'Date/Heure' },
  { key: 'content', label: 'Contenu' },
  { key: 'client_id', label: 'Client' },
  { key: 'profile_id', label: 'Profil' },
  { key: 'moderator_id', label: 'Modérateur' },
  { key: 'is_from_client', label: 'Type' },
  { key: 'read_at', label: 'Statut' },
  { key: 'points_consumed', label: 'Points' },
  { key: 'actions', label: 'Actions' }
]

const pages = computed(() => {
  const range = []
  for (let i = 1; i <= props.lastPage; i++) {
    range.push(i)
  }
  return range
})

const formatDate = (date) => {
  return new Date(date).toLocaleString('fr-FR', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const toggleSelectAll = (event) => {
  if (event.target.checked) {
    selectedMessages.value = props.messages.map(m => m.id)
  } else {
    selectedMessages.value = []
  }
}

const sort = (field) => {
  if (sortField.value === field) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDirection.value = 'asc'
  }
  emit('update:sort', { field: sortField.value, direction: sortDirection.value })
}

const markAsRead = async () => {
  try {
    await axios.post('/admin/messages/mark-as-read', {
      message_ids: selectedMessages.value
    })
    selectedMessages.value = []
    emit('page-change', props.currentPage)
  } catch (error) {
    console.error('Erreur lors du marquage comme lu:', error)
  }
}

const markAsUnread = async () => {
  try {
    await axios.post('/admin/messages/mark-as-unread', {
      message_ids: selectedMessages.value
    })
    selectedMessages.value = []
    emit('page-change', props.currentPage)
  } catch (error) {
    console.error('Erreur lors du marquage comme non lu:', error)
  }
}

const deleteMessage = async (id) => {
  if (!confirm('Êtes-vous sûr de vouloir supprimer ce message ?')) return

  try {
    await axios.delete('/admin/messages/destroy', {
      data: { message_ids: [id] }
    })
    emit('page-change', props.currentPage)
  } catch (error) {
    console.error('Erreur lors de la suppression:', error)
  }
}

const deleteSelected = async () => {
  if (!confirm(`Êtes-vous sûr de vouloir supprimer ${selectedMessages.value.length} message(s) ?`)) return

  try {
    await axios.delete('/admin/messages/destroy', {
      data: { message_ids: selectedMessages.value }
    })
    selectedMessages.value = []
    emit('page-change', props.currentPage)
  } catch (error) {
    console.error('Erreur lors de la suppression:', error)
  }
}
</script> 