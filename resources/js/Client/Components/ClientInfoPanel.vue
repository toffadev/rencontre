<template>
    <div class="bg-white rounded-xl shadow-md p-4 mb-4">
        <!-- Notification -->
        <div
            v-if="notification.show"
            :class="[
                'fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg z-50 transition-all duration-300',
                notification.type === 'success'
                    ? 'bg-green-500 text-white'
                    : 'bg-red-500 text-white',
            ]"
        >
            {{ notification.message }}
        </div>

        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">
                Informations du client
            </h3>
            <button
                @click="showBasicInfoForm = !showBasicInfoForm"
                class="text-sm px-3 py-1 rounded-full bg-pink-100 text-pink-600 hover:bg-pink-200 transition"
            >
                {{ showBasicInfoForm ? "Annuler" : "Modifier" }}
            </button>
        </div>

        <!-- Informations de base -->
        <div v-if="!showBasicInfoForm" class="mb-6">
            <div class="grid grid-cols-1 gap-4">
                <div
                    v-if="basicInfo?.age"
                    class="bg-white p-3 rounded-lg border border-gray-200"
                >
                    <span class="block text-sm font-medium text-gray-600 mb-1"
                        >Âge</span
                    >
                    <span class="block text-gray-800"
                        >{{ basicInfo.age }} ans</span
                    >
                </div>
                <div
                    v-if="basicInfo?.ville"
                    class="bg-white p-3 rounded-lg border border-gray-200"
                >
                    <span class="block text-sm font-medium text-gray-600 mb-1"
                        >Ville</span
                    >
                    <span class="block text-gray-800">{{
                        basicInfo.ville
                    }}</span>
                </div>
                <div
                    v-if="basicInfo?.quartier"
                    class="bg-white p-3 rounded-lg border border-gray-200"
                >
                    <span class="block text-sm font-medium text-gray-600 mb-1"
                        >Quartier</span
                    >
                    <span class="block text-gray-800">{{
                        basicInfo.quartier
                    }}</span>
                </div>
                <div
                    v-if="basicInfo?.profession"
                    class="bg-white p-3 rounded-lg border border-gray-200"
                >
                    <span class="block text-sm font-medium text-gray-600 mb-1"
                        >Profession</span
                    >
                    <span class="block text-gray-800">{{
                        basicInfo.profession
                    }}</span>
                </div>
                <div
                    v-if="basicInfo?.celibataire"
                    class="bg-white p-3 rounded-lg border border-gray-200"
                >
                    <span class="block text-sm font-medium text-gray-600 mb-1"
                        >Célibataire</span
                    >
                    <span class="block text-gray-800">{{
                        basicInfo.celibataire === "oui" ? "Oui" : "Non"
                    }}</span>
                </div>
                <div
                    v-if="basicInfo?.situation_residence"
                    class="bg-white p-3 rounded-lg border border-gray-200"
                >
                    <span class="block text-sm font-medium text-gray-600 mb-1"
                        >Situation de résidence</span
                    >
                    <span class="block text-gray-800">{{
                        formatSituationResidence(basicInfo.situation_residence)
                    }}</span>
                </div>
                <div
                    v-if="basicInfo?.orientation"
                    class="bg-white p-3 rounded-lg border border-gray-200"
                >
                    <span class="block text-sm font-medium text-gray-600 mb-1"
                        >Orientation</span
                    >
                    <span class="block text-gray-800">{{
                        basicInfo.orientation
                    }}</span>
                </div>
                <div
                    v-if="basicInfo?.loisirs"
                    class="bg-white p-3 rounded-lg border border-gray-200"
                >
                    <span class="block text-sm font-medium text-gray-600 mb-1"
                        >Loisirs</span
                    >
                    <span class="block text-gray-800">{{
                        basicInfo.loisirs
                    }}</span>
                </div>
                <div
                    v-if="basicInfo?.preference_negative"
                    class="bg-white p-3 rounded-lg border border-gray-200"
                >
                    <span class="block text-sm font-medium text-gray-600 mb-1"
                        >Ce qu'il/elle n'aime pas</span
                    >
                    <span class="block text-gray-800">{{
                        basicInfo.preference_negative
                    }}</span>
                </div>
            </div>
            <div v-if="!basicInfo" class="text-gray-500 text-sm italic">
                Aucune information de base renseignée
            </div>
        </div>

        <!-- Formulaire des informations de base -->
        <form v-else @submit.prevent="updateBasicInfo" class="mb-6">
            <div class="grid grid-cols-1 gap-4">
                <div class="flex flex-col">
                    <label class="block text-sm font-medium text-gray-600 mb-1"
                        >Âge</label
                    >
                    <input
                        type="number"
                        v-model="basicInfoForm.age"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500 focus:border-transparent"
                        min="18"
                        max="100"
                    />
                </div>
                <div class="flex flex-col">
                    <label class="block text-sm font-medium text-gray-600 mb-1"
                        >Ville</label
                    >
                    <input
                        type="text"
                        v-model="basicInfoForm.ville"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500 focus:border-transparent"
                    />
                </div>
                <div class="flex flex-col">
                    <label class="block text-sm font-medium text-gray-600 mb-1"
                        >Quartier</label
                    >
                    <input
                        type="text"
                        v-model="basicInfoForm.quartier"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500 focus:border-transparent"
                    />
                </div>
                <div class="flex flex-col">
                    <label class="block text-sm font-medium text-gray-600 mb-1"
                        >Profession</label
                    >
                    <input
                        type="text"
                        v-model="basicInfoForm.profession"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500 focus:border-transparent"
                    />
                </div>
                <div class="flex flex-col">
                    <label class="block text-sm font-medium text-gray-600 mb-1"
                        >Célibataire</label
                    >
                    <select
                        v-model="basicInfoForm.celibataire"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500 focus:border-transparent"
                    >
                        <option value="">Non spécifié</option>
                        <option value="oui">Oui</option>
                        <option value="non">Non</option>
                    </select>
                </div>
                <div class="flex flex-col">
                    <label class="block text-sm font-medium text-gray-600 mb-1"
                        >Situation de résidence</label
                    >
                    <select
                        v-model="basicInfoForm.situation_residence"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500 focus:border-transparent"
                    >
                        <option value="">Non spécifié</option>
                        <option value="seul">Seul(e)</option>
                        <option value="colocation">En colocation</option>
                        <option value="famille">En famille</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
                <div class="flex flex-col">
                    <label class="block text-sm font-medium text-gray-600 mb-1"
                        >Orientation</label
                    >
                    <select
                        v-model="basicInfoForm.orientation"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500 focus:border-transparent"
                    >
                        <option value="">Non spécifié</option>
                        <option value="heterosexuel">Hétérosexuel</option>
                        <option value="homosexuel">Homosexuel</option>
                        <option value="bisexuel">Bisexuel</option>
                    </select>
                </div>
                <div class="flex flex-col">
                    <label class="block text-sm font-medium text-gray-600 mb-1"
                        >Loisirs</label
                    >
                    <input
                        type="text"
                        v-model="basicInfoForm.loisirs"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500 focus:border-transparent"
                    />
                </div>
                <div class="flex flex-col">
                    <label class="block text-sm font-medium text-gray-600 mb-1"
                        >Ce qu'il/elle n'aime pas</label
                    >
                    <input
                        type="text"
                        v-model="basicInfoForm.preference_negative"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500 focus:border-transparent"
                    />
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button
                    type="submit"
                    :disabled="isLoadingBasicInfo"
                    class="px-4 py-2 bg-pink-500 text-white rounded-lg hover:bg-pink-600 transition flex items-center space-x-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <i
                        v-if="isLoadingBasicInfo"
                        class="fas fa-spinner fa-spin"
                    ></i>
                    <span>{{
                        isLoadingBasicInfo ? "Enregistrement..." : "Enregistrer"
                    }}</span>
                </button>
            </div>
        </form>

        <!-- Informations personnalisées -->
        <div class="mb-4">
            <h4 class="font-medium text-gray-700 mb-2">
                Informations personnalisées
            </h4>
            <div class="space-y-3">
                <div
                    v-for="info in customInfos"
                    :key="info.id"
                    class="bg-white p-3 rounded-lg border border-gray-200"
                >
                    <div class="flex justify-between items-start">
                        <div>
                            <h5 class="font-medium text-gray-800">
                                {{ info.titre }}
                            </h5>
                            <p class="text-gray-600">{{ info.contenu }}</p>
                            <div class="text-xs text-gray-500 mt-1">
                                Ajouté par {{ info.added_by?.name }} le
                                {{ formatDate(info.created_at) }}
                            </div>
                        </div>
                        <button
                            @click="deleteInfo(info.id)"
                            :disabled="isLoadingDelete"
                            class="text-gray-400 hover:text-red-500 transition disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <i
                                :class="
                                    isLoadingDelete
                                        ? 'fas fa-spinner fa-spin'
                                        : 'fas fa-trash'
                                "
                            ></i>
                        </button>
                    </div>
                </div>
                <div
                    v-if="customInfos.length === 0"
                    class="text-gray-500 text-sm italic"
                >
                    Aucune information personnalisée
                </div>
            </div>
        </div>

        <!-- Formulaire d'ajout d'information personnalisée -->
        <form
            @submit.prevent="addCustomInfo"
            class="border-t border-gray-100 pt-4"
        >
            <h4 class="font-medium text-gray-700 mb-3">
                Ajouter une information
            </h4>
            <div class="space-y-3">
                <div class="flex flex-col">
                    <label class="block text-sm font-medium text-gray-600 mb-1"
                        >Titre de l'information</label
                    >
                    <input
                        type="text"
                        v-model="customInfoForm.titre"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500 focus:border-transparent"
                        placeholder="Ex: Sport préféré"
                    />
                </div>
                <div class="flex flex-col">
                    <label class="block text-sm font-medium text-gray-600 mb-1"
                        >Contenu</label
                    >
                    <textarea
                        v-model="customInfoForm.contenu"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500 focus:border-transparent"
                        rows="3"
                        placeholder="Ex: Football"
                    ></textarea>
                </div>
            </div>
            <div class="mt-4">
                <button
                    type="submit"
                    :disabled="isLoadingCustomInfo"
                    class="w-full px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-50 border border-gray-200 transition flex items-center justify-center space-x-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <i
                        v-if="isLoadingCustomInfo"
                        class="fas fa-spinner fa-spin"
                    ></i>
                    <span>{{
                        isLoadingCustomInfo
                            ? "Ajout en cours..."
                            : "Ajouter cette information"
                    }}</span>
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from "vue";
import axios from "axios";

