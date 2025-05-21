<template>
  <div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="p-6 border-b border-gray-200">
      <h2 class="text-lg font-semibold text-dark">Produits populaires</h2>
    </div>
    <div class="divide-y divide-gray-200">
      <div v-for="product in topProducts" :key="product.id" class="p-4 flex items-center">
        <div class="flex-shrink-0 h-16 w-16 rounded-md overflow-hidden bg-gray-200">
          <img :src="product.image" :alt="product.name" class="h-full w-full object-cover">
        </div>
        <div class="ml-4 flex-1">
          <h3 class="text-sm font-medium text-dark">{{ product.name }}</h3>
          <p class="text-sm text-gray-500">{{ product.category }}</p>
          <div class="mt-1 flex items-center">
            <div class="flex items-center">
              <template v-for="n in 5" :key="n">
                <i v-if="n <= Math.floor(product.rating)" class="fas fa-star text-yellow-400"></i>
                <i v-else-if="n <= Math.floor(product.rating) + 0.5" class="fas fa-star-half-alt text-yellow-400"></i>
                <i v-else class="far fa-star text-yellow-400"></i>
              </template>
              <span class="text-xs text-gray-500 ml-1">({{ product.reviewCount }})</span>
            </div>
          </div>
        </div>
        <div class="ml-4">
          <span class="text-sm font-medium text-dark">{{ product.price }}</span>
          <p :class="`text-xs ${product.trend > 0 ? 'text-green-500' : 'text-red-500'}`">
            {{ product.trend > 0 ? '+' : '' }}{{ product.trend }}% ce mois
          </p>
        </div>
      </div>
    </div>
    <div class="p-4 border-t border-gray-200 text-center">
      <a href="#" class="text-primary font-medium hover:underline">Voir tous les produits</a>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const topProducts = ref([
  {
    id: 1,
    name: 'Smartphone Premium',
    category: 'Électronique',
    rating: 4.5,
    reviewCount: 128,
    price: '799 €',
    trend: 15,
    image: 'https://via.placeholder.com/80'
  },
  {
    id: 2,
    name: 'Casque sans fil',
    category: 'Audio',
    rating: 4,
    reviewCount: 89,
    price: '149 €',
    trend: 28,
    image: 'https://via.placeholder.com/80'
  },
  {
    id: 3,
    name: 'Montre connectée',
    category: 'Accessoire',
    rating: 5,
    reviewCount: 256,
    price: '199 €',
    trend: -5,
    image: 'https://via.placeholder.com/80'
  },
  {
    id: 4,
    name: 'Sac à dos',
    category: 'Mode',
    rating: 4,
    reviewCount: 42,
    price: '59 €',
    trend: 12,
    image: 'https://via.placeholder.com/80'
  }
])
</script> 