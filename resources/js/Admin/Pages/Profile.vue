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

    <!-- Modal de confirmation de suppression -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Overlay -->
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
          <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>

        <!-- Centrage de la modale -->
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Contenu de la modale -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                <i class="fas fa-exclamation-triangle text-red-600"></i>
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                  Confirmer la suppression
                </h3>
                <div class="mt-2">
                  <p class="text-sm text-gray-500">
                    Êtes-vous sûr de vouloir supprimer ce profil ? Cette action est irréversible.
                  </p>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <button
              type="button"
              @click="confirmDelete"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm"
            >
              Supprimer
            </button>
            <button
              type="button"
              @click="showDeleteModal = false"
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
            >
              Annuler
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Photo delete modal -->
    <div v-if="showDeletePhotoModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
          <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                <i class="fas fa-exclamation-triangle text-red-600"></i>
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                  Supprimer cette photo
                </h3>
                <div class="mt-2">
                  <p class="text-sm text-gray-500">
                    Êtes-vous sûr de vouloir supprimer cette photo ? Cette action est irréversible.
                  </p>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <button
              type="button"
              @click="confirmDeletePhoto"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm"
            >
              Supprimer
            </button>
            <button
              type="button"
              @click="showDeletePhotoModal = false"
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
            >
              Annuler
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Actions Bar -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 space-y-4 md:space-y-0">
      <div class="relative w-full md:w-64">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <i class="fas fa-search text-gray-400"></i>
        </div>
        <input 
          type="text" 
          v-model="searchQuery" 
          class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm" 
          placeholder="Rechercher un profil..."
        >
      </div>
      
      <div class="flex space-x-3">
        <div class="relative">
          <button 
            @click="showFilters = !showFilters" 
            class="flex items-center space-x-2 px-4 py-2 bg-white border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary"
          >
            <i class="fas fa-filter"></i>
            <span>Filtrer</span>
            <i class="fas" :class="showFilters ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
          </button>
          
          <!-- Filters Dropdown -->
          <div 
            v-if="showFilters" 
            class="absolute right-0 mt-2 w-56 origin-top-right bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5 z-10"
          >
            <div class="py-1">
              <div class="px-4 py-2 border-b border-gray-100">
                <label class="text-xs font-medium text-gray-500 uppercase">Genre</label>
                <select 
                  v-model="filters.gender" 
                  class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md"
                >
                  <option value="">Tous</option>
                  <option value="male">Homme</option>
                  <option value="female">Femme</option>
                  <option value="other">Autre</option>
                </select>
              </div>
              <div class="px-4 py-2 border-b border-gray-100">
                <label class="text-xs font-medium text-gray-500 uppercase">Statut</label>
                <select 
                  v-model="filters.status" 
                  class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md"
                >
                  <option value="">Tous</option>
                  <option value="active">Actifs</option>
                  <option value="inactive">Inactifs</option>
                </select>
              </div>
              <div class="px-4 py-2">
                <button 
                  @click="applyFilters"
                  class="w-full bg-primary text-white py-2 px-4 rounded-md text-sm hover:bg-opacity-90 transition duration-150"
                >
                  Appliquer
                </button>
              </div>
            </div>
          </div>
        </div>
        
        <button 
          @click="showAddForm = true"
          class="flex items-center space-x-2 px-4 py-2 bg-primary text-white rounded-md shadow-sm text-sm font-medium hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-primary"
        >
          <i class="fas fa-plus"></i>
          <span>Ajouter un profil</span>
        </button>
      </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-500">Total profils</p>
            <h3 class="text-2xl font-bold text-dark">{{ stats.total }}</h3>
          </div>
          <div class="p-3 rounded-full bg-blue-100 text-secondary">
            <i class="fas fa-users text-xl"></i>
          </div>
        </div>
      </div>
      
      <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-500">Profils actifs</p>
            <h3 class="text-2xl font-bold text-dark">{{ stats.active }}</h3>
          </div>
          <div class="p-3 rounded-full bg-green-100 text-green-600">
            <i class="fas fa-user-check text-xl"></i>
          </div>
        </div>
      </div>
      
      <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-500">Profils inactifs</p>
            <h3 class="text-2xl font-bold text-dark">{{ stats.inactive }}</h3>
          </div>
          <div class="p-3 rounded-full bg-red-100 text-red-600">
            <i class="fas fa-user-times text-xl"></i>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Profiles Table -->
    <div v-if="!showAddForm && !editingProfile" class="bg-white shadow-sm rounded-lg overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Photo
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Nom
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Genre
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Photos
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Statut
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="profile in filteredProfiles" :key="profile.id">
            <td class="px-6 py-4 whitespace-nowrap">
              <img 
                :src="profile.main_photo_path || 'https://via.placeholder.com/40'" 
                :alt="profile.name" 
                class="h-10 w-10 rounded-full object-cover"
              >
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium text-gray-900">{{ profile.name }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">{{ formatGender(profile.gender) }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">{{ profile.photos.length }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span :class="[
                'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                profile.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
              ]">
                {{ profile.status === 'active' ? 'Actif' : 'Inactif' }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
              <button @click="editProfile(profile)" class="text-primary hover:text-primary-dark mr-3">
                <i class="fas fa-edit"></i>
              </button>
              <button @click="deleteProfile(profile.id)" class="text-red-600 hover:text-red-900">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    
    <!-- Add/Edit Profile Form -->
    <div v-if="showAddForm || editingProfile" class="bg-white shadow-sm rounded-lg p-6">
      <h2 class="text-lg font-medium text-gray-900 mb-6">
        {{ editingProfile ? 'Modifier le profil' : 'Ajouter un profil' }}
      </h2>
      
      <form @submit.prevent="handleFormSubmit">
        <div class="space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="name" class="block text-sm font-medium text-gray-700">Nom</label>
              <input 
                type="text" 
                id="name" 
                v-model="form.name" 
                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
                required
              >
            </div>
            
            <div>
              <label for="gender" class="block text-sm font-medium text-gray-700">Genre</label>
              <select 
                id="gender" 
                v-model="form.gender" 
                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
                required
              >
                <option value="">Sélectionner un genre</option>
                <option value="male">Homme</option>
                <option value="female">Femme</option>
                <option value="other">Autre</option>
              </select>
            </div>
          </div>
          
          <div>
            <label for="bio" class="block text-sm font-medium text-gray-700">Biographie</label>
            <textarea 
              id="bio" 
              v-model="form.bio" 
              rows="3" 
              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
            ></textarea>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Photos</label>
            
            <!-- Photos existantes (pour l'édition) -->
            <div v-if="editingProfile && profilePhotos.length" class="mb-4">
              <div class="text-sm font-medium text-gray-700 mb-2">Photos existantes</div>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div v-for="photo in profilePhotos" :key="photo.id" class="relative">
                  <img :src="photo.path" :alt="'Photo de ' + form.name" class="w-full h-32 object-cover rounded-lg">
                  <div class="absolute top-2 right-2 flex space-x-1">
                    <button 
                      type="button"
                      @click="setMainPhoto(photo.id)"
                      :class="[
                        'p-1 rounded-full',
                        photo.path === form.main_photo_path ? 'bg-yellow-500' : 'bg-gray-200'
                      ]"
                      title="Définir comme principale"
                    >
                      <i class="fas fa-star text-white text-xs"></i>
                    </button>
                    <button 
                      type="button"
                      @click="deletePhoto(photo.id)"
                      class="p-1 rounded-full bg-red-500"
                      title="Supprimer"
                    >
                      <i class="fas fa-trash text-white text-xs"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Ajouter de nouvelles photos -->
            <div class="flex items-center space-x-4">
              <div class="flex-shrink-0">
                <div 
                  v-if="imagePreview" 
                  class="h-20 w-20 rounded-lg bg-gray-100 flex items-center justify-center"
                >
                  <img 
                    :src="imagePreview" 
                    alt="Preview" 
                    class="h-20 w-20 object-cover rounded-lg"
                  >
                </div>
                <div 
                  v-else 
                  class="h-20 w-20 rounded-lg bg-gray-100 flex items-center justify-center"
                >
                  <i class="fas fa-image text-gray-400 text-2xl"></i>
                </div>
              </div>
              <div class="flex-1">
                <input 
                  type="file" 
                  id="photos" 
                  ref="imageInput"
                  @change="handleImageChange" 
                  accept="image/*"
                  multiple
                  class="hidden"
                >
                <button 
                  type="button"
                  @click="$refs.imageInput.click()"
                  class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
                >
                  <i class="fas fa-upload mr-2"></i>
                  {{ form.photos?.length ? 'Changer les photos' : 'Choisir des photos' }}
                </button>
                <p v-if="form.photos?.length" class="mt-2 text-sm text-gray-500">
                  {{ form.photos.length }} photo(s) sélectionnée(s)
                </p>
              </div>
            </div>
          </div>
          
          <div>
            <label for="status" class="block text-sm font-medium text-gray-700">Statut</label>
            <select 
              id="status" 
              v-model="form.status" 
              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
              required
            >
              <option value="active">Actif</option>
              <option value="inactive">Inactif</option>
            </select>
          </div>
        </div>
        
        <div class="mt-6 flex items-center space-x-3">
          <button 
            type="submit" 
            class="bg-primary text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-primary"
          >
            {{ editingProfile ? 'Mettre à jour' : 'Ajouter' }}
          </button>
          <button 
            type="button" 
            @click="cancelForm" 
            class="bg-gray-100 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500"
          >
            Annuler
          </button>
        </div>
      </form>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import AdminLayout from '../Layouts/AdminLayout.vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  profiles: {
    type: Array,
    required: true
  },
  flash: {
    type: Object,
    default: () => ({})
  }
})