const props = defineProps({
    clientId: {
        type: [Number, String],
        required: true,
    },
});

// État local
const basicInfo = ref(null);
const customInfos = ref([]);
const showBasicInfoForm = ref(false);
const notification = ref({ show: false, message: "", type: "success" });
const isLoadingBasicInfo = ref(false);
const isLoadingCustomInfo = ref(false);
const isLoadingDelete = ref(false);

// Formulaires
const basicInfoForm = ref({
    age: "",
    ville: "",
    quartier: "",
    profession: "",
    celibataire: "",
    situation_residence: "",
    orientation: "",
    loisirs: "",
    preference_negative: "",
});

const customInfoForm = ref({
    titre: "",
    contenu: "",
});

// Charger les informations du client
const loadClientInfo = async () => {
    try {
        const response = await axios.get(
            `/moderateur/clients/${props.clientId}/info`
        );
        basicInfo.value = response.data.basic_info;
        customInfos.value = response.data.custom_infos;

        // Pré-remplir le formulaire des infos de base
        if (basicInfo.value) {
            basicInfoForm.value = { ...basicInfo.value };
        }
    } catch (error) {
        console.error("Erreur lors du chargement des informations:", error);
    }
};

// Fonction pour afficher une notification
const showNotification = (message, type = "success") => {
    notification.value = { show: true, message, type };
    setTimeout(() => {
        notification.value.show = false;
    }, 3000);
};

