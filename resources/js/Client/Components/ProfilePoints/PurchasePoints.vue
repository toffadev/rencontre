<template>
    <div class="w-full p-4">
        <div
            v-if="error"
            class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded"
        >
            {{ error }}
        </div>

        <div class="points-packs grid grid-cols-1 md:grid-cols-3 gap-4">
            <div
                v-for="(pack, key) in pointsPacks"
                :key="key"
                class="pack-card p-4 border rounded-lg shadow-sm hover:shadow-md transition-shadow bg-white"
            >
                <h3 class="text-xl font-semibold mb-2">
                    {{ pack.points }} Points
                </h3>
                <p class="text-gray-600 mb-4">{{ pack.description }}</p>
                <div class="price text-2xl font-bold text-pink-600 mb-4">
                    {{ formatPrice(pack.price) }} €
                </div>
                <button
                    @click="purchasePoints(key)"
                    class="w-full bg-pink-600 text-white py-2 px-4 rounded hover:bg-pink-700 transition-colors"
                    :disabled="loading"
                >
                    <span
                        v-if="loading"
                        class="flex items-center justify-center"
                    >
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
                        Chargement...
                    </span>
                    <span v-else>Acheter</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from "vue";
import axios from "axios";

const props = defineProps({
    profileId: {
        type: Number,
        required: true,
    },
    stripeKey: {
        type: String,
        required: true,
    },
});

const loading = ref(false);
const error = ref(null);

const pointsPacks = {
    100: {
        points: 100,
        price: 2.99,
        description: "Pack de démarrage",
    },
    500: {
        points: 500,
        price: 9.99,
        description: "Pack populaire",
    },
    1000: {
        points: 1000,
        price: 16.99,
        description: "Pack premium",
    },
};

const formatPrice = (price) => {
    return price.toFixed(2);
};

const purchasePoints = async (packKey) => {
    if (!window.Stripe) {
        error.value = "Stripe n'est pas initialisé";
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const stripe = window.Stripe(props.stripeKey);

        const response = await axios.post(route("profile.points.checkout"), {
            profile_id: props.profileId,
            pack: packKey,
        });

        const { sessionId } = response.data;

        const result = await stripe.redirectToCheckout({
            sessionId,
        });

        if (result.error) {
            throw new Error(result.error.message);
        }
    } catch (e) {
        error.value =
            e.response?.data?.error ||
            "Une erreur est survenue lors de la transaction";
        console.error("Erreur lors de l'achat:", e);
    } finally {
        loading.value = false;
    }
};
</script>
