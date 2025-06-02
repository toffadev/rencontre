<template>
    <div class="bg-white p-4 rounded-lg shadow-sm mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Recherche -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Recherche</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-search text-gray-400"></i>
                    </div>
                    <input type="text"
                           v-model="localFilters.search"
                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
                           placeholder="Rechercher un profil...">
                </div>
            </div>

            <!-- Filtre par modérateur -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Modérateur</label>
                <select v-model="localFilters.moderator_id"
                        class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md">
                    <option value="">Tous les modérateurs</option>
                    <option v-for="moderator in moderators" 
                            :key="moderator.id" 
                            :value="moderator.id">
                        {{ moderator.name }}
                    </option>
                </select>
            </div>

            <!-- Filtre par période -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Période</label>
                <select v-model="localFilters.period"
                        class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md">
                    <option value="all">Toutes les périodes</option>
                    <option value="today">Aujourd'hui</option>
                    <option value="week">Cette semaine</option>
                    <option value="month">Ce mois</option>
                    <option value="quarter">Ce trimestre</option>
                </select>
            </div>

            <!-- Filtre par statut -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                <select v-model="localFilters.status"
                        class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md">
                    <option value="all">Tous les statuts</option>
                    <option value="active">Actif</option>
                    <option value="inactive">Inactif</option>
                </select>
            </div>
        </div>

        <!-- Actions -->
        <div class="mt-4 flex justify-end space-x-3">
            <button @click="resetFilters"
                    class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                Réinitialiser
            </button>
            <button @click="applyFilters"
                    class="px-4 py-2 bg-primary text-white rounded-md text-sm font-medium hover:bg-opacity-90">
                Appliquer
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    modelValue: {
        type: Object,
        required: true
    },
    moderators: {
        type: Array,
        default: () => []
    }
})

const emit = defineEmits(['update:modelValue', 'filter'])

const localFilters = ref({ ...props.modelValue })

watch(() => props.modelValue, (newValue) => {
    localFilters.value = { ...newValue }
}, { deep: true })

const applyFilters = () => {
    emit('update:modelValue', { ...localFilters.value })
    emit('filter')
}

const resetFilters = () => {
    localFilters.value = {
        search: '',
        moderator_id: '',
        period: 'all',
        status: 'all'
    }
    applyFilters()
}
</script>