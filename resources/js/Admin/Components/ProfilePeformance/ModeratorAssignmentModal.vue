<template>
  <Modal :show="true" @close="$emit('close')">
    <div class="p-6">
      <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-semibold text-gray-800">Assigner des modérateurs</h2>
        <button @click="$emit('close')" class="text-gray-500 hover:text-gray-700">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div v-if="profile" class="space-y-6">
        <!-- Informations du profil -->
        <div class="flex items-center space-x-4 mb-6">
          <img :src="profile.photo || 'https://via.placeholder.com/64'" :alt="profile.name" 
               class="w-16 h-16 rounded-full object-cover">
          <div>
            <h3 class="text-lg font-medium text-gray-900">{{ profile.name }}</h3>
            <p class="text-sm text-gray-500">
              {{ currentModerators?.length || 0 }} modérateur(s) assigné(s)
            </p>
          </div>
        </div>

        <!-- Liste des modérateurs actuels -->
        <div v-if="currentModerators?.length" class="mb-6">
          <h4 class="text-sm font-medium text-gray-700 mb-2">Modérateurs actuels</h4>
          <div class="space-y-2">
            <div v-for="moderator in currentModerators" :key="moderator.id"
                 class="flex items-center justify-between bg-white p-2 rounded border">
              <div class="flex items-center space-x-2">
                <span class="w-2 h-2 rounded-full"
                      :class="moderator.is_primary ? 'bg-green-500' : 'bg-gray-300'"></span>
                <span class="text-sm">{{ moderator.name }}</span>
              </div>
              <div class="flex items-center space-x-2">
                <button v-if="!moderator.is_primary"
                        @click="setPrimaryModerator(moderator.id)"
                        class="text-xs text-blue-600 hover:text-blue-800">
                  Définir comme principal
                </button>
                <button @click="removeModerator(moderator.id)"
                        class="text-xs text-red-600 hover:text-red-800">
                  Retirer
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Formulaire d'ajout -->
        <div>
          <h4 class="text-sm font-medium text-gray-700 mb-2">Ajouter un modérateur</h4>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Sélectionner un modérateur</label>
              <select v-model="selectedModeratorId"
                      class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md">
                <option value="">Choisir un modérateur</option>
                <option v-for="moderator in availableModerators" 
                        :key="moderator.id" 
                        :value="moderator.id">
                  {{ moderator.name }}
                </option>
              </select>
            </div>

            <div class="flex items-center">
              <input type="checkbox" 
                     v-model="isPrimary"
                     id="is_primary" 
                     class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
              <label for="is_primary" class="ml-2 block text-sm text-gray-900">
                Définir comme modérateur principal
              </label>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-end space-x-3 pt-4 border-t">
          <button @click="$emit('close')"
                  class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
            Annuler
          </button>
          <button @click="addModerator"
                  :disabled="!selectedModeratorId"
                  class="px-4 py-2 bg-primary text-white rounded-md text-sm font-medium hover:bg-opacity-90 disabled:opacity-50 disabled:cursor-not-allowed">
            Ajouter
          </button>
        </div>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { ref, computed } from 'vue'
import Modal from '@/Admin/Components/Modal.vue'

const props = defineProps({
  profile: {
    type: Object,
    required: true
  },
  currentModerators: {
    type: Array,
    default: () => []
  },
  availableModerators: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close', 'assign'])

const selectedModeratorId = ref('')
const isPrimary = ref(false)

const addModerator = () => {
  if (!selectedModeratorId.value) return

  emit('assign', {
    moderator_id: selectedModeratorId.value,
    is_primary: isPrimary.value
  })

  // Reset form
  selectedModeratorId.value = ''
  isPrimary.value = false
}

const setPrimaryModerator = (moderatorId) => {
  emit('assign', {
    moderator_id: moderatorId,
    is_primary: true
  })
}

const removeModerator = (moderatorId) => {
  emit('assign', {
    moderator_id: moderatorId,
    remove: true
  })
}
</script> 