// Search and filters
const searchQuery = ref('')
const showFilters = ref(false)
const filters = ref({
  gender: '',
  status: ''
})

// Form display control
const showAddForm = ref(false)
const editingProfile = ref(null)

// Form data
const form = ref({
  name: '',
  gender: '',
  bio: '',
  status: 'active',
  photos: [],
  main_photo_path: null
})

const imagePreview = ref(null)
const imageInput = ref(null)
const profilePhotos = ref([])

// Notifications
const showNotification = ref(false)
const notificationMessage = ref('')
const notificationType = ref('success')

// Delete modals
const showDeleteModal = ref(false)
const profileToDelete = ref(null)
const showDeletePhotoModal = ref(false)
const photoToDelete = ref(null)

// Computed properties
const filteredProfiles = computed(() => {
  let result = [...props.profiles]
  
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(profile => 
      profile.name.toLowerCase().includes(query)
    )
  }
  
  if (filters.value.gender) {
    result = result.filter(profile => profile.gender === filters.value.gender)
  }
  
  if (filters.value.status) {
    result = result.filter(profile => profile.status === filters.value.status)
  }
  
  return result
})

const stats = computed(() => {
  return {
    total: props.profiles.length,
    active: props.profiles.filter(p => p.status === 'active').length,
    inactive: props.profiles.filter(p => p.status === 'inactive').length,
  }
})

