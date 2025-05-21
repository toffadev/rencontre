<template>
  <div class="bg-white shadow rounded-lg overflow-hidden">
    <div class="p-6 border-b border-gray-200">
      <h2 class="text-lg font-semibold text-gray-900">{{ isEditing ? 'Modifier le produit' : 'Ajouter un produit' }}</h2>
    </div>
    
    <form @submit.prevent="submitForm" class="p-6 space-y-6">
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <!-- Nom du produit -->
        <div>
          <label for="name" class="block text-sm font-medium text-gray-700">Nom du produit</label>
          <input 
            type="text" 
            id="name" 
            v-model="form.name" 
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
            required
          >
        </div>
        
        <!-- Référence -->
        <div>
          <label for="reference" class="block text-sm font-medium text-gray-700">Référence</label>
          <input 
            type="text" 
            id="reference" 
            v-model="form.reference" 
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
          >
        </div>
        
        <!-- Catégorie -->
        <div>
          <label for="category" class="block text-sm font-medium text-gray-700">Catégorie</label>
          <select 
            id="category" 
            v-model="form.category" 
            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md"
            required
          >
            <option value="">Sélectionner une catégorie</option>
            <option v-for="category in categories" :key="category" :value="category">{{ category }}</option>
          </select>
        </div>
        
        <!-- Prix -->
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
              min="0" 
              step="0.01" 
              class="pl-7 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
              required
            >
          </div>
        </div>
        
        <!-- Stock -->
        <div>
          <label for="stock" class="block text-sm font-medium text-gray-700">Stock</label>
          <input 
            type="number" 
            id="stock" 
            v-model="form.stock" 
            min="0" 
            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
            required
          >
        </div>
        
        <!-- Statut -->
        <div>
          <label for="status" class="block text-sm font-medium text-gray-700">Statut</label>
          <select 
            id="status" 
            v-model="form.status" 
            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md"
            required
          >
            <option value="">Sélectionner un statut</option>
            <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
          </select>
        </div>
      </div>
      
      <!-- Description -->
      <div>
        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
        <textarea 
          id="description" 
          v-model="form.description" 
          rows="3" 
          class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
        ></textarea>
      </div>
      
      <!-- Image -->
      <div>
        <label class="block text-sm font-medium text-gray-700">Image du produit</label>
        <div class="mt-1 flex items-center">
          <div v-if="form.image || previewImage" class="flex-shrink-0 h-24 w-24 rounded-md overflow-hidden bg-gray-200 mr-4">
            <img :src="previewImage || form.image" alt="Preview" class="h-full w-full object-cover">
          </div>
          <div class="flex-1">
            <input 
              type="file" 
              id="image" 
              ref="imageInput"
              @change="handleImageChange" 
              accept="image/*"
              class="sr-only"
            >
            <label 
              for="image" 
              class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none cursor-pointer"
            >
              {{ form.image ? 'Changer l\'image' : 'Ajouter une image' }}
            </label>
            <p class="mt-1 text-sm text-gray-500">PNG, JPG, GIF jusqu'à 2MB</p>
          </div>
        </div>
      </div>
      
      <!-- Buttons -->
      <div class="flex justify-end space-x-3">
        <button 
          type="button" 
          @click="$emit('cancel')" 
          class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
        >
          Annuler
        </button>
        <button 
          type="submit" 
          :disabled="isSubmitting"
          class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
        >
          {{ isSubmitting ? 'Traitement...' : (isEditing ? 'Mettre à jour' : 'Ajouter') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'

const props = defineProps({
  product: {
    type: Object,
    default: null
  },
  isEditing: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['submit', 'cancel'])

// Form data
const form = reactive({
  name: '',
  reference: '',
  category: '',
  price: '',
  stock: 0,
  status: '',
  description: '',
  image: ''
})

// Options for selects
const categories = ref(['Électronique', 'Audio', 'Accessoire', 'Mode', 'Maison', 'Beauté'])
const statuses = ref(['En stock', 'Rupture', 'Bientôt disponible'])

// Image preview
const imageInput = ref(null)
const previewImage = ref(null)

// Form state
const isSubmitting = ref(false)

// Initialize form with product data if editing
onMounted(() => {
  if (props.product) {
    Object.keys(form).forEach(key => {
      if (props.product[key] !== undefined) {
        form[key] = props.product[key]
      }
    })
  }
})

// Watch for changes in the product prop
watch(() => props.product, (newProduct) => {
  if (newProduct) {
    Object.keys(form).forEach(key => {
      if (newProduct[key] !== undefined) {
        form[key] = newProduct[key]
      }
    })
  }
}, { deep: true })

// Handle image change
const handleImageChange = (event) => {
  const file = event.target.files[0]
  if (file) {
    form.image = file
    previewImage.value = URL.createObjectURL(file)
  }
}

// Submit form
const submitForm = async () => {
  isSubmitting.value = true
  
  try {
    // In a real app, you would likely use FormData for file upload
    // const formData = new FormData()
    // Object.entries(form).forEach(([key, value]) => {
    //   formData.append(key, value)
    // })
    
    // For now, just emit the form data
    emit('submit', { ...form })
    
    // Reset form if adding a new product
    if (!props.isEditing) {
      Object.keys(form).forEach(key => {
        form[key] = key === 'stock' ? 0 : ''
      })
      previewImage.value = null
      if (imageInput.value) {
        imageInput.value.value = ''
      }
    }
  } catch (error) {
    console.error('Error submitting form:', error)
  } finally {
    isSubmitting.value = false
  }
}
</script> 