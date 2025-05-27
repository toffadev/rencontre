<template>
  <AdminLayout>
    <!-- Notification -->
    <div 
      v-if="showNotification"
      :class="[
        'fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg z-50',
        notificationType === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
      ]"
    >
      {{ notificationMessage }}
    </div>

    <div class="py-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- En-tête avec titre et statistiques -->
        <div class="flex justify-between items-center mb-6">
          <h1 class="text-2xl font-semibold text-gray-900">Gestion des Messages</h1>
        </div>

        <!-- Quick Stats -->
        <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-6">
          <div class="bg-white shadow rounded-lg p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-500">Total messages</p>
                <h3 class="text-2xl font-bold text-dark">{{ stats.total_messages }}</h3>
              </div>
              <div class="p-3 rounded-full bg-blue-100 text-secondary">
                <i class="fas fa-envelope text-xl"></i>
              </div>
            </div>
          </div>
          
          <div class="bg-white shadow rounded-lg p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-500">Messages aujourd'hui</p>
                <h3 class="text-2xl font-bold text-dark">{{ stats.messages_today }}</h3>
              </div>
              <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-calendar-day text-xl"></i>
              </div>
            </div>
          </div>
          
          <div class="bg-white shadow rounded-lg p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-500">Messages non lus</p>
                <h3 class="text-2xl font-bold text-dark">{{ stats.total_unread }}</h3>
              </div>
              <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                <i class="fas fa-envelope-open text-xl"></i>
              </div>
            </div>
          </div>

          <div class="bg-white shadow rounded-lg p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-500">Points aujourd'hui</p>
                <h3 class="text-2xl font-bold text-dark">{{ stats.points_consumed_today }}</h3>
              </div>
              <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                <i class="fas fa-coins text-xl"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Filtres -->
        <FilterBar v-model:filters="filters" class="mb-6" />

        <!-- Table des messages avec défilement -->
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
          <div class="w-full overflow-x-auto">
            <div class="min-w-max">
              <MessageTable
                :messages="messages.data"
                :current-page="messages.current_page"
                :last-page="messages.last_page"
                :from="messages.from"
                :to="messages.to"
                :total="messages.total"
                @update:sort="updateSort"
                @page-change="changePage"
                @update:per-page="updatePerPage"
                @view="viewMessage"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Modal de visualisation du message -->
      <Modal :show="showMessageModal" @close="showMessageModal = false">
        <div class="p-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Détails du message</h3>
          <div v-if="selectedMessage" class="space-y-4">
            <div>
              <p class="text-sm font-medium text-gray-500">Date</p>
              <p class="mt-1">{{ formatDate(selectedMessage.created_at) }}</p>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-500">Client</p>
              <p class="mt-1">{{ selectedMessage.client.name }}</p>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-500">Profil</p>
              <p class="mt-1">{{ selectedMessage.profile.name }}</p>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-500">Contenu</p>
              <p class="mt-1">{{ selectedMessage.content }}</p>
            </div>
          </div>
        </div>
      </Modal>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import axios from 'axios'
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import FilterBar from '@/Admin/Components/FilterBar.vue'
import MessageTable from '@/Admin/Components/MessageTable.vue'
import Modal from '@/Admin/Components/Modal.vue'

const messages = ref({
  data: [],
  current_page: 1,
  last_page: 1,
  from: 0,
  to: 0,
  total: 0
})

const stats = ref({
  total_messages: 0,
  total_unread: 0,
  messages_today: 0,
  points_consumed_today: 0
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

const sort = ref({
  field: 'created_at',
  direction: 'desc'
})

const perPage = ref(15)
const showMessageModal = ref(false)
const selectedMessage = ref(null)
const showNotification = ref(false)
const notificationMessage = ref('')
const notificationType = ref('success')

const loadMessages = async () => {
  try {
    const response = await axios.get('/admin/messages/list', {
      params: {
        ...filters.value,
        sort_field: sort.value.field,
        sort_direction: sort.value.direction,
        per_page: perPage.value,
        page: messages.value.current_page
      }
    })
    messages.value = response.data.messages
    stats.value = response.data.stats
  } catch (error) {
    console.error('Erreur lors du chargement des messages:', error)
    notificationMessage.value = 'Erreur lors du chargement des messages'
    notificationType.value = 'error'
    showNotification.value = true
    setTimeout(() => showNotification.value = false, 3000)
  }
}

const updateSort = (newSort) => {
  sort.value = newSort
  loadMessages()
}

const changePage = (page) => {
  messages.value.current_page = page
  loadMessages()
}

const updatePerPage = (newPerPage) => {
  perPage.value = newPerPage
  loadMessages()
}

const viewMessage = (message) => {
  selectedMessage.value = message
  showMessageModal.value = true
}

const formatDate = (date) => {
  return new Date(date).toLocaleString('fr-FR', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

watch(filters, () => {
  messages.value.current_page = 1
  loadMessages()
}, { deep: true })

onMounted(() => {
  loadMessages()
})
</script>

<style>
.min-w-max {
  min-width: max-content;
}

.overflow-x-auto {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

/* Assure que la table prend toute la largeur disponible */
.w-full {
  width: 100%;
}
</style> 