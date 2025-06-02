<template>
    <Modal :show="true" @close="$emit('close')">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Détails du message
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

            <div class="space-y-6">
                <!-- Informations sur le profil -->
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Profil utilisé</h3>
                    <div class="flex items-center">
                        <img 
                            :src="message.profile.photo" 
                            :alt="message.profile.name"
                            class="h-12 w-12 rounded-full mr-3"
                        >
                        <div>
                            <div class="font-medium text-gray-900">{{ message.profile.name }}</div>
                            <div class="text-sm text-gray-500">ID: {{ message.profile.id }}</div>
                        </div>
                    </div>
                </div>

                <!-- Informations sur le client -->
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Client</h3>
                    <div class="font-medium text-gray-900">{{ message.client.name }}</div>
                    <div class="text-sm text-gray-500">ID: {{ message.client.id }}</div>
                </div>

                <!-- Contenu du message -->
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Message</h3>
                    <div class="bg-gray-50 rounded-lg p-4 text-gray-900">
                        {{ message.content }}
                    </div>
                </div>

                <!-- Métriques -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-2">Longueur</h3>
                        <div class="font-medium text-gray-900">
                            {{ message.length }} caractères
                            <span class="text-sm text-gray-500">
                                ({{ message.length >= 10 ? 'Long' : 'Court' }})
                            </span>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500 mb-2">Points gagnés</h3>
                        <div class="font-medium text-gray-900">{{ message.points_earned }} points</div>
                    </div>
                </div>

                <!-- Horodatage -->
                <div>
                    <h3 class="text-sm font-medium text-gray-500 mb-2">Horodatage</h3>
                    <div class="text-gray-900">{{ formatDate(message.created_at) }}</div>
                </div>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue';
import Modal from '@/Admin/Components/Modal.vue';

defineProps({
    message: {
        type: Object,
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
        minute: '2-digit',
        second: '2-digit'
    });
};
</script> 