// Methods
const formatGender = (gender) => {
  const genders = {
    'male': 'Homme',
    'female': 'Femme',
    'other': 'Autre'
  }
  return genders[gender] || 'Non spécifié'
}

const applyFilters = () => {
  showFilters.value = false
}

const editProfile = (profile) => {
  editingProfile.value = profile
  form.value = {
    name: profile.name,
    gender: profile.gender,
    bio: profile.bio || '',
    status: profile.status,
    photos: [],
    main_photo_path: profile.main_photo_path
  }
  profilePhotos.value = profile.photos || []
  imagePreview.value = profile.main_photo_path
}

const deleteProfile = (id) => {
  profileToDelete.value = id
  showDeleteModal.value = true
}

const confirmDelete = () => {
  if (profileToDelete.value) {
    router.delete(`/admin/profiles/${profileToDelete.value}`, {
      onSuccess: () => {
        notificationMessage.value = 'Profil supprimé avec succès'
        notificationType.value = 'success'
        showNotification.value = true
        setTimeout(() => showNotification.value = false, 3000)
      },
      onError: (errors) => {
        notificationMessage.value = Object.values(errors)[0]
        notificationType.value = 'error'
        showNotification.value = true
        setTimeout(() => showNotification.value = false, 3000)
      }
    })
  }
  showDeleteModal.value = false
  profileToDelete.value = null
}

const deletePhoto = (id) => {
  photoToDelete.value = id
  showDeletePhotoModal.value = true
}

