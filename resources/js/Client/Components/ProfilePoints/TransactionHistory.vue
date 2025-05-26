<template>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th
                        scope="col"
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                        Date
                    </th>
                    <th
                        scope="col"
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                        Points
                    </th>
                    <th
                        scope="col"
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                        Montant
                    </th>
                    <th
                        scope="col"
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                    >
                        Statut
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <tr v-if="loading">
                    <td
                        colspan="4"
                        class="px-6 py-4 text-center text-sm text-gray-500"
                    >
                        Chargement...
                    </td>
                </tr>
                <tr v-else-if="error">
                    <td
                        colspan="4"
                        class="px-6 py-4 text-center text-sm text-red-500"
                    >
                        {{ error }}
                    </td>
                </tr>
                <tr v-else-if="transactions.length === 0">
                    <td
                        colspan="4"
                        class="px-6 py-4 text-center text-sm text-gray-500"
                    >
                        Aucune transaction trouvée
                    </td>
                </tr>
                <tr
                    v-for="transaction in transactions"
                    :key="transaction.id"
                    class="hover:bg-gray-50"
                >
                    <td
                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                    >
                        {{ formatDate(transaction.created_at) }}
                    </td>
                    <td
                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                    >
                        {{ transaction.points_amount }} points
                    </td>
                    <td
                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                    >
                        {{ formatPrice(transaction.money_amount) }} €
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span :class="getStatusClass(transaction.status)">
                            {{ formatStatus(transaction.status) }}
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";

const props = defineProps({
    profileId: {
        type: Number,
        required: true,
    },
});

const transactions = ref([]);
const loading = ref(true);
const error = ref(null);

const formatDate = (date) => {
    return new Date(date).toLocaleDateString("fr-FR", {
        year: "numeric",
        month: "long",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
};

const formatPrice = (price) => {
    return Number(price).toFixed(2);
};

const formatStatus = (status) => {
    const statusMap = {
        pending: "En attente",
        completed: "Complété",
        failed: "Échoué",
    };
    return statusMap[status] || status;
};

const getStatusClass = (status) => {
    const baseClasses =
        "px-2 inline-flex text-xs leading-5 font-semibold rounded-full";
    const statusClasses = {
        pending: "bg-yellow-100 text-yellow-800",
        completed: "bg-green-100 text-green-800",
        failed: "bg-red-100 text-red-800",
    };
    return `${baseClasses} ${
        statusClasses[status] || "bg-gray-100 text-gray-800"
    }`;
};

const loadTransactions = async () => {
    try {
        loading.value = true;
        const response = await axios.get(
            route("profile.points.transactions.profile", {
                profile: props.profileId,
            })
        );
        transactions.value = response.data.transactions;
    } catch (e) {
        error.value = "Erreur lors du chargement des transactions";
        console.error("Erreur:", e);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadTransactions();
});
</script>
