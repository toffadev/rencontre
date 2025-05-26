<template>
    <Modal :show="show" @close="closeModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                Signaler ce profil
            </h2>

            <div class="mt-6">
                <!-- Message d'erreur -->
                <div v-if="error" class="mb-4 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg">
                    {{ error }}
                </div>

                <!-- Message de succès -->
                <div v-if="success" class="mb-4 bg-green-50 border border-green-200 text-green-600 px-4 py-3 rounded-lg">
                    {{ success }}
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700"
                        >Raison du signalement</label
                    >
                    <select
                        v-model="form.reason"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        :disabled="processing"
                    >
                        <option value="">Sélectionnez une raison</option>
                        <option value="fake_profile">Faux profil</option>
                        <option value="inappropriate_content">
                            Contenu inapproprié
                        </option>
                        <option value="harassment">Harcèlement</option>
                        <option value="spam">Spam</option>
                        <option value="other">Autre</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700"
                        >Description (optionnelle)</label
                    >
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Donnez plus de détails sur votre signalement..."
                        :disabled="processing"
                    ></textarea>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button
                        type="button"
                        class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="closeModal"
                        :disabled="processing"
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                        @click="submitReport"
                        :disabled="processing || !form.reason"
                    >
                        <template v-if="processing">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Envoi en cours...
                        </template>
                        <template v-else>
                            Signaler
                        </template>
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref, defineProps, defineEmits } from "vue";
import Modal from "../Components/Modal.vue";
import { router } from "@inertiajs/vue3";

const props = defineProps({
    show: Boolean,
    userId: Number,
    profileId: Number,
});

const emit = defineEmits(["close", "reported"]);

const processing = ref(false);
const error = ref(null);
const success = ref(null);
const form = ref({
    reason: "",
    description: "",
});

const closeModal = () => {
    if (processing.value) return;
    
    form.value = {
        reason: "",
        description: "",
    };
    error.value = null;
    success.value = null;
    emit("close");
};

const submitReport = async () => {
    console.log('Submit Report - Form State:', {
        reason: form.value.reason,
        description: form.value.description,
        userId: props.userId,
        profileId: props.profileId
    });

    // Vérification de la raison
    if (!form.value.reason || form.value.reason === "") {
        error.value = "Veuillez sélectionner une raison pour le signalement.";
        return;
    }

    // Vérification de l'ID du profil
    if (!props.profileId) {
        console.error('Missing profileId:', { profileId: props.profileId });
        error.value = "Une erreur est survenue. ID du profil manquant.";
        return;
    }

    error.value = null;
    success.value = null;
    processing.value = true;

    try {
        // Préparer les données de base
        const requestData = {
            reported_profile_id: props.profileId,
            reason: form.value.reason,
            description: form.value.description || ""
        };

        // Ajouter reported_user_id seulement s'il existe
        if (props.userId) {
            requestData.reported_user_id = props.userId;
        }

        console.log('Sending request with data:', requestData);

        const response = await axios.post("/profile-reports", requestData);
        console.log('Response:', response.data);

        success.value = "Profil signalé avec succès";
        
        // Émettre l'événement reported immédiatement
        emit("reported", props.profileId);
        
        // Attendre un peu pour montrer le message de succès puis fermer
        setTimeout(() => {
            processing.value = false; // Arrêter le processing
            closeModal();
        }, 1500);

    } catch (error) {
        console.error("Erreur détaillée:", error.response?.data || error);
        if (error.response?.status === 422) {
            error.value = error.response.data.message || "Vous avez déjà signalé ce profil.";
        } else {
            error.value = "Une erreur est survenue lors du signalement. Veuillez réessayer.";
        }
        processing.value = false;
    }
};
</script>

<style scoped>
.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}
</style>
