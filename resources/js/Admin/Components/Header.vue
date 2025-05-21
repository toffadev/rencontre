<template>
  <header class="bg-white border-b border-gray-200">
    <div class="flex items-center justify-between p-4">
      <div class="flex items-center space-x-4">
        <button @click="$emit('toggle-sidebar')" class="text-gray-500 md:hidden">
          <i class="fas fa-bars"></i>
        </button>
        <h1 class="text-xl font-bold text-dark">{{ title }}</h1>
      </div>
      
      <div class="flex items-center space-x-4">
        <div class="relative">
          <button class="p-2 rounded-full hover:bg-gray-100">
            <i class="fas fa-bell text-gray-600"></i>
            <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full"></span>
          </button>
        </div>
        <div class="relative">
          <button class="p-2 rounded-full hover:bg-gray-100">
            <i class="fas fa-envelope text-gray-600"></i>
            <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full"></span>
          </button>
        </div>
        <div class="relative md:hidden">
          <button class="flex items-center space-x-2">
            <img src="https://randomuser.me/api/portraits/women/44.jpg" alt="Profile" class="w-8 h-8 rounded-full">
            <i class="fas fa-chevron-down text-gray-500 text-xs"></i>
          </button>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const title = computed(() => {
  switch (page.component) {
    case 'Admin/Pages/Dashboard':
      return 'Tableau de bord'
    default:
      return 'Administration'
  }
})
</script> 