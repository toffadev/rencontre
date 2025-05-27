<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center">
        <!-- Overlay -->
        <div 
            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
            @click="closeModal"
        ></div>

        <!-- Modal Content -->
        <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full mx-4 overflow-hidden">
            <!-- Profile Header -->
            <div class="relative h-64">
                <img 
                    :src="profile.main_photo_path || 'https://via.placeholder.com/400'" 
                    :alt="profile.name"
                    class="w-full h-full object-cover"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent"></div>
                <div class="absolute bottom-0 left-0 right-0 p-4 text-white">
                    <h2 class="text-2xl font-bold">{{ profile.name }}</h2>
                    <div class="flex items-center space-x-2 mt-1">
                        <span>{{ formatGender(profile.gender) }}</span>
                        <span>•</span>
                        <span>{{ formatLocation(profile) }}</span>
                    </div>
                </div>
                <!-- Close Button -->
                <button 
                    @click="closeModal"
                    class="absolute top-4 right-4 text-white/90 hover:text-white bg-black/30 hover:bg-black/50 rounded-full p-2 transition"
                >
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Action Buttons -->
            <div class="p-4 space-y-3">
                <button 
                    @click="viewPhoto"
                    class="w-full py-3 px-4 bg-pink-100 text-pink-600 rounded-lg hover:bg-pink-200 transition flex items-center justify-center space-x-2"
                >
                    <i class="fas fa-image"></i>
                    <span>Voir la photo</span>
                </button>
                
                <button 
                    @click="startChat"
                    class="w-full py-3 px-4 bg-pink-500 text-white rounded-lg hover:bg-pink-600 transition flex items-center justify-center space-x-2"
                >
                    <i class="fas fa-comments"></i>
                    <span>Discuter</span>
                </button>
            </div>
        </div>

        <!-- Full Photo Modal -->
        <div 
            v-if="showFullPhoto" 
            class="fixed inset-0 z-60 flex items-center justify-center bg-black/90"
            @click="showFullPhoto = false"
        >
            <img 
                :src="profile.main_photo_path || 'https://via.placeholder.com/800'" 
                :alt="profile.name"
                class="max-w-full max-h-full object-contain"
            />
            <button 
                class="absolute top-4 right-4 text-white/90 hover:text-white"
                @click="showFullPhoto = false"
            >
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        required: true
    },
    profile: {
        type: Object,
        required: true
    }
});

const emit = defineEmits(['close', 'chat']);

const showFullPhoto = ref(false);

function closeModal() {
    emit('close');
}

function viewPhoto() {
    showFullPhoto.value = true;
}

function startChat() {
    emit('chat', props.profile);
    closeModal();
}

function formatGender(gender) {
    const genders = {
        male: "Homme",
        female: "Femme",
        other: "Autre"
    };
    return genders[gender] || "Non spécifié";
}

function formatLocation(profile) {
    return "À proximité";
}
</script> 