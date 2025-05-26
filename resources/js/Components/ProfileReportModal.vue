<template>
    <Modal :show="show" @close="closeModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                Signaler ce profil
            </h2>

            <div class="mt-6">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700"
                        >Raison du signalement</label
                    >
                    <select
                        v-model="form.reason"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
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
                    ></textarea>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button
                        type="button"
                        class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="closeModal"
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        class="inline-flex justify-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                        @click="submitReport"
                        :disabled="processing"
                    >
                        {{ processing ? "Envoi..." : "Signaler" }}
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
});

const emit = defineEmits(["close"]);

const processing = ref(false);
const form = ref({
    reason: "",
    description: "",
});

const closeModal = () => {
    form.value = {
        reason: "",
        description: "",
    };
    emit("close");
};

const submitReport = async () => {
    if (!form.value.reason) {
        return;
    }

    processing.value = true;

    try {
        const response = await axios.post("/profile-reports", {
            reported_user_id: props.userId,
            reason: form.value.reason,
            description: form.value.description,
        });

        closeModal();
        // Émettre un événement pour mettre à jour l'interface utilisateur
        emit("reported", props.userId);
    } catch (error) {
        console.error("Erreur lors du signalement:", error);
    } finally {
        processing.value = false;
    }
};
</script>
