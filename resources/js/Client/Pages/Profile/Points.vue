<template>
    <MainLayout>
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <div class="px-4 py-6 sm:px-0">
                <div
                    class="border-4 border-dashed border-gray-200 rounded-lg p-4"
                >
                    <div class="mb-8">
                        <h2 class="text-2xl font-semibold text-gray-900">
                            Acheter des points pour {{ profile.name }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-600">
                            Les points achetés seront crédités au profil et vous
                            permettront d'interagir davantage.
                        </p>
                    </div>

                    <PurchasePoints
                        :profile-id="profile.id"
                        :stripe-key="stripeKey"
                    />

                    <div class="mt-8">
                        <h3 class="text-lg font-medium text-gray-900">
                            Historique des transactions
                        </h3>
                        <TransactionHistory :profile-id="profile.id" />
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<script setup>
import MainLayout from "@client/Layouts/MainLayout.vue";
import PurchasePoints from "@client/Components/ProfilePoints/PurchasePoints.vue";
import TransactionHistory from "@client/Components/ProfilePoints/TransactionHistory.vue";

defineProps({
    profile: {
        type: Object,
        required: true,
    },
    stripeKey: {
        type: String,
        required: true,
    },
});
</script>