// Mettre à jour les informations de base
const updateBasicInfo = async () => {
    if (isLoadingBasicInfo.value) return;

    isLoadingBasicInfo.value = true;
    try {
        const response = await axios.post(
            `/moderateur/clients/${props.clientId}/basic-info`,
            basicInfoForm.value
        );
        await loadClientInfo();
        showBasicInfoForm.value = false;
        showNotification(response.data.message);
    } catch (error) {
        console.error("Erreur lors de la mise à jour des informations:", error);
        showNotification(
            error.response?.data?.message ||
                "Erreur lors de la mise à jour des informations",
            "error"
        );
    } finally {
        isLoadingBasicInfo.value = false;
    }
};

// Ajouter une information personnalisée
const addCustomInfo = async () => {
    if (isLoadingCustomInfo.value) return;

    isLoadingCustomInfo.value = true;
    try {
        const response = await axios.post(
            `/moderateur/clients/${props.clientId}/custom-info`,
            customInfoForm.value
        );
        await loadClientInfo();
        customInfoForm.value = { titre: "", contenu: "" };
        showNotification(response.data.message);
    } catch (error) {
        console.error("Erreur lors de l'ajout de l'information:", error);
        showNotification(
            error.response?.data?.message ||
                "Erreur lors de l'ajout de l'information",
            "error"
        );
    } finally {
        isLoadingCustomInfo.value = false;
    }
};

// Supprimer une information personnalisée
const deleteInfo = async (infoId) => {
    if (isLoadingDelete.value) return;
    if (!confirm("Voulez-vous vraiment supprimer cette information ?")) return;

    isLoadingDelete.value = true;
    try {
        const response = await axios.delete(
            `/moderateur/custom-info/${infoId}`
        );
        await loadClientInfo();
        showNotification(response.data.message);
    } catch (error) {
        console.error("Erreur lors de la suppression de l'information:", error);
        showNotification(
            error.response?.data?.message ||
                "Erreur lors de la suppression de l'information",
            "error"
        );
    } finally {
        isLoadingDelete.value = false;
    }
};

// Formater la date
const formatDate = (date) => {
    return new Date(date).toLocaleDateString("fr-FR", {
        day: "numeric",
        month: "long",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

// Formater la situation de résidence
const formatSituationResidence = (situation) => {
    const situations = {
        seul: "Seul(e)",
        colocation: "En colocation",
        famille: "En famille",
        autre: "Autre",
    };
    return situations[situation] || situation;
};

// Charger les informations au montage et quand le client change
onMounted(loadClientInfo);
watch(() => props.clientId, loadClientInfo);
</script>

<style scoped>
/* Suppression des styles avec @apply et utilisation directe des classes dans le template */
</style>
