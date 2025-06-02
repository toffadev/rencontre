<template>
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th 
                            v-for="column in columns" 
                            :key="column.key"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                            @click="sortBy(column.key)"
                        >
                            <div class="flex items-center space-x-1">
                                <span>{{ column.label }}</span>
                                <span v-if="sortField === column.key" class="ml-1">
                                    {{ sortDirection === 'asc' ? '↑' : '↓' }}
                                </span>
                            </div>
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-if="loading" class="animate-pulse">
                        <td :colspan="columns.length + 1" class="px-6 py-4">
                            <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                        </td>
                    </tr>
                    <tr v-else-if="!messages.data.length" class="text-center">
                        <td :colspan="columns.length + 1" class="px-6 py-4 text-gray-500">
                            Aucun message trouvé
                        </td>
                    </tr>
                    <tr v-else v-for="message in messages.data" :key="message.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ formatDate(message.created_at) }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            <div class="flex items-center">
                                <img 
                                    :src="message.profile.photo" 
                                    :alt="message.profile.name"
                                    class="h-8 w-8 rounded-full mr-2"
                                >
                                {{ message.profile.name }}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            {{ message.client.name }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            <div class="max-w-xs truncate">
                                {{ message.content }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ message.points_earned }} points
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button 
                                @click="$emit('view-details', message)"
                                class="text-primary hover:text-primary-dark mr-3"
                            >
                                Détails
                            </button>
                            <button 
                                @click="$emit('view-conversation', { 
                                    client_id: message.client.id,
                                    profile_id: message.profile.id 
                                })"
                                class="text-primary hover:text-primary-dark"
                            >
                                Conversation
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    messages: {
        type: Object,
        required: true
    },
    loading: {
        type: Boolean,
        default: false
    }
});

defineEmits(['view-details', 'view-conversation', 'sort']);

const columns = [
    { key: 'created_at', label: 'Date' },
    { key: 'profile_id', label: 'Profil' },
    { key: 'client_id', label: 'Client' },
    { key: 'content', label: 'Message' },
    { key: 'points_earned', label: 'Points' }
];

const sortField = ref('created_at');
const sortDirection = ref('desc');

const sortBy = (field) => {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = 'asc';
    }
    
    emit('sort', {
        field: sortField.value,
        direction: sortDirection.value
    });
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script> 