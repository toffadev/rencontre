<template>
  <AdminLayout>
    <!-- Notification -->
    <div v-if="showNotification" :class="[
      'fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg z-50',
      notificationType === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
    ]">
      {{ notificationMessage }}
    </div>

    <!-- Modal de confirmation de suppression -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
          <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div
          class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div
                class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                <i class="fas fa-exclamation-triangle text-red-600"></i>
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                  Confirmer la suppression
                </h3>
                <div class="mt-2">
                  <p class="text-sm text-gray-500">
                    Êtes-vous sûr de vouloir supprimer cet événement ? Cette action est irréversible.
                  </p>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <button type="button" @click="confirmDelete"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
              Supprimer
            </button>
            <button type="button" @click="showDeleteModal = false"
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
              Annuler
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Formulaire d'ajout/modification -->
    <div v-if="showAddForm || editingEvent" class="bg-white shadow-sm rounded-lg p-6">
      <h2 class="text-lg font-medium text-gray-900 mb-6">
        {{ editingEvent ? 'Modifier l\'événement' : 'Ajouter un événement' }}
      </h2>

      <form @submit.prevent="handleFormSubmit" class="space-y-6">
        <!-- Titre -->
        <div>
          <label for="title" class="block text-sm font-medium text-gray-700">Titre</label>
          <input type="text" id="title" v-model="form.title"
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
            required>
        </div>

        <!-- Description -->
        <div>
          <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
          <textarea id="description" v-model="form.description" rows="4"
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"></textarea>
        </div>

        <!-- Adresse -->
        <div>
          <label for="address" class="block text-sm font-medium text-gray-700">Adresse</label>
          <input type="text" id="address" v-model="form.address"
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
            required>
        </div>

        <!-- Ville -->
        <div>
          <label for="city" class="block text-sm font-medium text-gray-700">Ville</label>
          <input type="text" id="city" v-model="form.city"
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm">
        </div>

        <!-- Pays -->
        <div>
          <label for="country" class="block text-sm font-medium text-gray-700">Pays</label>
          <input type="text" id="country" v-model="form.country"
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
            required>
        </div>

        <!-- Date de l'événement -->
        <div>
          <label for="event_date" class="block text-sm font-medium text-gray-700">Date et heure de l'événement</label>
          <input type="datetime-local" id="event_date" v-model="form.event_date"
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
            required>
        </div>

        <!-- Image -->
        <div>
          <label class="block text-sm font-medium text-gray-700">Image</label>
          <div class="mt-1 flex items-center space-x-4">
            <div class="flex-shrink-0">
              <div v-if="imagePreview"
                class="h-32 w-32 rounded-lg bg-gray-100 flex items-center justify-center overflow-hidden">
                <img :src="imagePreview" alt="Preview" class="h-32 w-32 object-cover">
              </div>
              <div v-else class="h-32 w-32 rounded-lg bg-gray-100 flex items-center justify-center">
                <i class="fas fa-image text-gray-400 text-3xl"></i>
              </div>
            </div>
            <div class="flex-1">
              <input type="file" ref="imageInput" @change="handleImageChange" accept="image/*" class="hidden">
              <button type="button" @click="$refs.imageInput.click()"
                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                <i class="fas fa-upload mr-2"></i>
                {{ form.image ? 'Changer l\'image' : 'Choisir une image' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Lien de billetterie -->
        <div>
          <label for="ticket_link" class="block text-sm font-medium text-gray-700">Lien de billetterie</label>
          <input type="url" id="ticket_link" v-model="form.ticket_link" placeholder="https://ticketing.com/..."
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm">
        </div>

        <!-- Complet -->
        <div class="flex items-center">
          <input type="checkbox" id="is_sold_out" v-model="form.is_sold_out"
            class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
          <label for="is_sold_out" class="ml-2 block text-sm text-gray-900">
            Événement complet
          </label>
        </div>

        <!-- Visibilité -->
        <div class="flex items-center">
          <input type="checkbox" id="is_visible" v-model="form.is_visible"
            class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
          <label for="is_visible" class="ml-2 block text-sm text-gray-900">
            Rendre cet événement visible
          </label>
        </div>

        <!-- Boutons -->
        <div class="flex justify-end space-x-3">
          <button type="button" @click="cancelForm"
            class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
            Annuler
          </button>
          <button type="submit"
            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
            {{ editingEvent ? 'Mettre à jour' : 'Créer' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Liste des événements -->
    <div v-else>
      <!-- Actions Bar -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 space-y-4 md:space-y-0">
        <div class="relative w-full md:w-64">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <i class="fas fa-search text-gray-400"></i>
          </div>
          <input type="text" v-model="search"
            class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
            placeholder="Rechercher un événement...">
        </div>

        <div class="flex space-x-3">
          <div class="relative">
            <select v-model="statusFilter"
              class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md">
              <option value="">Tous les statuts</option>
              <option value="upcoming">À venir</option>
              <option value="past">Passés</option>
              <option value="sold_out">Complet</option>
            </select>
          </div>

          <button @click="showAddForm = true"
            class="flex items-center space-x-2 px-4 py-2 bg-primary text-white rounded-md shadow-sm text-sm font-medium hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-primary">
            <i class="fas fa-plus"></i>
            <span>Ajouter un événement</span>
          </button>
        </div>
      </div>

      <!-- Quick Stats -->
      <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white shadow rounded-lg p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-500">À venir</p>
              <h3 class="text-2xl font-bold text-dark">{{ stats.upcoming }}</h3>
            </div>
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
              <i class="fas fa-calendar-alt text-xl"></i>
            </div>
          </div>
        </div>

        <div class="bg-white shadow rounded-lg p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-500">Passés</p>
              <h3 class="text-2xl font-bold text-dark">{{ stats.past }}</h3>
            </div>
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
              <i class="fas fa-history text-xl"></i>
            </div>
          </div>
        </div>

        <div class="bg-white shadow rounded-lg p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-500">Complet</p>
              <h3 class="text-2xl font-bold text-dark">{{ stats.soldOut }}</h3>
            </div>
            <div class="p-3 rounded-full bg-red-100 text-red-600">
              <i class="fas fa-ticket-alt text-xl"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Image
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Titre
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Lieu
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Date
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Statut
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="event in filteredEvents" :key="event.id">
              <td class="px-6 py-4 whitespace-nowrap">
                <img :src="event.image" :alt="event.title" class="h-12 w-12 rounded-lg object-cover">
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">{{ event.title }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ event.city ? `${event.city}, ` : '' }}{{ event.country }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ formatDate(event.event_date) }}</div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="[
                  'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                  getStatusClass(event)
                ]">
                  {{ getStatusText(event) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <button @click="editEvent(event)" class="text-primary hover:text-primary-dark mr-3">
                  <i class="fas fa-edit"></i>
                </button>
                <button @click="deleteEvent(event.id)" class="text-red-600 hover:text-red-900">
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import AdminLayout from '../Layouts/AdminLayout.vue'

// Données statiques
const events = ref([
  {
    id: 1,
    title: 'Concert à Paris',
    description: 'Un concert exceptionnel dans la capitale',
    address: '123 Avenue des Champs-Élysées',
    city: 'Paris',
    country: 'France',
    event_date: '2023-06-15T20:00:00',
    ticket_link: 'https://tickets.com/paris-concert',
    is_sold_out: false,
    is_visible: true,
    image: 'https://images.unsplash.com/photo-1501281668745-f7f57925c3b4?ixlib=rb-1.2.1&auto=format&fit=crop&w=300&h=300&q=80'
  },
  {
    id: 2,
    title: 'Festival de musique',
    description: 'Le plus grand festival de l\'été',
    address: 'Parc des Expositions',
    city: 'Lyon',
    country: 'France',
    event_date: '2023-07-22T18:30:00',
    ticket_link: 'https://tickets.com/lyon-festival',
    is_sold_out: true,
    is_visible: true,
    image: 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?ixlib=rb-1.2.1&auto=format&fit=crop&w=300&h=300&q=80'
  },
  {
    id: 3,
    title: 'Showcase privé',
    description: 'Présentation du nouvel album',
    address: 'Club Privé',
    city: 'Marseille',
    country: 'France',
    event_date: '2023-05-10T19:00:00',
    ticket_link: 'https://tickets.com/marseille-showcase',
    is_sold_out: false,
    is_visible: true,
    image: 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?ixlib=rb-1.2.1&auto=format&fit=crop&w=300&h=300&q=80'
  }
])

// État local
const search = ref('')
const statusFilter = ref('')
const showDeleteModal = ref(false)
const eventToDelete = ref(null)
const showNotification = ref(false)
const notificationMessage = ref('')
const notificationType = ref('success')
const showAddForm = ref(false)
const editingEvent = ref(null)

// Form state
const form = ref({
  title: '',
  description: '',
  address: '',
  city: '',
  country: '',
  event_date: '',
  ticket_link: '',
  is_sold_out: false,
  is_visible: true,
  image: null
})

const imagePreview = ref(null)
const imageInput = ref(null)

// Computed
const filteredEvents = computed(() => {
  let filtered = [...events.value]

  if (search.value) {
    const searchLower = search.value.toLowerCase()
    filtered = filtered.filter(event =>
      event.title.toLowerCase().includes(searchLower) ||
      (event.city && event.city.toLowerCase().includes(searchLower)) ||
      event.country.toLowerCase().includes(searchLower)
    )
  }

  if (statusFilter.value) {
    const now = new Date()

    if (statusFilter.value === 'upcoming') {
      filtered = filtered.filter(event => new Date(event.event_date) >= now)
    } else if (statusFilter.value === 'past') {
      filtered = filtered.filter(event => new Date(event.event_date) < now)
    } else if (statusFilter.value === 'sold_out') {
      filtered = filtered.filter(event => event.is_sold_out)
    }
  }

  return filtered
})

const stats = computed(() => {
  const now = new Date()

  return {
    upcoming: events.value.filter(e => new Date(e.event_date) >= now).length,
    past: events.value.filter(e => new Date(e.event_date) < now).length,
    soldOut: events.value.filter(e => e.is_sold_out).length
  }
})

// Méthodes
const formatDate = (date) => {
  return new Date(date).toLocaleDateString('fr-FR', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const formatDateTimeForInput = (date) => {
  const d = new Date(date)
  return d.toISOString().slice(0, 16)
}

const getStatusClass = (event) => {
  const now = new Date()
  const eventDate = new Date(event.event_date)

  if (event.is_sold_out) {
    return 'bg-red-100 text-red-800'
  } else if (eventDate < now) {
    return 'bg-gray-100 text-gray-800'
  } else {
    return 'bg-green-100 text-green-800'
  }
}

const getStatusText = (event) => {
  const now = new Date()
  const eventDate = new Date(event.event_date)

  if (event.is_sold_out) {
    return 'Complet'
  } else if (eventDate < now) {
    return 'Passé'
  } else {
    return 'À venir'
  }
}

const handleImageChange = (e) => {
  const file = e.target.files[0]
  if (file) {
    form.value.image = file
    imagePreview.value = URL.createObjectURL(file)
  }
}

const editEvent = (event) => {
  editingEvent.value = event
  form.value = {
    title: event.title,
    description: event.description,
    address: event.address,
    city: event.city,
    country: event.country,
    event_date: formatDateTimeForInput(event.event_date),
    ticket_link: event.ticket_link,
    is_sold_out: event.is_sold_out,
    is_visible: event.is_visible,
    image: null
  }
  imagePreview.value = event.image
}

const deleteEvent = (id) => {
  eventToDelete.value = id
  showDeleteModal.value = true
}

const confirmDelete = () => {
  if (eventToDelete.value) {
    // Simulation de suppression
    events.value = events.value.filter(event => event.id !== eventToDelete.value)

    // Notification
    notificationMessage.value = 'Événement supprimé avec succès'
    notificationType.value = 'success'
    showNotification.value = true
    setTimeout(() => showNotification.value = false, 3000)
  }
  showDeleteModal.value = false
  eventToDelete.value = null
}

const handleFormSubmit = () => {
  if (editingEvent.value) {
    // Simulation de mise à jour
    const index = events.value.findIndex(e => e.id === editingEvent.value.id)
    if (index !== -1) {
      events.value[index] = {
        ...events.value[index],
        ...form.value,
        image: imagePreview.value || events.value[index].image
      }
    }

    // Notification
    notificationMessage.value = 'Événement mis à jour avec succès'
    notificationType.value = 'success'
    showNotification.value = true
    setTimeout(() => showNotification.value = false, 3000)
  } else {
    // Simulation de création
    const newEvent = {
      id: Math.max(...events.value.map(e => e.id)) + 1,
      ...form.value,
      image: imagePreview.value || 'https://images.unsplash.com/photo-1501612780327-45045538702b?ixlib=rb-1.2.1&auto=format&fit=crop&w=300&h=300&q=80'
    }
    events.value.push(newEvent)

    // Notification
    notificationMessage.value = 'Événement créé avec succès'
    notificationType.value = 'success'
    showNotification.value = true
    setTimeout(() => showNotification.value = false, 3000)
  }

  cancelForm()
}

const cancelForm = () => {
  showAddForm.value = false
  editingEvent.value = null
  form.value = {
    title: '',
    description: '',
    address: '',
    city: '',
    country: '',
    event_date: '',
    ticket_link: '',
    is_sold_out: false,
    is_visible: true,
    image: null
  }
  imagePreview.value = null
}
</script>