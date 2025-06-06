<template>
  <header class="bg-white shadow">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center py-6">
        <!-- Left side -->
        <div class="flex items-center">
          <button 
            @click="$emit('toggle-sidebar')"
            class="text-gray-500 hover:text-gray-900 focus:outline-none focus:text-gray-900 mr-4"
          >
            <i class="fas fa-bars text-xl"></i>
          </button>
          <h1 class="text-2xl font-semibold text-gray-900">
            {{ title }}
          </h1>
        </div>

        <!-- Right side -->
        <div class="flex items-center space-x-4">
          <!-- Notifications -->
          <NotificationDropdown />

          <!-- User Menu -->
        <div class="relative">
            <button 
              @click="showUserMenu = !showUserMenu"
              class="flex items-center space-x-3 focus:outline-none"
            >
              <img 
                :src="`https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&color=7F9CF5&background=EBF4FF`"
                :alt="user.name"
                class="h-8 w-8 rounded-full object-cover"
              >
              <span class="text-sm font-medium text-gray-700">{{ user.name }}</span>
              <i class="fas fa-chevron-down text-gray-400"></i>
            </button>

            <!-- Dropdown Menu -->
            <div 
              v-if="showUserMenu"
              class="absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5"
            >
              <button
                @click="logout"
                class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
              >
                Déconnexion
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import NotificationDropdown from './NotificationDropdown.vue';
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({
  title: {
    type: String,
    required: true
  },
  user: {
    type: Object,
    required: true
  }
});

defineEmits(['toggle-sidebar']);

const showUserMenu = ref(false);

const logout = () => {
  router.post(route('logout'));
};

// Fermer le menu utilisateur quand on clique en dehors
const handleClickOutside = (event) => {
  if (showUserMenu.value && !event.target.closest('.relative')) {
    showUserMenu.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script> 