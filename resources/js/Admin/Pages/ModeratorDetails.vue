<template>
    <AdminLayout title="Détails du modérateur">
        <div class="container mx-auto px-4 py-8">
            <!-- En-tête avec informations du modérateur -->
            <div class="bg-white rounded-lg shadow mb-6 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div
                            class="h-16 w-16 rounded-full bg-gray-300 flex items-center justify-center text-gray-500 text-2xl">
                            {{ moderator?.name?.charAt(0).toUpperCase() }}
                        </div>
                        <div class="ml-4">
                            <h1 class="text-2xl font-bold">{{ moderator?.name }}</h1>
                            <p class="text-gray-600">{{ moderator?.email }}</p>
                            <p class="text-sm text-gray-500">Inscrit depuis: {{ moderator?.created_at }}</p>
                        </div>
                    </div>
                    <div>
                        <Link href="/admin/moderator-performance"
                            class="px-4 py-2 bg-gray-200 rounded-md text-gray-700 hover:bg-gray-300">
                        Retour à la liste
                        </Link>
                    </div>
                </div>

                <!-- Profils gérés par le modérateur -->
                <div class="mt-4">
                    <h3 class="text-lg font-medium mb-2">Profils gérés</h3>
                    <div class="flex flex-wrap gap-2">
                        <div v-for="profile in moderator?.profiles" :key="profile.id"
                            class="flex items-center bg-gray-100 rounded-lg p-2">
                            <img v-if="profile.photo" :src="profile.photo" class="h-8 w-8 rounded-full mr-2"
                                alt="Profile photo" />
                            <div v-else
                                class="h-8 w-8 rounded-full bg-gray-300 flex items-center justify-center text-gray-500">
                                {{ profile.name.charAt(0).toUpperCase() }}
                            </div>
                            <span class="text-sm">{{ profile.name }}</span>
                            <span v-if="profile.is_primary"
                                class="ml-1 text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full">Principal</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <div class="bg-white rounded-lg shadow mb-6 p-6">
                <div class="flex flex-wrap items-center gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Période</label>
                        <select v-model="filters.period"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            <option value="month">Mois en cours</option>
                            <option value="year">Année en cours</option>
                            <option value="custom">Période personnalisée</option>
                        </select>
                    </div>
                    <div v-if="filters.period === 'custom'" class="flex items-center gap-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Du</label>
                            <input type="date" v-model="filters.start_date"
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Au</label>
                            <input type="date" v-model="filters.end_date"
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Profil</label>
                        <select v-model="filters.profile_id"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            <option :value="null">Tous les profils</option>
                            <option v-for="profile in moderator?.profiles" :key="profile.id" :value="profile.id">{{
                                profile.name }}</option>
                        </select>
                    </div>
                    <div class="ml-auto">
                        <button @click="fetchData"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Appliquer
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistiques globales -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm font-medium text-gray-500 mb-1">Messages envoyés</div>
                    <div class="text-2xl font-bold">{{ totalStats.messages_sent }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm font-medium text-gray-500 mb-1">Messages reçus</div>
                    <div class="text-2xl font-bold">{{ totalStats.messages_received }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm font-medium text-gray-500 mb-1">Points des profils</div>
                    <div class="text-2xl font-bold">{{ totalStats.profile_points }}</div>
                    <div class="text-xs text-gray-500">Part modérateur: {{ totalStats.moderator_share }}</div>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="text-sm font-medium text-gray-500 mb-1">Revenus totaux</div>
                    <div class="text-2xl font-bold">{{ formatCurrency(totalStats.total_earnings) }}</div>
                </div>
            </div>

            <!-- Onglets -->
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="border-b border-gray-200">
                    <nav class="flex -mb-px">
                        <button @click="activeTab = 'monthly'" :class="[
                            'py-4 px-6 font-medium text-sm focus:outline-none',
                            activeTab === 'monthly'
                                ? 'border-b-2 border-indigo-500 text-indigo-600'
                                : 'text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        ]">
                            Statistiques mensuelles
                        </button>
                        <button @click="activeTab = 'messages'" :class="[
                            'py-4 px-6 font-medium text-sm focus:outline-none',
                            activeTab === 'messages'
                                ? 'border-b-2 border-indigo-500 text-indigo-600'
                                : 'text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        ]">
                            Messages
                        </button>
                        <button @click="activeTab = 'payments'" :class="[
                            'py-4 px-6 font-medium text-sm focus:outline-none',
                            activeTab === 'payments'
                                ? 'border-b-2 border-indigo-500 text-indigo-600'
                                : 'text-gray-500 hover:text-gray-700 hover:border-gray-300'
                        ]">
                            Paiements
                        </button>
                    </nav>
                </div>

                <!-- Contenu des onglets -->
                <div class="p-6">
                    <!-- Statistiques mensuelles -->
                    <div v-if="activeTab === 'monthly'">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Mois</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Messages envoyés</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Messages reçus</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Points</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Part modérateur</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Revenus messages</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Revenus totaux</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Statut</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="stat in monthlyStats" :key="stat.month" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{
                                        stat.month }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ stat.messages_sent
                                        }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{
                                        stat.messages_received }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ stat.profile_points
                                        }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{
                                        stat.moderator_share }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{
                                        formatCurrency(stat.message_earnings) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{
                                        formatCurrency(stat.total_earnings) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="{
                                            'px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full': true,
                                            'bg-green-100 text-green-800': stat.payment_status === 'Payé',
                                            'bg-yellow-100 text-yellow-800': stat.payment_status === 'En attente'
                                        }">
                                            {{ stat.payment_status }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="monthlyStats.length === 0">
                                    <td colspan="8"
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">Aucune
                                        donnée disponible</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Messages -->
                    <div v-if="activeTab === 'messages'">
                        <div class="mb-4 flex flex-wrap items-center gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Type de message</label>
                                <select v-model="messageFilters.message_type"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <option value="all">Tous les messages</option>
                                    <option value="received">Messages reçus</option>
                                    <option value="sent">Messages envoyés</option>
                                </select>
                            </div>
                            <div class="ml-auto">
                                <button @click="fetchMessages"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Appliquer
                                </button>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div v-for="message in messages" :key="message.id" class="bg-gray-50 p-4 rounded-lg">
                                <div class="flex justify-between items-start mb-2">
                                    <div class="flex items-center">
                                        <img v-if="message.profile.photo" :src="message.profile.photo"
                                            class="h-8 w-8 rounded-full mr-2" alt="Profile photo" />
                                        <div v-else
                                            class="h-8 w-8 rounded-full bg-gray-300 flex items-center justify-center text-gray-500">
                                            {{ message.profile.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="ml-2">
                                            <div class="text-sm font-medium text-gray-900">{{ message.profile.name }}
                                            </div>
                                            <div class="text-xs text-gray-500">{{ message.created_at }}</div>
                                        </div>
                                    </div>
                                    <div>
                                        <span :class="{
                                            'px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full': true,
                                            'bg-green-100 text-green-800': message.is_from_client,
                                            'bg-blue-100 text-blue-800': !message.is_from_client
                                        }">
                                            {{ message.is_from_client ? 'Reçu' : 'Envoyé' }}
                                        </span>
                                        <span class="ml-1 text-xs text-gray-500">
                                            {{ message.is_from_client ? '50' : '0' }} points
                                        </span>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-700 bg-white p-3 rounded border border-gray-200">
                                    {{ message.content }}
                                </div>
                                <div class="mt-2 text-xs text-gray-500">
                                    De: {{ message.is_from_client ? message.client.name : message.moderator.name }} à {{
                                        message.is_from_client ? 'Profil' : message.client.name }}
                                </div>
                            </div>
                            <div v-if="messages.length === 0" class="text-center py-4 text-gray-500">
                                Aucun message trouvé
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-4 flex justify-between items-center">
                            <div class="text-sm text-gray-700">
                                Affichage de {{ (messagePagination.current_page - 1) * messagePagination.per_page + 1 }}
                                à
                                {{ Math.min(messagePagination.current_page * messagePagination.per_page,
                                messagePagination.total) }}
                                sur {{ messagePagination.total }} messages
                            </div>
                            <div class="flex-1 flex justify-end">
                                <button @click="changeMessagePage(messagePagination.current_page - 1)"
                                    :disabled="messagePagination.current_page === 1"
                                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                    Précédent
                                </button>
                                <button @click="changeMessagePage(messagePagination.current_page + 1)"
                                    :disabled="messagePagination.current_page === messagePagination.last_page"
                                    class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                    Suivant
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Paiements -->
                    <div v-if="activeTab === 'payments'">
                        <div class="mb-4 flex items-center">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Année</label>
                                <select v-model="paymentFilters.year"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <option v-for="year in availableYears" :key="year" :value="year">{{ year }}</option>
                                </select>
                            </div>
                            <div class="ml-4">
                                <button @click="fetchPayments"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Appliquer
                                </button>
                            </div>
                        </div>

                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Mois</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Messages reçus</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Revenus messages</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Points</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Part modérateur</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Revenus totaux</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Statut</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="month in months" :key="month.month_value" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{
                                        month.month }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ month.messages }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{
                                        formatCurrency(month.message_earnings) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ month.points }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{
                                        month.moderator_share }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{
                                        formatCurrency(month.earnings) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="{
                                            'px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full': true,
                                            'bg-green-100 text-green-800': month.status === 'Payé',
                                            'bg-yellow-100 text-yellow-800': month.status === 'En attente'
                                        }">
                                            {{ month.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button v-if="month.status === 'En attente'"
                                            @click="updatePaymentStatus(month.month_value, 'Payé')"
                                            class="text-indigo-600 hover:text-indigo-900">
                                            Marquer comme payé
                                        </button>
                                        <button v-else @click="updatePaymentStatus(month.month_value, 'En attente')"
                                            class="text-yellow-600 hover:text-yellow-900">
                                            Marquer comme en attente
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="months.length === 0">
                                    <td colspan="8"
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">Aucune
                                        donnée disponible</td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Total</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{
                                        totalPayments.messages }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{
                                        formatCurrency(totalPayments.message_earnings) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{
                                        totalPayments.points }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{
                                        totalPayments.moderator_share }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{
                                        formatCurrency(totalPayments.total_earnings) }}</td>
                                    <td colspan="2"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script>
import { ref, reactive, onMounted, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import axios from 'axios';

export default {
    components: {
        AdminLayout,
        Link
    },
    props: {
        moderatorId: {
            type: Number,
            required: true
        },
        initialModerator: Object
    },
    setup(props) {
        const moderator = ref(props.initialModerator || null);
        const activeTab = ref('monthly');
        const loading = ref(false);
        const monthlyStats = ref([]);
        const totalStats = ref({
            messages_sent: 0,
            messages_received: 0,
            profile_points: 0,
            moderator_share: 0,
            message_earnings: 0,
            total_earnings: 0
        });

        const filters = reactive({
            period: 'month',
            start_date: null,
            end_date: null,
            profile_id: null
        });

        const messageFilters = reactive({
            period: 'month',
            message_type: 'all',
            profile_id: null,
            page: 1
        });

        const paymentFilters = reactive({
            year: new Date().getFullYear()
        });

        const messages = ref([]);
        const messagePagination = ref({
            current_page: 1,
            last_page: 1,
            per_page: 10,
            total: 0
        });

        const months = ref([]);
        const availableYears = ref([]);
        const totalPayments = ref({
            messages: 0,
            message_earnings: 0,
            points: 0,
            moderator_share: 0,
            total_earnings: 0
        });

        // Méthodes pour récupérer les données
        // Dans fetchData()
        const fetchData = async () => {
            loading.value = true;
            try {
                // Vérifier que props.moderatorId est défini
                if (!props.moderatorId) {
                    console.error('ID du modérateur non défini');
                    return;
                }

                const response = await axios.get(`/admin/moderator-performance/moderator/${props.moderatorId}/details`, {
                    params: filters
                });

                moderator.value = response.data.moderator;
                monthlyStats.value = response.data.monthly_stats;
                totalStats.value = response.data.total_stats;
            } catch (error) {
                console.error('Erreur lors de la récupération des données:', error);
            } finally {
                loading.value = false;
            }
        };

        // Dans fetchMessages()
        const fetchMessages = async () => {
            loading.value = true;
            try {
                if (!props.moderatorId) {
                    console.error('ID du modérateur non défini');
                    return;
                }

                const response = await axios.get(`/admin/moderator-performance/moderator/${props.moderatorId}/messages`, {
                    params: {
                        ...filters,
                        ...messageFilters
                    }
                });

                messages.value = response.data.messages;
                messagePagination.value = response.data.pagination;
            } catch (error) {
                console.error('Erreur lors de la récupération des messages:', error);
            } finally {
                loading.value = false;
            }
        };

        const fetchPayments = async () => {
            loading.value = true;
            try {
                if (!props.moderatorId) {
                    console.error('ID du modérateur non défini');
                    return;
                }

                const response = await axios.get(`/admin/moderator-performance/moderator/${props.moderatorId}/payments`, {
                    params: paymentFilters
                });

                months.value = response.data.months;
                availableYears.value = response.data.available_years;
                totalPayments.value = {
                    messages: response.data.months.reduce((sum, month) => sum + month.messages, 0),
                    message_earnings: response.data.total_message_earnings,
                    points: response.data.total_points,
                    moderator_share: response.data.total_moderator_share,
                    total_earnings: response.data.total_earnings
                };
            } catch (error) {
                console.error('Erreur lors de la récupération des paiements:', error);
            } finally {
                loading.value = false;
            }
        };

        const changeMessagePage = (page) => {
            if (page < 1 || page > messagePagination.value.last_page) return;
            messageFilters.page = page;
            fetchMessages();
        };

        const updatePaymentStatus = async (month, status) => {
            try {
                const response = await axios.post(`/admin/moderator-performance/moderator/${props.moderatorId}/payment-status`, {
                    month,
                    status
                });

                if (response.data.success) {
                    // Mettre à jour le statut dans le tableau
                    const index = months.value.findIndex(m => m.month_value === month);
                    if (index !== -1) {
                        months.value[index].status = status;
                    }
                }
            } catch (error) {
                console.error('Erreur lors de la mise à jour du statut de paiement:', error);
            }
        };

        const formatCurrency = (value) => {
            return new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'EUR'
            }).format(value);
        };

        // Charger les données au montage du composant
        onMounted(() => {
            fetchData();
            fetchMessages();
            fetchPayments();
        });

        // Observer les changements d'onglet
        const onTabChange = (tab) => {
            if (tab === 'messages' && messages.value.length === 0) {
                fetchMessages();
            } else if (tab === 'payments' && months.value.length === 0) {
                fetchPayments();
            }
        };

        return {
            moderator,
            activeTab,
            loading,
            filters,
            messageFilters,
            paymentFilters,
            monthlyStats,
            totalStats,
            messages,
            messagePagination,
            months,
            availableYears,
            totalPayments,
            fetchData,
            fetchMessages,
            fetchPayments,
            changeMessagePage,
            updatePaymentStatus,
            formatCurrency,
            onTabChange
        };
    },
    watch: {
        activeTab(newTab) {
            this.onTabChange(newTab);
        }
    }
};
</script>