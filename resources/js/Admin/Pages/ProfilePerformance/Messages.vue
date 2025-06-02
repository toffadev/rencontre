<template>
  <AdminLayout>
    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
      <div class="p-6 border-b border-gray-200">
        <div class="flex items-center justify-between">
          <h2 class="text-xl font-semibold text-gray-800">
            Messages de {{ profile.name }}
          </h2>
          <button 
            @click="router.get('/admin/profile-performance')"
            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
          >
            <i class="fas fa-arrow-left mr-2"></i>
            Retour
          </button>
        </div>
      </div>

      <div class="divide-y divide-gray-200">
        <div v-for="message in messages.data" :key="message.id" 
          :class="['p-4', message.is_from_client ? 'bg-gray-50' : 'bg-white']">
          <div class="flex items-start space-x-3">
            <div class="flex-shrink-0">
              <img 
                :src="message.author_avatar || 'https://via.placeholder.com/40'" 
                :alt="message.author_name"
                class="h-10 w-10 rounded-full"
              >
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900">
                {{ message.author_name }}
                <span v-if="message.moderator_name" class="text-xs text-gray-500">
                  (Modérateur)
                </span>
              </p>
              <p class="text-sm text-gray-500">
                {{ new Date(message.created_at).toLocaleString() }}
              </p>
              <p class="mt-1 text-sm text-gray-900">
                {{ message.content }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div class="px-4 py-3 border-t border-gray-200 sm:px-6">
        <Pagination :links="messages.links" />
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import Pagination from '@/Admin/Components/ProfilePeformance/Pagination.vue'

defineProps({
  messages: {
    type: Object,
    required: true
  },
  profile: {
    type: Object,
    required: true
  }
})
</script> 