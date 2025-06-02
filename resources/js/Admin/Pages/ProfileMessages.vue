<template>
    <AdminLayout>
        <!-- En-tête avec informations du profil -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <div class="flex items-center space-x-4">
                <img :src="profile.photo" :alt="profile.name" class="w-16 h-16 rounded-full object-cover">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">{{ profile.name }}</h2>
                    <div class="flex items-center space-x-2 mt-1">
                        <span :class="[
                            'px-2 py-1 rounded-full text-xs',
                            profile.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                        ]">
                            {{ profile.status === 'active' ? 'Actif' : 'Inactif' }}
                        </span>
                        <span class="text-sm text-gray-500">
                            {{ profile.moderators.length }} modérateur(s) assigné(s)
                        </span>
                    </div>
                </div>
                <div class="ml-auto">
                    <button @click="goBack" class="btn-secondary">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Retour
                    </button>
                </div>
            </div>
        </div>

        <!-- Filtres pour les messages -->
        <div class="bg-white p-4 rounded-lg shadow-md mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="relative">
                    <input v-model="filters.search" type="text" placeholder="Rechercher dans les messages..."
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-pink-500">
                </div>
                <select v-model="filters.type"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-pink-500">
                    <option value="all">Tous les messages</option>
                    <option value="received">Messages reçus</option>
                    <option value="sent">Messages envoyés</option>
                </select>
                <select v-model="filters.moderator"
                    class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-pink-500">
                    <option value="">Tous les modérateurs</option>
                    <option v-for="mod in profile.moderators" :key="mod.id" :value="mod.id">
                        {{ mod.name }}
                    </option>
                </select>
                <date-picker v-model="filters.date" range class="w-full" placeholder="Sélectionner une période" />
            </div>
        </div>

        <!-- Timeline des messages -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Historique des messages</h3>
            </div>
            <div class="divide-y divide-gray-200">
                <div v-for="message in messages" :key="message.id"
                    class="p-4 hover:bg-gray-50 transition-colors duration-200">
                    <div class="flex items-start space-x-3">
                        <img :src="message.author_avatar" :alt="message.author_name"
                            class="w-10 h-10 rounded-full object-cover">
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <span class="font-medium text-gray-900">{{ message.author_name }}</span>
                                    <span class="text-sm text-gray-500">{{ formatDate(message.created_at) }}</span>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span v-if="message.points" class="text-sm text-green-600">
                                        +{{ message.points }} points
                                    </span>
                                    <button @click="viewConversation(message.conversation_id)"
                                        class="text-pink-600 hover:text-pink-900">
                                        <i class="fas fa-external-link-alt"></i>
                                    </button>
                                </div>
                            </div>
                            <p class="mt-1 text-gray-600">{{ message.content }}</p>
                            <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500">
                                <span v-if="message.is_from_client">
                                    <i class="fas fa-user mr-1"></i>
                                    Client
                                </span>
                                <span v-else>
                                    <i class="fas fa-user-tie mr-1"></i>
                                    Modérateur: {{ message.moderator_name }}
                                </span>
                                <span>
                                    <i class="fas fa-comments mr-1"></i>
                                    Conversation #{{ message.conversation_id }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            <Pagination :links="paginationLinks" @navigate="loadMessages" />
        </div>

        <!-- Modal de conversation -->
        <ConversationModal v-if="showConversation" :conversation-id="selectedConversationId"
            @close="showConversation = false" />
    </AdminLayout>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import ConversationModal from '@/Components/ProfilePerformance/ConversationModal.vue'
import { router } from '@inertiajs/vue3'
import DatePicker from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'

const props = defineProps({
    profileId: {
        type: [Number, String],
        required: true
    }
})

// État
const profile = ref(null)
const messages = ref([])
const loading = ref(true)
const paginationLinks = ref([])
const showConversation = ref(false)
const selectedConversationId = ref(null)
const filters = ref({
    search: '',
    type: 'all',
    moderator: '',
    date: null
})

// Chargement des données
const loadProfile = async () => {
    try {
        const response = await axios.get(`/admin/profiles/${props.profileId}`)
        profile.value = response.data
    } catch (error) {
        console.error('Erreur lors du chargement du profil:', error)
    }
}

const loadMessages = async (page = 1) => {
    loading.value = true
    try {
        const response = await axios.get(`/admin/profile-performance/${props.profileId}/messages`, {
            params: {
                ...filters.value,
                page
            }
        })
        messages.value = response.data.data
        paginationLinks.value = response.data.links
    } catch (error) {
        console.error('Erreur lors du chargement des messages:', error)
    } finally {
        loading.value = false
    }
}

// Actions
const viewConversation = (conversationId) => {
    selectedConversationId.value = conversationId
    showConversation.value = true
}

const goBack = () => {
    router.visit('/admin/profile-performance')
}

const formatDate = (date) => {
    return new Date(date).toLocaleString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

// Watchers
watch(filters, () => {
    loadMessages()
}, { deep: true })

// Lifecycle hooks
onMounted(() => {
    loadProfile()
    loadMessages()
})
</script>