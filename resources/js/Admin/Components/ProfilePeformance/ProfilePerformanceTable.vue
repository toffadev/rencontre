<template>
    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Profil
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Modérateurs
                        </th>
                        <th scope="col" 
                            @click="updateSort('messages_received')"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">
                            Messages reçus
                            <SortIcon :active="sort.field === 'messages_received'" :direction="sort.direction" />
                        </th>
                        <th scope="col"
                            @click="updateSort('messages_sent')"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">
                            Messages envoyés
                            <SortIcon :active="sort.field === 'messages_sent'" :direction="sort.direction" />
                        </th>
                        <th scope="col"
                            @click="updateSort('average_response_time')"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">
                            Temps de réponse
                            <SortIcon :active="sort.field === 'average_response_time'" :direction="sort.direction" />
                        </th>
                        <th scope="col"
                            @click="updateSort('retention_rate')"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">
                            Taux de rétention
                            <SortIcon :active="sort.field === 'retention_rate'" :direction="sort.direction" />
                        </th>
                        <th scope="col" class="relative px-6 py-3">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-if="loading" class="animate-pulse">
                        <td colspan="7" class="px-6 py-4">
                            <div class="flex items-center space-x-4">
                                <div class="h-10 w-10 bg-gray-200 rounded-full"></div>
                                <div class="flex-1 space-y-2">
                                    <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                                    <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    
                    <tr v-else v-for="profile in profiles" :key="profile.id" class="hover:bg-gray-50">
                        <!-- Profil -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <img :src="profile.photo || 'https://via.placeholder.com/40'" 
                                         :alt="profile.name"
                                         class="h-10 w-10 rounded-full object-cover">
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">{{ profile.name }}</div>
                                    <div class="text-sm text-gray-500">
                                        <span :class="[
                                            'px-2 py-1 text-xs rounded-full',
                                            profile.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                        ]">
                                            {{ profile.status === 'active' ? 'Actif' : 'Inactif' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Modérateurs -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex flex-col space-y-1">
                                <div v-for="moderator in profile.moderators" :key="moderator.id" class="flex items-center">
                                    <span class="w-2 h-2 rounded-full mr-2"
                                          :class="moderator.is_primary ? 'bg-green-500' : 'bg-gray-300'"></span>
                                    <span class="text-sm text-gray-900">{{ moderator.name }}</span>
                                </div>
                            </div>
                        </td>

                        <!-- Statistiques -->
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ profile.stats.messages_received }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ profile.stats.messages_sent }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ profile.stats.average_response_time }} min
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ profile.stats.retention_rate }}%
                        </td>

                        <!-- Actions -->
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button @click="$emit('view-messages', profile.id)"
                                    class="text-primary hover:text-primary-dark mr-3">
                                <i class="fas fa-envelope"></i>
                            </button>
                            <button @click="$emit('view-details', profile)"
                                    class="text-primary hover:text-primary-dark mr-3">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button @click="$emit('assign-moderator', profile)"
                                    class="text-primary hover:text-primary-dark">
                                <i class="fas fa-user-plus"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue'
import SortIcon from '@/Admin/Components/SortIcon.vue'

const props = defineProps({
    profiles: {
        type: Array,
        required: true
    },
    loading: {
        type: Boolean,
        default: false
    },
    sort: {
        type: Object,
        required: true
    }
})

const emit = defineEmits(['update:sort', 'view-messages', 'view-details', 'assign-moderator'])

const updateSort = (field) => {
    const direction = props.sort.field === field && props.sort.direction === 'asc' ? 'desc' : 'asc'
    emit('update:sort', { field, direction })
}
</script>