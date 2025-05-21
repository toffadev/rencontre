<template>
  <div class="bg-white shadow rounded-lg overflow-hidden">
    <div class="flex flex-col md:flex-row items-center justify-between p-4 border-b border-gray-200">
      <h2 class="text-lg font-semibold text-gray-900">Liste des produits</h2>
      <div class="mt-3 md:mt-0 flex items-center space-x-2">
        <span class="text-sm text-gray-500">Produits par page :</span>
        <select v-model="perPage" class="block w-20 pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md">
          <option value="10">10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
      </div>
    </div>
    
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              <div class="flex items-center">
                <span>Produit</span>
                <button @click="sortBy('name')" class="ml-1 text-gray-400 hover:text-gray-600">
                  <i class="fas fa-sort"></i>
                </button>
              </div>
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              <div class="flex items-center">
                <span>Catégorie</span>
                <button @click="sortBy('category')" class="ml-1 text-gray-400 hover:text-gray-600">
                  <i class="fas fa-sort"></i>
                </button>
              </div>
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              <div class="flex items-center">
                <span>Stock</span>
                <button @click="sortBy('stock')" class="ml-1 text-gray-400 hover:text-gray-600">
                  <i class="fas fa-sort"></i>
                </button>
              </div>
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              <div class="flex items-center">
                <span>Prix</span>
                <button @click="sortBy('price')" class="ml-1 text-gray-400 hover:text-gray-600">
                  <i class="fas fa-sort"></i>
                </button>
              </div>
            </th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              <div class="flex items-center">
                <span>Statut</span>
                <button @click="sortBy('status')" class="ml-1 text-gray-400 hover:text-gray-600">
                  <i class="fas fa-sort"></i>
                </button>
              </div>
            </th>
            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="product in products" :key="product.id">
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="flex items-center">
                <div class="flex-shrink-0 h-10 w-10">
                  <img class="h-10 w-10 rounded-md" :src="product.image" :alt="product.name">
                </div>
                <div class="ml-4">
                  <div class="text-sm font-medium text-gray-900">{{ product.name }}</div>
                  <div class="text-sm text-gray-500">{{ product.reference }}</div>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">{{ product.category }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">{{ product.stock }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <div class="text-sm text-gray-900">{{ product.price }}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" :class="getStatusClass(product.status)">
                {{ product.status }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
              <div class="flex justify-end space-x-3">
                <button @click="viewProduct(product.id)" class="text-blue-600 hover:text-blue-900">
                  <i class="fas fa-eye"></i>
                </button>
                <button @click="editProduct(product.id)" class="text-yellow-600 hover:text-yellow-900">
                  <i class="fas fa-edit"></i>
                </button>
                <button @click="showDeleteConfirm(product.id)" class="text-red-600 hover:text-red-900">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    
    <!-- Pagination -->
    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
      <div class="flex-1 flex justify-between sm:hidden">
        <button 
          @click="previousPage" 
          :disabled="currentPage <= 1"
          :class="currentPage <= 1 ? 'opacity-50 cursor-not-allowed' : ''"
          class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
          Précédent
        </button>
        <button 
          @click="nextPage" 
          :disabled="currentPage >= totalPages"
          :class="currentPage >= totalPages ? 'opacity-50 cursor-not-allowed' : ''"
          class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
          Suivant
        </button>
      </div>
      <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
        <div>
          <p class="text-sm text-gray-700">
            Affichage de
            <span class="font-medium">{{ startIndex }}</span>
            à
            <span class="font-medium">{{ endIndex }}</span>
            sur
            <span class="font-medium">{{ totalItems }}</span>
            produits
          </p>
        </div>
        <div>
          <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
            <button @click="previousPage" :disabled="currentPage <= 1" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
              <span class="sr-only">Précédent</span>
              <i class="fas fa-chevron-left"></i>
            </button>
            
            <template v-for="(page, index) in displayedPages" :key="index">
              <button 
                v-if="page === '...'" 
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                ...
              </button>
              <button 
                v-else 
                @click="goToPage(page)" 
                :class="page === currentPage ? 'z-10 bg-primary border-primary text-white' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'"
                class="relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                {{ page }}
              </button>
            </template>

            <button @click="nextPage" :disabled="currentPage >= totalPages" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
              <span class="sr-only">Suivant</span>
              <i class="fas fa-chevron-right"></i>
            </button>
          </nav>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const props = defineProps({
  initialProducts: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['view', 'edit', 'delete'])

const products = ref(props.initialProducts.length ? props.initialProducts : [
  {
    id: 1,
    name: 'Smartphone Premium',
    reference: 'REF: SM-2023-001',
    category: 'Électronique',
    stock: 48,
    price: '799 €',
    status: 'En stock',
    image: 'https://via.placeholder.com/80'
  },
  {
    id: 2,
    name: 'Casque sans fil',
    reference: 'REF: HD-2023-045',
    category: 'Audio',
    stock: 0,
    price: '149 €',
    status: 'Rupture',
    image: 'https://via.placeholder.com/80'
  },
  {
    id: 3,
    name: 'Montre connectée',
    reference: 'REF: WT-2023-112',
    category: 'Accessoire',
    stock: 15,
    price: '199 €',
    status: 'En stock',
    image: 'https://via.placeholder.com/80'
  },
  {
    id: 4,
    name: 'Sac à dos',
    reference: 'REF: BG-2023-078',
    category: 'Mode',
    stock: 32,
    price: '59 €',
    status: 'En stock',
    image: 'https://via.placeholder.com/80'
  },
  {
    id: 5,
    name: 'Enceinte Bluetooth',
    reference: 'REF: SP-2023-023',
    category: 'Audio',
    stock: 5,
    price: '129 €',
    status: 'Bientôt disponible',
    image: 'https://via.placeholder.com/80'
  }
])

// Sorting
const sortKey = ref('id')
const sortOrder = ref('asc')

const sortBy = (key) => {
  if (sortKey.value === key) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortOrder.value = 'asc'
  }
}

// Pagination
const currentPage = ref(1)
const perPage = ref(10)
const totalItems = computed(() => products.value.length)
const totalPages = computed(() => Math.ceil(totalItems.value / perPage.value))

const startIndex = computed(() => ((currentPage.value - 1) * perPage.value) + 1)
const endIndex = computed(() => {
  const end = startIndex.value + perPage.value - 1
  return end > totalItems.value ? totalItems.value : end
})

const displayedPages = computed(() => {
  const pages = []
  const maxVisiblePages = 5
  
  if (totalPages.value <= maxVisiblePages) {
    // Show all pages if there are fewer than maxVisiblePages
    for (let i = 1; i <= totalPages.value; i++) {
      pages.push(i)
    }
  } else {
    // Always show first page
    pages.push(1)
    
    // Calculate start and end of visible numbered pages
    let start = Math.max(2, currentPage.value - 1)
    let end = Math.min(totalPages.value - 1, start + maxVisiblePages - 3)
    
    // Adjust start if end is maxed out
    if (end === totalPages.value - 1) {
      start = Math.max(2, end - (maxVisiblePages - 3))
    }
    
    // Add ellipsis if start is after page 2
    if (start > 2) {
      pages.push('...')
    }
    
    // Add visible numbered pages
    for (let i = start; i <= end; i++) {
      pages.push(i)
    }
    
    // Add ellipsis if end is before the last page - 1
    if (end < totalPages.value - 1) {
      pages.push('...')
    }
    
    // Always show last page
    pages.push(totalPages.value)
  }
  
  return pages
})

const previousPage = () => {
  if (currentPage.value > 1) {
    currentPage.value--
  }
}

const nextPage = () => {
  if (currentPage.value < totalPages.value) {
    currentPage.value++
  }
}

const goToPage = (page) => {
  currentPage.value = page
}

// Status classes
const getStatusClass = (status) => {
  switch (status) {
    case 'En stock':
      return 'bg-green-100 text-green-800'
    case 'Rupture':
      return 'bg-red-100 text-red-800'
    case 'Bientôt disponible':
      return 'bg-yellow-100 text-yellow-800'
    default:
      return 'bg-gray-100 text-gray-800'
  }
}

// Action handlers
const viewProduct = (id) => {
  emit('view', id)
}

const editProduct = (id) => {
  emit('edit', id)
}

const showDeleteConfirm = (id) => {
  if (confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')) {
    emit('delete', id)
  }
}
</script> 