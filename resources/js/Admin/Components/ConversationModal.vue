<template>
    <Modal :show="true" @close="$emit('close')" max-width="4xl">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Conversation complète
                </h2>
                <button 
                    @click="$emit('close')"
                    class="text-gray-400 hover:text-gray-500"
                >
                    <span class="sr-only">Fermer</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="space-y-4 max-h-[60vh] overflow-y-auto px-2">
                <div 
                    v-for="message in conversation" 
                    :key="message.id"
                    :class="[
                        'flex',
                        message.is_from_client ? 'justify-start' : 'justify-end'
                    ]"
                >
                    <div 
                        :class="[
                            'rounded-lg px-4 py-2 max-w-[70%]',
                            message.is_from_client 
                                ? 'bg-gray-100 text-gray-900' 
                                : 'bg-primary text-white'
                        ]"
                    >
                        <div class="text-sm">{{ message.content }}</div>
                        <div 
                            :class="[
                                'text-xs mt-1',
                                message.is_from_client ? 'text-gray-500' : 'text-primary-light'
                            ]"
                        >
                            {{ formatDate(message.created_at) }}
                            <template v-if="!message.is_from_client">
                                • {{ message.moderator.name }}
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue';
import Modal from '@/Admin/Components/Modal.vue';

defineProps({
    conversation: {
        type: Array,
        required: true
    }
});

defineEmits(['close']);

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