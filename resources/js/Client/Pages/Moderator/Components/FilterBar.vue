<template>
    <div class="bg-white rounded-xl shadow-md p-6">
        <h3 class="text-lg font-medium mb-4">Filtres</h3>

        <!-- Période -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Période
            </label>
            <select
                v-model="filters.dateRange"
                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-pink-500 focus:ring-pink-500"
                @change="emitFilters"
            >
                <option value="day">Aujourd'hui</option>
                <option value="week">Cette semaine</option>
                <option value="month">Ce mois</option>
                <option value="year">Cette année</option>
            </select>
        </div>

        <!-- Profil -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Profil
            </label>
            <select
                v-model="filters.profileId"
                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-pink-500 focus:ring-pink-500"
                @change="emitFilters"
            >
                <option value="">Tous les profils</option>
                <option
                    v-for="profile in profiles"
                    :key="profile.id"
                    :value="profile.id"
                >
                    {{ profile.name }}
                </option>
            </select>
        </div>

        <!-- Type de message (pour l'historique) -->
        <div class="mb-4" v-if="showMessageTypeFilter">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Type de message
            </label>
            <select
                v-model="filters.messageType"
                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-pink-500 focus:ring-pink-500"
                @change="emitFilters"
            >
                <option value="all">Tous les messages</option>
                <option value="short">Messages courts</option>
                <option value="long">Messages longs</option>
            </select>
        </div>

        <!-- Bouton de réinitialisation -->
        <button
            @click="resetFilters"
            class="w-full bg-gray-100 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-200 transition flex items-center justify-center"
        >
            <i class="fas fa-undo mr-2"></i>
            Réinitialiser les filtres
        </button>
    </div>
</template>

<script setup>
import { ref, watch } from "vue";

const props = defineProps({
    dateRange: {
        type: String,
        default: "week",
    },
    profiles: {
        type: Array,
        default: () => [],
    },
    showMessageTypeFilter: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["filter-changed"]);

const filters = ref({
    dateRange: props.dateRange,
    profileId: "",
    messageType: "all",
});

function emitFilters() {
    emit("filter-changed", { ...filters.value });
}

function resetFilters() {
    filters.value = {
        dateRange: "week",
        profileId: "",
        messageType: "all",
    };
    emitFilters();
}

// Surveiller les changements de props
watch(
    () => props.dateRange,
    (newValue) => {
        filters.value.dateRange = newValue;
    }
);
</script>
