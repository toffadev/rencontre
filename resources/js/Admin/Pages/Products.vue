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
                    Êtes-vous sûr de vouloir supprimer ce produit ? Cette action est irréversible.
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
          placeholder="Rechercher un produit..."
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
                <label class="text-xs font-medium text-gray-500 uppercase">Catégorie</label>
                <select 
                  v-model="filters.category" 
                  class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md"
                >
                  <option value="">Toutes</option>
                  <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
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
          <span>Ajouter un produit</span>
        </button>
      </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-500">Produits en stock</p>
            <h3 class="text-2xl font-bold text-dark">{{ stats.inStock }}</h3>
          </div>
          <div class="p-3 rounded-full bg-green-100 text-secondary">
            <i class="fas fa-box-open text-xl"></i>
          </div>
        </div>
      </div>
      
      <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-500">Produits en rupture</p>
            <h3 class="text-2xl font-bold text-dark">{{ stats.outOfStock }}</h3>
          </div>
          <div class="p-3 rounded-full bg-red-100 text-red-600">
            <i class="fas fa-exclamation-circle text-xl"></i>
          </div>
        </div>
      </div>
      
      <div class="bg-white shadow rounded-lg p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-gray-500">Produits mis en avant</p>
            <h3 class="text-2xl font-bold text-dark">{{ stats.featured }}</h3>
          </div>
          <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
            <i class="fas fa-star text-xl"></i>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Products Table -->
    <div v-if="!showAddForm && !editingProduct" class="bg-white shadow-sm rounded-lg overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Image
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Nom
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Catégorie
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Prix
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Stock
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
          <tr v-for="product in filteredProducts" :key="product.id">
            <td class="px-6 py-4 whitespace-nowrap">
              <img 
                :src="product.images.find(img => img.is_main)?.image_path || 'https://via.placeholder.com/40'" 
                :alt="product.name" 
                class="h-10 w-10 rounded-full object-cover"
              >
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm font-medium text-gray-900">{{ product.name }}</div>
              <div class="text-sm text-gray-500">{{ product.sku }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">{{ product.category?.name }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">{{ formatPrice(product.price) }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">{{ product.stock_quantity }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span :class="[
                'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                product.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
              ]">
                {{ product.is_active ? 'Actif' : 'Inactif' }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
              <button @click="editProduct(product)" class="text-primary hover:text-primary-dark mr-3">
                <i class="fas fa-edit"></i>
              </button>
              <button @click="deleteProduct(product.id)" class="text-red-600 hover:text-red-900">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    
    <!-- Add/Edit Product Form -->
    <div v-if="showAddForm || editingProduct" class="bg-white shadow-sm rounded-lg p-6">
      <h2 class="text-lg font-medium text-gray-900 mb-6">
        {{ editingProduct ? 'Modifier le produit' : 'Ajouter un produit' }}
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
              <label for="category_id" class="block text-sm font-medium text-gray-700">Catégorie</label>
              <select 
                id="category_id" 
                v-model="form.category_id" 
                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
                required
              >
                <option value="">Sélectionner une catégorie</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">
                  {{ category.name }}
                </option>
              </select>
            </div>
          </div>
          
          <div>
            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
            <textarea 
              id="description" 
              v-model="form.description" 
              rows="3" 
              class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
              required
            ></textarea>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <label for="price" class="block text-sm font-medium text-gray-700">Prix</label>
              <div class="mt-1 relative rounded-md shadow-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <span class="text-gray-500 sm:text-sm">€</span>
                </div>
                <input 
                  type="number" 
                  id="price" 
                  v-model="form.price" 
                  step="0.01" 
                  min="0"
                  class="block w-full pl-7 pr-12 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
                  required
                >
              </div>
            </div>
            
            <div>
              <label for="stock_quantity" class="block text-sm font-medium text-gray-700">Quantité en stock</label>
              <input 
                type="number" 
                id="stock_quantity" 
                v-model="form.stock_quantity" 
                min="0"
                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
                required
              >
            </div>
            
            <div>
              <label for="sku" class="block text-sm font-medium text-gray-700">SKU</label>
              <input 
                type="text" 
                id="sku" 
                v-model="form.sku" 
                class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
                required
              >
            </div>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700">Images</label>
            <div class="mt-1 flex items-center space-x-4">
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
                  id="images" 
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
                  {{ form.images?.length ? 'Changer les images' : 'Choisir des images' }}
                </button>
                <p v-if="form.images?.length" class="mt-2 text-sm text-gray-500">
                  {{ form.images.length }} image(s) sélectionnée(s)
                </p>
              </div>
            </div>
          </div>
          
          <div class="flex items-center space-x-6">
            <div class="flex items-center">
              <input 
                type="checkbox" 
                id="is_featured" 
                v-model="form.is_featured" 
                class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded"
              >
              <label for="is_featured" class="ml-2 block text-sm text-gray-900">
                Mettre en avant
              </label>
            </div>
            
            <div class="flex items-center">
              <input 
                type="checkbox" 
                id="is_active" 
                v-model="form.is_active" 
                class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded"
              >
              <label for="is_active" class="ml-2 block text-sm text-gray-900">
                Produit actif
              </label>
            </div>
          </div>
        </div>
        
        <div class="mt-6 flex items-center space-x-3">
          <button 
            type="submit" 
            class="bg-primary text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-primary"
          >
            {{ editingProduct ? 'Mettre à jour' : 'Ajouter' }}
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
  products: {
    type: Array,
    required: true
  },
  categories: {
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
  category: '',
  status: ''
})

// Form display control
const showAddForm = ref(false)
const editingProduct = ref(null)

// Form data
const form = ref({
  category_id: '',
  name: '',
  description: '',
  price: '',
  stock_quantity: 0,
  sku: '',
  is_featured: false,
  is_active: true,
  images: []
})

const imagePreview = ref(null)
const imageInput = ref(null)

// Notifications
const showNotification = ref(false)
const notificationMessage = ref('')
const notificationType = ref('success')

// Delete modal
const showDeleteModal = ref(false)
const productToDelete = ref(null)

// Computed properties
const filteredProducts = computed(() => {
  let result = [...props.products]
  
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(product => 
      product.name.toLowerCase().includes(query) || 
      product.sku.toLowerCase().includes(query) ||
      product.category?.name.toLowerCase().includes(query)
    )
  }
  
  if (filters.value.category) {
    result = result.filter(product => product.category_id === parseInt(filters.value.category))
  }
  
  if (filters.value.status) {
    result = result.filter(product => 
      filters.value.status === 'active' ? product.is_active : !product.is_active
    )
  }
  
  return result
})

const stats = computed(() => {
  return {
    inStock: props.products.filter(p => p.stock_quantity > 0).length,
    outOfStock: props.products.filter(p => p.stock_quantity === 0).length,
    featured: props.products.filter(p => p.is_featured).length
  }
})

// Methods
const formatPrice = (price) => {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR'
  }).format(price)
}

