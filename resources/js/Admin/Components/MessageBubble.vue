<template>
    <div :class="[
        'flex items-start space-x-2 mb-4',
        message.is_from_client ? '' : 'flex-row-reverse space-x-reverse'
    ]">
        <!-- Avatar -->
        <div class="flex-shrink-0">
            <img 
                :src="avatarUrl" 
                :alt="authorName"
                class="w-8 h-8 rounded-full object-cover"
            />
        </div>

        <!-- Message Content -->
        <div :class="[
            'max-w-[70%]',
            message.is_from_client ? 'items-start' : 'items-end'
        ]">
            <!-- Author Name -->
            <div :class="[
                'text-xs text-gray-500 mb-1',
                message.is_from_client ? 'text-left' : 'text-right'
            ]">
                {{ authorName }}
                <span v-if="message.moderator && !message.is_from_client" 
                    class="text-xs text-gray-400">
                    (mod: {{ message.moderator.name }})
                </span>
            </div>

            <!-- Message Bubble -->
            <div :class="[
                'rounded-lg px-4 py-2 inline-block',
                message.is_from_client 
                    ? 'bg-gray-100 text-gray-900 rounded-bl-none' 
                    : 'bg-pink-500 text-white rounded-br-none'
            ]">
                {{ message.content }}
            </div>

            <!-- Timestamp and Read Status -->
            <div :class="[
                'flex items-center space-x-1 mt-1 text-xs text-gray-400',
                message.is_from_client ? 'justify-start' : 'justify-end'
            ]">
                <span>{{ formatTime(message.created_at) }}</span>
                <span v-if="message.read_at" class="text-green-500">
                    <i class="fas fa-check-double"></i>
                </span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    message: {
        type: Object,
        required: true
    },
    clientAvatar: {
        type: String,
        default: null
    },
    profileAvatar: {
        type: String,
        default: null
    },
    clientName: {
        type: String,
        required: true
    },
    profileName: {
        type: String,
        required: true
    }
});

const avatarUrl = computed(() => {
    return props.message.is_from_client 
        ? props.clientAvatar || 'https://via.placeholder.com/32'
        : props.profileAvatar || 'https://via.placeholder.com/32';
});

const authorName = computed(() => {
    return props.message.is_from_client ? props.clientName : props.profileName;
});

const formatTime = (timestamp) => {
    const date = new Date(timestamp);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};
</script> 