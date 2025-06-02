<template>
  <div v-if="links.length > 3" class="flex justify-center">
    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
      <!-- Bouton précédent -->
      <a
        v-if="!links[0].active"
        :href="links[0].url"
        class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
        @click.prevent="$emit('navigate', links[0].url)"
      >
        <span class="sr-only">Précédent</span>
        <i class="fas fa-chevron-left"></i>
      </a>

      <!-- Pages -->
      <template v-for="(link, index) in links.slice(1, -1)" :key="index">
        <span
          v-if="link.active"
          class="relative inline-flex items-center px-4 py-2 border border-pink-500 bg-pink-50 text-sm font-medium text-pink-600"
        >
          {{ link.label }}
        </span>
        <a
          v-else
          :href="link.url"
          class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50"
          @click.prevent="$emit('navigate', link.url)"
        >
          {{ link.label }}
        </a>
      </template>

      <!-- Bouton suivant -->
      <a
        v-if="!links[links.length - 1].active"
        :href="links[links.length - 1].url"
        class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
        @click.prevent="$emit('navigate', links[links.length - 1].url)"
      >
        <span class="sr-only">Suivant</span>
        <i class="fas fa-chevron-right"></i>
      </a>
    </nav>
  </div>
</template>

<script setup>
defineProps({
  links: {
    type: Array,
    required: true
  }
})

defineEmits(['navigate'])
</script> 