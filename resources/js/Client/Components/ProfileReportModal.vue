<template>
    <Modal :show="show" @close="closeModal">
        <div class="p-6">
            <div class="mb-4">
                <h2 class="text-lg font-medium text-gray-900">
                    Signaler ce profil
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Veuillez sélectionner la raison du signalement.
                </p>
            </div>

            <div class="space-y-4">
                <div
                    v-if="error"
                    class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded"
                >
                    {{ error }}
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">
                        Raison du signalement
                    </label>
                    <select
                        v-model="selectedReason"
                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-pink-500 focus:border-pink-500 sm:text-sm rounded-md"
                    >
                        <option value="">Sélectionnez une raison</option>
                        <option
                            v-for="reason in reasons"
                            :key="reason.id"
                            :value="reason.id"
                        >
                            {{ reason.label }}
                        </option>
                    </select>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">
                        Description détaillée (optionnelle)
                    </label>
                    <textarea
                        v-model="description"
                        rows="4"
                        class="shadow-sm focus:ring-pink-500 focus:border-pink-500 block w-full sm:text-sm border-gray-300 rounded-md"
                        placeholder="Décrivez le problème en détail..."
                    ></textarea>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <button
                    type="button"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pink-500"
                    @click="closeModal"
                >
                    Annuler
                </button>
                <button
                    type="button"
                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-pink-600 hover:bg-pink-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pink-500"
                    :disabled="!selectedReason || loading"
                    @click="submitReport"
                >
                    <span v-if="loading" class="flex items-center">
                        <svg
                            class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        Envoi en cours...
                    </span>
                    <span v-else>Envoyer le signalement</span>
                </button>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref } from "vue";
import Modal from "@client/Components/Modal.vue";
import axios from "axios";

const props = defineProps({
    show: {
        type: Boolean,
        required: true,
    },
    userId: {
        type: Number,
        required: false,
        default: null,
    },
    profileId: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(["close", "reported"]);

const selectedReason = ref("");
const description = ref("");
const loading = ref(false);
const error = ref("");

const reasons = [
    { id: "inappropriate_content", label: "Contenu inapproprié" },
    { id: "harassment", label: "Harcèlement" },
    { id: "spam", label: "Spam" },
    { id: "fake_profile", label: "Faux profil" },
    { id: "scam", label: "Arnaque" },
    { id: "other", label: "Autre" },
];

const closeModal = () => {
    selectedReason.value = "";
    description.value = "";
    error.value = "";
    emit("close");
};

const submitReport = async () => {
    if (!selectedReason.value) {
        error.value = "Veuillez sélectionner une raison";
        return;
    }

    loading.value = true;
    error.value = "";

    try {
        await axios.post("/profile-reports", {
            reported_user_id: props.userId,
            reported_profile_id: props.profileId,
            reason: selectedReason.value,
            description: description.value,
        });

        emit("reported", props.profileId);
        closeModal();
    } catch (e) {
        error.value =
            e.response?.data?.message ||
            "Une erreur est survenue lors du signalement";
    } finally {
        loading.value = false;
    }
};
</script>
