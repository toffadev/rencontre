<template>
    <div class="bg-white rounded-lg shadow h-full flex flex-col">
        <!-- Header -->
        <div class="p-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="flex -space-x-2">
                        <img 
                            :src="clientAvatar" 
                            :alt="clientName"
                            class="w-10 h-10 rounded-full border-2 border-white"
                        />
                        <img 
                            :src="profileAvatar" 
                            :alt="profileName"
                            class="w-10 h-10 rounded-full border-2 border-white"
                        />
                    </div>
                    <div>
                        <h2 class="font-semibold text-gray-900">
                            {{ clientName }} & {{ profileName }}
                        </h2>
                        <p class="text-sm text-gray-500">
                            {{ messages.length }} messages
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button 
                        @click="scrollToBottom"
                        class="p-2 text-gray-400 hover:text-gray-600 transition-colors"
                        title="Aller en bas"
                    >
                        <i class="fas fa-arrow-down"></i>
                    </button>
                    <button 
                        @click="scrollToTop"
                        class="p-2 text-gray-400 hover:text-gray-600 transition-colors"
                        title="Aller en haut"
                    >
                        <i class="fas fa-arrow-up"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Messages Container -->
        <div 
            ref="messagesContainer"
            class="flex-1 overflow-y-auto p-4 space-y-4"
            @scroll="handleScroll"
        >
            <!-- Loading More Messages Indicator -->
            <div v-if="loadingMore" class="text-center py-2">
                <i class="fas fa-spinner fa-spin text-gray-400"></i>
                <span class="ml-2 text-sm text-gray-500">
                    Chargement des messages...
                </span>
            </div>

            <!-- Messages -->
            <template v-for="(group, date) in groupedMessages" :key="date">
                <!-- Date Separator -->
                <div class="flex items-center justify-center my-6">
                    <div class="bg-gray-200 px-3 py-1 rounded-full">
                        <span class="text-xs text-gray-600">{{ formatDate(date) }}</span>
                    </div>
                </div>

                <!-- Messages for this date -->
                <MessageBubble
                    v-for="message in group"
                    :key="message.id"
                    :message="message"
                    :client-avatar="clientAvatar"
                    :profile-avatar="profileAvatar"
                    :client-name="clientName"
                    :profile-name="profileName"
                />
            </template>

            <!-- Empty State -->
            <div v-if="messages.length === 0" class="text-center py-8">
                <div class="text-gray-400 mb-2">
                    <i class="fas fa-comments text-4xl"></i>
                </div>
                <p class="text-gray-500">Aucun message dans cette conversation</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue';
import MessageBubble from './MessageBubble.vue';

const props = defineProps({
    messages: {
        type: Array,
        required: true
    },
    clientName: {
        type: String,
        required: true
    },
    clientAvatar: {
        type: String,
        default: 'https://via.placeholder.com/40'
    },
    profileName: {
        type: String,
        required: true
    },
    profileAvatar: {
        type: String,
        default: 'https://via.placeholder.com/40'
    },
    loading: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['load-more']);

const messagesContainer = ref(null);
const loadingMore = ref(false);
let scrollTimeout;

// Group messages by date
const groupedMessages = computed(() => {
    const groups = {};
    props.messages.forEach(message => {
        const date = new Date(message.created_at).toISOString().split('T')[0];
        if (!groups[date]) {
            groups[date] = [];
        }
        groups[date].push(message);
    });
    return groups;
});

// Format date for display
const formatDate = (dateString) => {
    const date = new Date(dateString);
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    if (dateString === today.toISOString().split('T')[0]) {
        return "Aujourd'hui";
    } else if (dateString === yesterday.toISOString().split('T')[0]) {
        return "Hier";
    } else {
        return date.toLocaleDateString('fr-FR', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
    }
};

// Scroll handlers
const handleScroll = () => {
    if (!messagesContainer.value) return;

    clearTimeout(scrollTimeout);
    scrollTimeout = setTimeout(() => {
        const { scrollTop } = messagesContainer.value;
        
        // If we're near the top and not already loading, emit load-more
        if (scrollTop < 100 && !loadingMore.value && !props.loading) {
            loadingMore.value = true;
            emit('load-more');
        }
    }, 100);
};

const scrollToBottom = () => {
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
};

const scrollToTop = () => {
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = 0;
    }
};

// Watch for new messages and scroll to bottom
watch(() => props.messages.length, (newLength, oldLength) => {
    if (newLength > oldLength) {
        scrollToBottom();
    }
    loadingMore.value = false;
});

// Initial scroll to bottom
onMounted(() => {
    scrollToBottom();
});

// Clean up
onUnmounted(() => {
    clearTimeout(scrollTimeout);
});
</script>

<style scoped>
/* Custom scrollbar styles */
.overflow-y-auto {
    scrollbar-width: thin;
    scrollbar-color: #E5E7EB transparent;
}

.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: transparent;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background-color: #E5E7EB;
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background-color: #D1D5DB;
}
</style> 