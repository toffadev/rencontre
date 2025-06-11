<template>
    <div>
        <!-- En-tête avec statistiques rapides -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-lg p-4 text-center">
                <p class="text-sm text-gray-500">Total Messages</p>
                <p class="text-2xl font-bold text-gray-700">
                    {{ totalMessages }}
                </p>
            </div>
            <div class="bg-white rounded-lg p-4 text-center border-2 border-blue-200">
                <p class="text-sm text-gray-500">Messages Reçus</p>
                <p class="text-2xl font-bold text-blue-500">
                    {{ receivedMessages }}
                </p>
                <p class="text-xs text-blue-600 mt-1">
                    {{ formatCurrency(newEarnings) }} (50 pts/msg)
                </p>
            </div>
            <div class="bg-white rounded-lg p-4 text-center">
                <p class="text-sm text-gray-500">Messages Envoyés</p>
                <p class="text-2xl font-bold text-pink-500">
                    {{ sentMessages }}
                </p>
            </div>
            <div class="bg-white rounded-lg p-4 text-center border-2 border-purple-200">
                <p class="text-sm text-gray-500">Points Reçus (50%)</p>
                <p class="text-2xl font-bold text-purple-500">
                    {{ formatCurrency(moderatorShare) }}
                </p>
            </div>
            <div class="bg-white rounded-lg p-4 text-center">
                <p class="text-sm text-gray-500">Gains Totaux</p>
                <p class="text-2xl font-bold text-green-500">
                    {{ formatCurrency(totalEarnings) }}
                </p>
            </div>
        </div>

        <!-- Filtre de type de message -->
        <div class="mb-6 flex flex-wrap gap-2">
            <button
                @click="changeMessageType('all')"
                :class="[
                    'px-4 py-2 rounded-lg text-sm font-medium',
                    messageType === 'all'
                        ? 'bg-gray-700 text-white'
                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200',
                ]"
            >
                Tous les messages
            </button>
            <button
                @click="changeMessageType('received')"
                :class="[
                    'px-4 py-2 rounded-lg text-sm font-medium',
                    messageType === 'received'
                        ? 'bg-blue-500 text-white'
                        : 'bg-blue-100 text-blue-600 hover:bg-blue-200',
                ]"
            >
                Messages reçus
            </button>
            <button
                @click="changeMessageType('sent')"
                :class="[
                    'px-4 py-2 rounded-lg text-sm font-medium',
                    messageType === 'sent'
                        ? 'bg-pink-500 text-white'
                        : 'bg-pink-100 text-pink-600 hover:bg-pink-200',
                ]"
            >
                Messages envoyés
            </button>
        </div>

        <!-- Tableau des messages -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                            >
                                Profil
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                            >
                                Message
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                            >
                                Client
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                            >
                                Type
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                            >
                                Gains
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                            >
                                Date
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-if="loading" class="text-center">
                            <td colspan="6" class="px-6 py-4">
                                <div class="flex justify-center">
                                    <div
                                        class="animate-spin rounded-full h-8 w-8 border-b-2 border-pink-500"
                                    ></div>
                                </div>
                            </td>
                        </tr>
                        <tr
                            v-else-if="messages.data.length === 0"
                            class="text-center"
                        >
                            <td colspan="6" class="px-6 py-4 text-gray-500">
                                Aucun message trouvé pour cette période
                            </td>
                        </tr>
                        <tr
                            v-else
                            v-for="message in messages.data"
                            :key="message.id"
                            :class="[
                                'hover:bg-gray-50',
                                message.is_from_client ? 'bg-blue-50' : ''
                            ]"
                        >
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <img
                                            :src="message.profile.photo"
                                            :alt="message.profile.name"
                                            class="h-10 w-10 rounded-full object-cover"
                                        />
                                    </div>
                                    <div class="ml-4">
                                        <div
                                            class="text-sm font-medium text-gray-900"
                                        >
                                            {{ message.profile.name }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div
                                    class="text-sm text-gray-900 max-w-md truncate"
                                >
                                    {{ message.content }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ message.length }} caractères
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    {{ message.client.name }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    :class="[
                                        'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                                        message.is_from_client
                                            ? 'bg-blue-100 text-blue-800'
                                            : 'bg-gray-100 text-gray-800'
                                    ]"
                                >
                                    {{ message.is_from_client ? "Reçu" : "Envoyé" }}
                                </span>
                            </td>
                            <td
                                class="px-6 py-4 whitespace-nowrap text-sm"
                                :class="message.is_from_client ? 'text-blue-600 font-medium' : 'text-gray-500'"
                            >
                                {{ formatCurrency(message.earnings) }}
                            </td>
                            <td
                                class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                            >
                                {{ formatDate(message.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                <div class="flex items-center justify-between">
                    <div class="flex-1 flex justify-between sm:hidden">
                        <button
                            @click="
                                changePage(messages.pagination.current_page - 1)
                            "
                            :disabled="messages.pagination.current_page === 1"
                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                        >
                            Précédent
                        </button>
                        <button
                            @click="
                                changePage(messages.pagination.current_page + 1)
                            "
                            :disabled="
                                messages.pagination.current_page ===
                                messages.pagination.last_page
                            "
                            class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                        >
                            Suivant
                        </button>
                    </div>
                    <div
                        class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between"
                    >
                        <div>
                            <p class="text-sm text-gray-700">
                                Affichage de
                                <span class="font-medium">{{
                                    (messages.pagination.current_page - 1) *
                                        messages.pagination.per_page +
                                    1
                                }}</span>
                                à
                                <span class="font-medium">{{
                                    Math.min(
                                        messages.pagination.current_page *
                                            messages.pagination.per_page,
                                        messages.pagination.total
                                    )
                                }}</span>
                                sur
                                <span class="font-medium">{{
                                    messages.pagination.total
                                }}</span>
                                résultats
                            </p>
                        </div>
                        <div>
                            <nav
                                class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px"
                            >
                                <button
                                    v-for="page in pageNumbers"
                                    :key="page"
                                    @click="changePage(page)"
                                    :class="[
                                        'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                                        page ===
                                        messages.pagination.current_page
                                            ? 'z-10 bg-pink-50 border-pink-500 text-pink-600'
                                            : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50',
                                    ]"
                                >
                                    {{ page }}
                                </button>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- État de chargement -->
        <div
            v-if="loading"
            class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center"
        >
            <div
                class="animate-spin rounded-full h-12 w-12 border-b-2 border-pink-500"
            ></div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted, watch, computed } from "vue";
import axios from "axios";
import { formatCurrency, formatDate } from "../../../utils/format";

export default {
    name: "MessageHistorySection",
    props: {
        selectedProfileId: {
            type: [Number, String],
            default: null,
        },
        selectedDateRange: {
            type: String,
            default: "week",
        },
    },
    setup(props) {
        const messages = ref({
            data: [],
            pagination: {
                current_page: 1,
                per_page: 50,
                total: 0,
                last_page: 1,
            },
        });
        const totalMessages = ref(0);
        const receivedMessages = ref(0);
        const sentMessages = ref(0);
        const shortMessages = ref(0);
        const longMessages = ref(0);
        const oldEarnings = ref(0);
        const newEarnings = ref(0);
        const pointsAmount = ref(0);
        const moderatorShare = ref(0);
        const totalEarnings = ref(0);
        const currentPage = ref(1);
        const loading = ref(false);
        const messageType = ref('all'); // 'all', 'received', ou 'sent'

        const fetchMessages = async () => {
            try {
                loading.value = true;
                const response = await axios.get(
                    "/moderateur/profile/messages",
                    {
                        params: {
                            dateRange: props.selectedDateRange,
                            profileId: props.selectedProfileId,
                            limit: messages.value.pagination.per_page,
                            page: currentPage.value,
                            messageType: messageType.value
                        },
                    }
                );

                messages.value.data = response.data.messages;
                messages.value.pagination = response.data.pagination;
                totalMessages.value = response.data.statistics.total_messages;
                receivedMessages.value = response.data.statistics.received_messages;
                sentMessages.value = response.data.statistics.sent_messages;
                shortMessages.value = response.data.statistics.short_messages;
                longMessages.value = response.data.statistics.long_messages;
                oldEarnings.value = response.data.statistics.old_earnings;
                newEarnings.value = response.data.statistics.new_earnings;
                pointsAmount.value = response.data.statistics.points_amount;
                moderatorShare.value = response.data.statistics.moderator_share;
                totalEarnings.value = response.data.statistics.total_earnings;
            } catch (error) {
                console.error(
                    "Erreur lors de la récupération des messages:",
                    error
                );
            } finally {
                loading.value = false;
            }
        };

        const changePage = (page) => {
            if (page >= 1 && page <= messages.value.pagination.last_page) {
                currentPage.value = page;
            }
        };

        const changeMessageType = (type) => {
            messageType.value = type;
            currentPage.value = 1; // Réinitialiser la page
            fetchMessages();
        };

        // Surveiller les changements de page
        watch(currentPage, () => {
            fetchMessages();
        });

        // Surveiller les changements de profil et de période
        watch(
            [() => props.selectedProfileId, () => props.selectedDateRange],
            () => {
                currentPage.value = 1; // Réinitialiser la page
                fetchMessages();
            }
        );

        // Charger les données au montage du composant
        onMounted(() => {
            fetchMessages();
        });

        const pageNumbers = computed(() => {
            const pages = [];
            for (let i = 1; i <= messages.value.pagination.last_page; i++) {
                pages.push(i);
            }
            return pages;
        });

        return {
            messages,
            totalMessages,
            receivedMessages,
            sentMessages,
            shortMessages,
            longMessages,
            oldEarnings,
            newEarnings,
            pointsAmount,
            moderatorShare,
            totalEarnings,
            currentPage,
            messageType,
            pageNumbers,
            changePage,
            changeMessageType,
            formatCurrency,
            formatDate,
            loading,
        };
    },
};
</script>
