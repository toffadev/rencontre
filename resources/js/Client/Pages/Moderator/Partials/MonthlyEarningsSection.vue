<template>
    <div>
        <!-- Sélecteur d'année -->
        <div class="mb-6 flex justify-between items-center">
            <h3 class="text-lg font-medium text-gray-700">Revenus mensuels {{ selectedYear }}</h3>
            <div class="flex items-center space-x-2">
                <button 
                    @click="changeYear(selectedYear - 1)" 
                    class="p-2 rounded-lg bg-gray-100 hover:bg-gray-200"
                >
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span class="text-gray-700 font-medium">{{ selectedYear }}</span>
                <button 
                    @click="changeYear(selectedYear + 1)" 
                    :disabled="selectedYear >= currentYear"
                    class="p-2 rounded-lg bg-gray-100 hover:bg-gray-200 disabled:opacity-50"
                >
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>

        <!-- Tableau des revenus mensuels -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Mois
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Messages reçus
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Revenus messages
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Points reçus
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Revenus points (50%)
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Total
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Statut
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-if="loading" class="text-center">
                            <td colspan="7" class="px-6 py-4">
                                <div class="flex justify-center">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-pink-500"></div>
                                </div>
                            </td>
                        </tr>
                        <tr v-else-if="!monthlyData.months || monthlyData.months.length === 0" class="text-center">
                            <td colspan="7" class="px-6 py-4 text-gray-500">
                                Aucune donnée disponible pour cette année
                            </td>
                        </tr>
                        <template v-else>
                            <tr v-for="month in monthlyData.months" :key="month.name" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ month.name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ month.messages }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600">
                                    {{ formatCurrency(month.message_earnings) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ Math.round(month.points) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-purple-600">
                                    {{ formatCurrency(month.moderator_share) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                                    {{ formatCurrency(month.earnings) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="[
                                        'px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full',
                                        month.status === 'Payé' 
                                            ? 'bg-green-100 text-green-800' 
                                            : 'bg-yellow-100 text-yellow-800'
                                    ]">
                                        {{ month.status }}
                                    </span>
                                </td>
                            </tr>
                            <!-- Ligne de total -->
                            <tr class="bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    Total
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ monthlyData.total_messages }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-700">
                                    {{ formatCurrency(monthlyData.total_message_earnings) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ Math.round(monthlyData.total_points) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-purple-700">
                                    {{ formatCurrency(monthlyData.total_moderator_share) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-700">
                                    {{ formatCurrency(monthlyData.total_earnings) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Résumé des revenus -->
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg p-4 shadow-sm">
                <h4 class="text-sm font-medium text-gray-500 mb-2">Revenus des messages</h4>
                <p class="text-2xl font-bold text-blue-600">{{ formatCurrency(monthlyData.total_message_earnings || 0) }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ monthlyData.total_messages || 0 }} messages reçus</p>
            </div>
            <div class="bg-white rounded-lg p-4 shadow-sm">
                <h4 class="text-sm font-medium text-gray-500 mb-2">Revenus des points (50%)</h4>
                <p class="text-2xl font-bold text-purple-600">{{ formatCurrency(monthlyData.total_moderator_share || 0) }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ Math.round(monthlyData.total_points || 0) }} points reçus</p>
            </div>
            <div class="bg-white rounded-lg p-4 shadow-sm">
                <h4 class="text-sm font-medium text-gray-500 mb-2">Revenus totaux</h4>
                <p class="text-2xl font-bold text-green-600">{{ formatCurrency(monthlyData.total_earnings || 0) }}</p>
                <p class="text-xs text-gray-500 mt-1">Pour l'année {{ selectedYear }}</p>
            </div>
        </div>

        <!-- État de chargement -->
        <div v-if="loading" class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-pink-500"></div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import { formatCurrency } from '../../../utils/format';

export default {
    name: 'MonthlyEarningsSection',
    props: {
        selectedProfileId: {
            type: [Number, String],
            default: null
        }
    },
    setup(props) {
        const currentYear = new Date().getFullYear();
        const selectedYear = ref(currentYear);
        const monthlyData = ref({});
        const loading = ref(false);

        const fetchMonthlyEarnings = async () => {
            try {
                loading.value = true;
                const response = await axios.get('/moderateur/profile/monthly-earnings', {
                    params: {
                        year: selectedYear.value,
                        profileId: props.selectedProfileId
                    }
                });
                monthlyData.value = response.data;
            } catch (error) {
                console.error('Erreur lors de la récupération des revenus mensuels:', error);
            } finally {
                loading.value = false;
            }
        };

        const changeYear = (year) => {
            if (year <= currentYear) {
                selectedYear.value = year;
            }
        };

        // Surveiller les changements d'année et de profil
        watch([selectedYear, () => props.selectedProfileId], () => {
            fetchMonthlyEarnings();
        });

        // Charger les données au montage du composant
        onMounted(() => {
            fetchMonthlyEarnings();
        });

        return {
            currentYear,
            selectedYear,
            monthlyData,
            loading,
            changeYear,
            formatCurrency
        };
    }
};
</script>