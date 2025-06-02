<template>
    <div class="bg-white p-4 rounded-lg shadow-sm mb-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Filtre par date -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Période</label>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Du</label>
                        <input
                            type="date"
                            v-model="filters.start_date"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            :max="filters.end_date"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Au</label>
                        <input
                            type="date"
                            v-model="filters.end_date"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            :min="filters.start_date"
                        />
                    </div>
                </div>
            </div>

            <!-- Filtre par profil -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Profil</label>
                <select
                    v-model="filters.profile_id"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                >
                    <option value="">Tous les profils</option>
                    <option v-for="profile in availableFilters.profiles" :key="profile.id" :value="profile.id">
                        {{ profile.name }}
                    </option>
                </select>
            </div>

            <!-- Filtre par client -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Client</label>
                <select
                    v-model="filters.client_id"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                >
                    <option value="">Tous les clients</option>
                    <option v-for="client in availableFilters.clients" :key="client.id" :value="client.id">
                        {{ client.name }}
                    </option>
                </select>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Filtre par longueur -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Longueur</label>
                <select
                    v-model="filters.length"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                >
                    <option value="">Toutes les longueurs</option>
                    <option value="short">Messages courts (&lt; 10 caractères)</option>
                    <option value="long">Messages longs (≥ 10 caractères)</option>
                </select>
            </div>

            <!-- Boutons de filtrage -->
            <div class="md:col-span-3 flex justify-end space-x-2 items-end">
                <button
                    @click="resetFilters"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    Réinitialiser
                </button>
                <button
                    @click="applyFilters"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    Appliquer
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    availableFilters: {
        type: Object,
        required: true
    }
});

const emit = defineEmits(['filter']);

const filters = ref({
    start_date: '',
    end_date: '',
    profile_id: '',
    client_id: '',
    length: ''
});

const resetFilters = () => {
    filters.value = {
        start_date: '',
        end_date: '',
        profile_id: '',
        client_id: '',
        length: ''
    };
    applyFilters();
};

const applyFilters = () => {
    emit('filter', { ...filters.value });
};

// Appliquer les filtres automatiquement quand ils changent
watch(filters.value, () => {
    applyFilters();
}, { deep: true });
</script> 