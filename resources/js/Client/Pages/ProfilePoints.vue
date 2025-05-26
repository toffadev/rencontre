<template>
    <MainLayout>
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- En-tête -->
            <div class="md:flex md:items-center md:justify-between mb-6">
                <div class="flex-1 min-w-0">
                    <h2
                        class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate"
                    >
                        Points pour {{ profile.name }}
                    </h2>
                </div>
                <div class="mt-4 flex md:mt-0 md:ml-4">
                    <router-link
                        :to="{ name: 'client.home' }"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                    >
                        <i class="fas fa-arrow-left mr-2"></i>
                        Retour
                    </router-link>
                </div>
            </div>

            <!-- Carte des points actuels -->
            <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3
                                class="text-lg leading-6 font-medium text-gray-900"
                            >
                                Points disponibles
                            </h3>
                            <div
                                class="mt-2 text-3xl font-semibold text-pink-600"
                            >
                                {{ remainingPoints }}
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-500">
                                Dernière mise à jour
                            </p>
                            <p class="text-sm font-medium text-gray-900">
                                {{ formatDate(new Date()) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Packs de points -->
            <div class="bg-white overflow-hidden shadow rounded-lg mb-6">
                <div class="px-4 py-5 sm:p-6">
                    <h3
                        class="text-lg leading-6 font-medium text-gray-900 mb-4"
                    >
                        Acheter des points
                    </h3>
                    <PurchasePoints
                        :profile-id="profile.id"
                        :stripe-key="stripeKey"
                        @points-purchased="handlePointsPurchased"
                    />
                </div>
            </div>

            <!-- Historique des transactions -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <TransactionHistory
                        :profile-id="profile.id"
                        :show-profile="false"
                    />
                </div>
            </div>
        </div>

        <!-- Notification de points -->
        <PointNotification
            v-if="auth.user"
            :user-id="auth.user.id"
            user-type="client"
        />
    </MainLayout>
</template>

<script setup>
import { ref, onMounted } from "vue";
import MainLayout from "@/Client/Layouts/MainLayout.vue";
import PurchasePoints from "@/Components/ProfilePoints/PurchasePoints.vue";
import TransactionHistory from "@/Components/ProfilePoints/TransactionHistory.vue";
import PointNotification from "@/Components/ProfilePoints/PointNotification.vue";
import axios from "axios";

const props = defineProps({
    profile: {
        type: Object,
        required: true,
    },
    auth: {
        type: Object,
        required: true,
    },
    stripeKey: {
        type: String,
        required: true,
    },
});

console.log("Stripe Key reçue du contrôleur:", props.stripeKey);

const remainingPoints = ref(0);

const formatDate = (date) => {
    return new Date(date).toLocaleDateString("fr-FR", {
        year: "numeric",
        month: "long",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

const loadPoints = async () => {
    try {
        const response = await axios.get("/points/data");
        remainingPoints.value = response.data.points;
    } catch (error) {
        console.error("Erreur lors du chargement des points:", error);
    }
};

const handlePointsPurchased = () => {
    loadPoints();
};

onMounted(() => {
    loadPoints();
});
</script>