const applyFilters = () => {
  showFilters.value = false
}

const editProduct = (product) => {
  editingProduct.value = product
  form.value = {
    category_id: product.category_id,
    name: product.name,
    description: product.description,
    price: product.price,
    stock_quantity: product.stock_quantity,
    sku: product.sku,
    is_featured: product.is_featured,
    is_active: product.is_active,
    images: []
  }
  imagePreview.value = product.images.find(img => img.is_main)?.image_path
}

const deleteProduct = (id) => {
  productToDelete.value = id
  showDeleteModal.value = true
}

const confirmDelete = () => {
  if (productToDelete.value) {
    router.delete(`/admin/products/${productToDelete.value}`, {
      onSuccess: () => {
        notificationMessage.value = 'Produit supprimé avec succès'
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
  productToDelete.value = null
}

const handleImageChange = (e) => {
  const files = Array.from(e.target.files)
  if (files.length > 0) {
    form.value.images = files
    imagePreview.value = URL.createObjectURL(files[0])
  }
}

const handleFormSubmit = () => {
  const formData = new FormData()
  
  // Ajouter les champs du formulaire
  Object.keys(form.value).forEach(key => {
    if (key === 'images') {
      form.value.images.forEach(image => {
        formData.append('images[]', image)
      })
    } else if (key === 'is_featured' || key === 'is_active') {
      // Convertir les valeurs booléennes en 0 ou 1
      formData.append(key, form.value[key] ? '1' : '0')
    } else {
      formData.append(key, form.value[key])
    }
  })

  if (editingProduct.value) {
    // Ajouter la méthode PUT pour Laravel
    formData.append('_method', 'PUT')
    
    router.post(`/admin/products/${editingProduct.value.id}`, formData, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        notificationMessage.value = 'Produit mis à jour avec succès'
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
  } else {
    router.post('/admin/products', formData, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        notificationMessage.value = 'Produit créé avec succès'
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
  
  cancelForm()
}

const cancelForm = () => {
  showAddForm.value = false
  editingProduct.value = null
  form.value = {
    category_id: '',
    name: '',
    description: '',
    price: '',
    stock_quantity: 0,
    sku: '',
    is_featured: false,
    is_active: true,
    images: []
  }
  imagePreview.value = null
}
</script> 