const confirmDeletePhoto = () => {
  if (photoToDelete.value) {
    router.delete(`/admin/profile-photos`, {
      data: {
        photo_id: photoToDelete.value
      },
      onSuccess: () => {
        // Update the local profilePhotos array
        const index = profilePhotos.value.findIndex(photo => photo.id === photoToDelete.value)
        if (index !== -1) {
          profilePhotos.value.splice(index, 1)
          
          // Check if we deleted the main photo and update form accordingly
          if (form.value.main_photo_path === profilePhotos.value[index]?.path) {
            form.value.main_photo_path = profilePhotos.value[0]?.path || null
          }
        }
        
        notificationMessage.value = 'Photo supprimée avec succès'
        notificationType.value = 'success'
        showNotification.value = true
        setTimeout(() => showNotification.value = false, 3000)
      },
      onError: (errors) => {
        notificationMessage.value = Object.values(errors)[0]
        notificationType.value = 'error'
        showNotification.value = true
        setTimeout(() => showNotification.value = false, 3000)
      }
    })
  }
  showDeletePhotoModal.value = false
  photoToDelete.value = null
}

const setMainPhoto = (photoId) => {
  if (editingProfile.value) {
    router.put(`/admin/profiles/${editingProfile.value.id}/main-photo`, {
      photo_id: photoId
    }, {
      onSuccess: () => {
        // Update the local state
        const photo = profilePhotos.value.find(p => p.id === photoId)
        if (photo) {
          form.value.main_photo_path = photo.path
        }
        
        notificationMessage.value = 'Photo principale mise à jour'
        notificationType.value = 'success'
        showNotification.value = true
        setTimeout(() => showNotification.value = false, 3000)
      },
      onError: (errors) => {
        notificationMessage.value = Object.values(errors)[0]
        notificationType.value = 'error'
        showNotification.value = true
        setTimeout(() => showNotification.value = false, 3000)
      }
    })
  }
}

const handleImageChange = (e) => {
  const files = Array.from(e.target.files)
  if (files.length > 0) {
    form.value.photos = files
    imagePreview.value = URL.createObjectURL(files[0])
  }
}

const handleFormSubmit = () => {
  const formData = new FormData()
  
  // Ajouter les champs du formulaire
  Object.keys(form.value).forEach(key => {
    if (key === 'photos') {
      form.value.photos.forEach(photo => {
        formData.append('photos[]', photo)
      })
    } else if (key !== 'main_photo_path') { // Skip main_photo_path, it's handled separately
      formData.append(key, form.value[key])
    }
  })

  if (editingProfile.value) {
    // Handle existing main photo
    if (form.value.main_photo_path) {
      const mainPhoto = profilePhotos.value.find(photo => photo.path === form.value.main_photo_path)
      if (mainPhoto) {
        formData.append('main_photo_id', mainPhoto.id)
      }
    }
    
    // Add PUT method for Laravel
    formData.append('_method', 'PUT')
    
    router.post(`/admin/profiles/${editingProfile.value.id}`, formData, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        notificationMessage.value = 'Profil mis à jour avec succès'
        notificationType.value = 'success'
        showNotification.value = true
        setTimeout(() => showNotification.value = false, 3000)
        cancelForm()
      },
      onError: (errors) => {
        notificationMessage.value = Object.values(errors)[0]
        notificationType.value = 'error'
        showNotification.value = true
        setTimeout(() => showNotification.value = false, 3000)
      }
    })
  } else {
    router.post('/admin/profiles', formData, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        notificationMessage.value = 'Profil créé avec succès'
        notificationType.value = 'success'
        showNotification.value = true
        setTimeout(() => showNotification.value = false, 3000)
        cancelForm()
      },
      onError: (errors) => {
        notificationMessage.value = Object.values(errors)[0]
        notificationType.value = 'error'
        showNotification.value = true
        setTimeout(() => showNotification.value = false, 3000)
      }
    })
  }
}

const cancelForm = () => {
  showAddForm.value = false
  editingProfile.value = null
  form.value = {
    name: '',
    gender: '',
    bio: '',
    status: 'active',
    photos: [],
    main_photo_path: null
  }
  profilePhotos.value = []
  imagePreview.value = null
}
</script>
