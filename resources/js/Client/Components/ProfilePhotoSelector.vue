<template>
    <div>
        <!-- Bouton pour ouvrir le modal -->
        <button 
            class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition"
            title="Galerie de photos du profil"
            @click="openModal"
        >
            <i class="fas fa-images"></i>
        </button>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Overlay de fond -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="closeModal"></div>

                <!-- Centre le modal -->
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    Photos du profil
                                </h3>
                                
                                <!-- État de chargement -->
                                <div v-if="loading" class="py-8 flex justify-center">
                                    <div class="animate-spin rounded-full h-8 w-8 border-2 border-pink-500 border-t-transparent"></div>
                                </div>
                                
                                <!-- Message d'erreur -->
                                <div v-else-if="error" class="py-4 text-red-500 text-center">
                                    {{ error }}
                                </div>
                                
                                <!-- Grille de photos -->
                                <div v-else class="mt-4 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                                    <div 
                                        v-for="photo in photos" 
                                        :key="photo.id" 
                                        class="relative aspect-square overflow-hidden rounded-lg cursor-pointer"
                                        :class="{ 'opacity-50': photo.already_sent }"
                                        @click="selectPhoto(photo)"
                                    >
                                        <img :src="photo.url" :alt="'Photo ' + photo.id" class="object-cover w-full h-full">
                                        
                                        <!-- Indicateur pour les photos déjà envoyées -->
                                        <div v-if="photo.already_sent" class="absolute inset-0 flex items-center justify-center">
                                            <div class="bg-red-500 text-white rounded-full p-1">
                                                <i class="fas fa-times"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Message si aucune photo disponible -->
                                <div v-if="photos.length === 0 && !loading && !error" class="py-8 text-center text-gray-500">
                                    Aucune photo disponible pour ce profil
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button 
                            type="button" 
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            @click="closeModal"
                        >
                            Fermer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';

// Props
const props = defineProps({
    profileId: {
        type: Number,
        required: true
    },
    clientId: {
        type: Number,
        required: true
    }
});

// Emits
const emit = defineEmits(['photo-selected']);

// State
const showModal = ref(false);
const photos = ref([]);
const loading = ref(false);
const error = ref(null);

// Methods
const openModal = async () => {
    showModal.value = true;
    await loadPhotos();
};

const closeModal = () => {
    showModal.value = false;
};

const loadPhotos = async () => {
    loading.value = true;
    error.value = null;
    
    try {
        const response = await axios.get('/moderateur/profile-photos', {
            params: {
                profile_id: props.profileId,
                client_id: props.clientId
            }
        });
        
        if (response.data.success) {
            photos.value = response.data.photos;
        } else {
            error.value = "Erreur lors du chargement des photos";
        }
    } catch (err) {
        console.error("Erreur lors du chargement des photos:", err);
        error.value = "Erreur lors du chargement des photos";
    } finally {
        loading.value = false;
    }
};

const selectPhoto = (photo) => {
    // Ne rien faire si la photo a déjà été envoyée
    if (photo.already_sent) {
        return;
    }
    
    // Émettre l'événement avec la photo sélectionnée
    emit('photo-selected', photo);
    
    // Fermer le modal
    closeModal();
};

// Watch pour recharger les photos si les props changent
watch([() => props.profileId, () => props.clientId], async () => {
    if (showModal.value) {
        await loadPhotos();
    }
});
</script>

<style scoped>
/* Styles pour l'animation du modal */
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style> 