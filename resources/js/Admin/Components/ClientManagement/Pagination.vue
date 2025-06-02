<template>
  <div v-if="links.length > 3" class="flex flex-wrap justify-center gap-1 px-4 py-3 sm:px-6">
    <template v-for="(link, key) in links" :key="key">
      <div
        v-if="link.url === null"
        class="relative inline-flex cursor-default items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-400"
        :class="{ 'mr-4': link.label === 'Previous', 'ml-4': link.label === 'Next' }"
      >
        {{ link.label }}
      </div>
      <Link
        v-else
        :href="link.url"
        class="relative inline-flex items-center rounded-md px-4 py-2 text-sm font-semibold"
        :class="{
          'mr-4': link.label === 'Previous',
          'ml-4': link.label === 'Next',
          'bg-indigo-600 text-white hover:bg-indigo-500': link.active,
          'bg-white text-gray-700 hover:bg-gray-50': !link.active
        }"
        preserve-scroll
      >
        {{ link.label }}
      </Link>
    </template>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  links: {
    type: Array,
    required: true
  }
})

// Formater les liens pour n'afficher que les numéros de page
const formattedLinks = computed(() => {
  return props.links.map(link => {
    if (link.label === '&laquo; Previous') {
      return { ...link, label: 'Previous' }
    }
    if (link.label === 'Next &raquo;') {
      return { ...link, label: 'Next' }
    }
    return link
  })
})

// Calculer la page courante
const currentPage = computed(() => {
  const activePage = props.links.find(link => link.active)
  return activePage ? parseInt(activePage.label) : 1
})

// Calculer le nombre total de pages
const totalPages = computed(() => {
  const numericLinks = props.links.filter(link => !isNaN(link.label))
  return numericLinks.length > 0 ? Math.max(...numericLinks.map(link => parseInt(link.label))) : 1
})
</script> 