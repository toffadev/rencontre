<template>
  <Modal :show="true" @close="$emit('close')">
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-gray-800">Détails du profil</h2>
        <button @click="$emit('close')" class="text-gray-500 hover:text-gray-700">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div v-if="profile" class="space-y-6">
        <!-- En-tête du profil -->
        <div class="flex items-center space-x-4">
          <img :src="profile.photo || 'https://via.placeholder.com/100'" :alt="profile.name" class="w-20 h-20 rounded-full object-cover">
          <div>
            <h3 class="text-lg font-medium text-gray-900">{{ profile.name }}</h3>
            <p class="text-sm text-gray-500">
              <span :class="[
                'px-2 py-1 rounded-full text-xs font-medium',
                profile.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
              ]">
                {{ profile.status === 'active' ? 'Actif' : 'Inactif' }}
              </span>
            </p>
          </div>
        </div>

        <!-- Statistiques -->
        <div class="grid grid-cols-2 gap-4">
          <div class="bg-gray-50 p-4 rounded-lg">
            <p class="text-sm text-gray-500">Messages reçus</p>
            <p class="text-lg font-semibold">{{ profile.stats.messages_received }}</p>
          </div>
          <div class="bg-gray-50 p-4 rounded-lg">
            <p class="text-sm text-gray-500">Messages envoyés</p>
            <p class="text-lg font-semibold">{{ profile.stats.messages_sent }}</p>
          </div>
          <div class="bg-gray-50 p-4 rounded-lg">
            <p class="text-sm text-gray-500">Temps de réponse moyen</p>
            <p class="text-lg font-semibold">{{ profile.stats.average_response_time }} min</p>
          </div>
          <div class="bg-gray-50 p-4 rounded-lg">
            <p class="text-sm text-gray-500">Taux de rétention</p>
            <p class="text-lg font-semibold">{{ profile.stats.retention_rate }}%</p>
          </div>
        </div>

        <!-- Modérateurs assignés -->
        <div>
          <h4 class="text-sm font-medium text-gray-700 mb-2">Modérateurs assignés</h4>
          <div class="space-y-2">
            <div v-for="moderator in profile.moderators" :key="moderator.id" 
                 class="flex items-center justify-between bg-white p-2 rounded border">
              <div class="flex items-center space-x-2">
                <span class="w-2 h-2 rounded-full" 
                      :class="moderator.is_primary ? 'bg-green-500' : 'bg-gray-300'"></span>
                <span class="text-sm">{{ moderator.name }}</span>
              </div>
              <span class="text-xs text-gray-500">{{ moderator.is_primary ? 'Principal' : 'Secondaire' }}</span>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-end space-x-3 pt-4 border-t">
          <button @click="$emit('close')" 
                  class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
            Fermer
          </button>
        </div>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import Modal from '@/Admin/Components/Modal.vue'

defineProps({
  profile: {
    type: Object,
    required: true
  }
})

defineEmits(['close'])
</script> 