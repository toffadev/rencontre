<template>
    <Modal :show="true" @close="$emit('close')" max-width="4xl">
        <div class="flex flex-col h-[80vh]">
            <!-- En-tête -->
            <div class="p-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <h2 class="text-lg font-semibold text-gray-900">
                            Conversation #{{ conversationId }}
                        </h2>
                        <span :class="[
                            'px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full',
                            conversation.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                        ]">
                            {{ conversation.status === 'active' ? 'Active' : 'Terminée' }}
                        </span>
                    </div>
                    <button @click="$emit('close')" class="text-gray-400 hover:text-gray-500 transition-colors duration-150">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Informations de la conversation -->
                <div class="mt-4 grid grid-cols-3 gap-4 text-sm text-gray-500">
                    <div>
                        <span class="font-medium">Client:</span>
                        {{ conversation.client_name }}
                    </div>
                    <div>
                        <span class="font-medium">Profil:</span>
                        {{ conversation.profile_name }}
                    </div>
                    <div>
                        <span class="font-medium">Points générés:</span>
                        {{ conversation.total_points }}
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <div class="flex-1 overflow-y-auto p-4 space-y-4" ref="messagesContainer">
                <div v-for="message in groupedMessages" :key="message.date" class="space-y-4">
                    <!-- Date -->
                    <div class="sticky top-0 z-10 flex justify-center">
                        <span class="bg-gray-100 px-3 py-1 rounded-full text-xs text-gray-600">
                            {{ formatDate(message.date) }}
                        </span>
                    </div>

                    <!-- Messages du jour -->
                    <div v-for="msg in message.messages" :key="msg.id" class="flex space-x-3"
                        :class="{ 'justify-end': !msg.is_from_client }">
                        <template v-if="msg.is_from_client">
                            <div class="flex-shrink-0">
                                <img :src="msg.author_avatar" :alt="msg.author_name" class="h-8 w-8 rounded-full object-cover">
                            </div>
                        </template>

                        <div :class="[
                            'max-w-lg rounded-lg px-4 py-2',
                            msg.is_from_client ? 'bg-gray-100' : 'bg-pink-100'
                        ]">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium text-gray-900">
                                    {{ msg.is_from_client ? msg.author_name : msg.moderator_name }}
                                </span>
                                <span class="text-xs text-gray-500">
                                    {{ formatTime(msg.created_at) }}
                                </span>
                            </div>
                            <p class="text-gray-700">{{ msg.content }}</p>
                            <div class="mt-1 flex items-center justify-between text-xs text-gray-500">
                                <span v-if="!msg.is_from_client">
                                    Modérateur: {{ msg.moderator_name }}
                                </span>
                                <span v-if="msg.points" class="text-green-600 font-medium">
                                    +{{ msg.points }} points
                                </span>
                            </div>
                        </div>

                        <template v-if="!msg.is_from_client">
                            <div class="flex-shrink-0">
                                <img :src="msg.moderator_avatar" :alt="msg.moderator_name" class="h-8 w-8 rounded-full object-cover">
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Statistiques de la conversation -->
            <div class="border-t border-gray-200 p-4">
                <div class="grid grid-cols-4 gap-4">
                    <div class="text-center">
                        <div class="text-sm font-medium text-gray-500">Messages totaux</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900">
                            {{ stats.total_messages }}
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="text-sm font-medium text-gray-500">Temps moyen de réponse</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900">
                            {{ formatDuration(stats.average_response_time) }}
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="text-sm font-medium text-gray-500">Points générés</div>
                        <div class="mt-1 text-lg font-semibold text-green-600">
                            {{ stats.total_points }}
                        </div>
                    </div>
                    <div class="text-center">
                        <div class="text-sm font-medium text-gray-500">Durée totale</div>
                        <div class="mt-1 text-lg font-semibold text-gray-900">
                            {{ formatDuration(stats.total_duration) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import Modal from '@/Components/Modal.vue'

const props = defineProps({
    conversationId: {
        type: [Number, String],
        required: true
    }
})

const emit = defineEmits(['close'])

// État
const conversation = ref(null)
const groupedMessages = ref([])
const stats = ref({
    total_messages: 0,
    average_response_time: 0,
    total_points: 0,
    total_duration: 0
})
const messagesContainer = ref(null)

// Chargement des données
const loadConversation = async () => {
    try {
        const response = await axios.get(`/admin/conversations/${props.conversationId}`)
        conversation.value = response.data.conversation
        groupMessages(response.data.messages)
        stats.value = response.data.stats
    } catch (error) {
        console.error('Erreur lors du chargement de la conversation:', error)
    }
}

// Grouper les messages par date
const groupMessages = (messages) => {
    const grouped = messages.reduce((acc, message) => {
        const date = new Date(message.created_at).toLocaleDateString('fr-FR')
        if (!acc[date]) {
            acc[date] = {
                date: date,
                messages: []
            }
        }
        acc[date].messages.push(message)
        return acc
    }, {})

    groupedMessages.value = Object.values(grouped)
}

// Formatage
const formatDate = (date) => {
    return new Date(date).toLocaleDateString('fr-FR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    })
}

const formatTime = (datetime) => {
    return new Date(datetime).toLocaleTimeString('fr-FR', {
        hour: '2-digit',
        minute: '2-digit'
    })
}

const formatDuration = (minutes) => {
    if (minutes < 60) {
        return `${minutes}min`
    }
    const hours = Math.floor(minutes / 60)
    const remainingMinutes = minutes % 60
    return `${hours}h${remainingMinutes}min`
}

// Scroll to bottom on new messages
watch(groupedMessages, () => {
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
})

onMounted(() => {
    loadConversation()
})
</script>