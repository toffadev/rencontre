<template>
  <div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <Sidebar :class="{ 'hidden': !sidebarOpen }" />
    
    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Header -->
      <Header 
        @toggle-sidebar="toggleSidebar" 
        :title="title"
        :user="$page.props.auth.user"
      />
      
      <!-- Main -->
      <main class="flex-1 overflow-y-auto p-6 bg-gray-100">
        <slot></slot>
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import Sidebar from '../Components/Sidebar.vue'
import Header from '../Components/Header.vue'

const page = usePage()
const sidebarOpen = ref(true)

// Calculer le titre en fonction de la route actuelle
const title = computed(() => {
  const currentRoute = page.props.ziggy?.current_route
  
  // Vous pouvez personnaliser les titres en fonction des routes
  const titles = {
    'admin.dashboard': 'Tableau de bord',
    'admin.profiles.index': 'Gestion des profils',
    'admin.users.index': 'Gestion des utilisateurs',
    'admin.reports.index': 'Signalements',
    // Ajoutez d'autres routes selon vos besoins
  }

  return titles[currentRoute] || 'Administration'
})

const toggleSidebar = () => {
  sidebarOpen.value = !sidebarOpen.value
}
</script> 