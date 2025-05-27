<template>
    <div class="bg-white rounded-lg shadow">
        <!-- Search Bar -->
        <div class="p-4 border-b border-gray-200">
            <div class="relative">
                <input 
                    type="text"
                    v-model="searchQuery"
                    placeholder="Rechercher un client..."
                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-pink-500 focus:border-pink-500"
                />
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </div>
            </div>
        </div>

        <!-- Clients List -->
        <div class="h-[calc(100vh-16rem)] overflow-y-auto">
            <div v-if="loading" class="p-4 text-center text-gray-500">
                <i class="fas fa-spinner fa-spin"></i>
                Chargement...
            </div>
            
            <template v-else>
                <!-- Selected Client Details -->
                <div v-if="selectedClient" class="p-4 bg-pink-50 border-b border-pink-100">
                    <div class="flex items-center space-x-3">
                        <img 
                            :src="selectedClient.avatar || 'https://via.placeholder.com/40'" 
                            :alt="selectedClient.name"
                            class="w-10 h-10 rounded-full object-cover"
                        />
                        <div class="flex-1">
                            <h3 class="font-medium text-gray-900">{{ selectedClient.name }}</h3>
                            <p class="text-sm text-gray-500">{{ selectedClient.email }}</p>
                        </div>
                        <button 
                            @click="clearSelection"
                            class="p-2 text-gray-400 hover:text-gray-600"
                        >
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- Client's Profiles -->
                    <div class="mt-4 space-y-2">
                        <h4 class="text-sm font-medium text-gray-500">Profils contactés</h4>
                        <div v-if="loadingProfiles" class="text-center text-sm text-gray-500">
                            <i class="fas fa-spinner fa-spin"></i>
                            Chargement des profils...
                        </div>
                        <div v-else-if="clientProfiles.length === 0" class="text-sm text-gray-500 text-center py-2">
                            Aucun profil contacté
                        </div>
                        <div 
                            v-else
                            v-for="profile in clientProfiles" 
                            :key="profile.id"
                            @click="selectProfile(profile)"
                            :class="[
                                'flex items-center space-x-3 p-2 rounded-lg cursor-pointer transition-colors',
                                selectedProfile?.id === profile.id 
                                    ? 'bg-pink-100' 
                                    : 'hover:bg-gray-50'
                            ]"
                        >
                            <img 
                                :src="profile.main_photo_path || 'https://via.placeholder.com/32'" 
                                :alt="profile.name"
                                class="w-8 h-8 rounded-full object-cover"
                            />
                            <div class="flex-1">
                                <div class="font-medium text-sm">{{ profile.name }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ profile.total_messages }} messages
                                </div>
                            </div>
                            <div v-if="profile.last_message" class="text-xs text-gray-400">
                                {{ formatDate(profile.last_message.created_at) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Clients List -->
                <div v-else class="divide-y divide-gray-200">
                    <div 
                        v-for="client in filteredClients" 
                        :key="client.id"
                        @click="selectClient(client)"
                        class="p-4 hover:bg-gray-50 cursor-pointer transition-colors"
                    >
                        <div class="flex items-center space-x-3">
                            <img 
                                :src="client.avatar || 'https://via.placeholder.com/40'" 
                                :alt="client.name"
                                class="w-10 h-10 rounded-full object-cover"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-medium text-gray-900 truncate">
                                        {{ client.name }}
                                    </h3>
                                    <span class="text-xs text-gray-500">
                                        {{ formatDate(client.last_activity) }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-500">{{ client.email }}</p>
                                <div class="flex items-center mt-1 space-x-4 text-xs text-gray-500">
                                    <span>
                                        <i class="fas fa-comment-alt mr-1"></i>
                                        {{ client.total_messages }} messages
                                    </span>
                                    <span>
                                        <i class="fas fa-coins mr-1"></i>
                                        {{ client.total_points_spent }} points
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    modelValue: {
        type: Object,
        default: null
    }
});

const emit = defineEmits(['update:modelValue']);

const clients = ref([]);
const loading = ref(true);
const searchQuery = ref('');
const selectedClient = ref(null);
const clientProfiles = ref([]);
const selectedProfile = ref(null);
const loadingProfiles = ref(false);

// Filtered clients based on search
const filteredClients = computed(() => {
    if (!searchQuery.value) return clients.value;
    
    const query = searchQuery.value.toLowerCase();
    return clients.value.filter(client => 
        client.name.toLowerCase().includes(query) ||
        client.email.toLowerCase().includes(query)
    );
});

// Load initial clients
const loadClients = async () => {
    try {
        loading.value = true;
        const response = await axios.get('/admin/conversations/clients');
        clients.value = response.data.data;
    } catch (error) {
        console.error('Error loading clients:', error);
    } finally {
        loading.value = false;
    }
};

// Load profiles for selected client
const loadClientProfiles = async (clientId) => {
    try {
        loadingProfiles.value = true;
        const response = await axios.get(`/admin/conversations/clients/${clientId}/profiles`);
        clientProfiles.value = response.data;
    } catch (error) {
        console.error('Error loading client profiles:', error);
    } finally {
        loadingProfiles.value = false;
    }
};

// Select a client
const selectClient = async (client) => {
    selectedClient.value = client;
    selectedProfile.value = null;
    await loadClientProfiles(client.id);
};

// Select a profile
const selectProfile = (profile) => {
    selectedProfile.value = profile;
    emit('update:modelValue', {
        client: selectedClient.value,
        profile: profile
    });
};

// Clear selection
const clearSelection = () => {
    selectedClient.value = null;
    selectedProfile.value = null;
    clientProfiles.value = [];
    emit('update:modelValue', null);
};

// Format date helper
const formatDate = (timestamp) => {
    if (!timestamp) return 'N/A';
    return new Date(timestamp).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// Load initial data
loadClients();

// Watch for external value changes
watch(() => props.modelValue, (newValue) => {
    if (!newValue) {
        clearSelection();
    }
}, { deep: true });
</script> 