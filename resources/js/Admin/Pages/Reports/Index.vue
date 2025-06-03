<template>
    <AdminLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-semibold text-gray-900">Signalements de profils</h1>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <select 
                                v-model="filters.status"
                                class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm rounded-md"
                            >
                                <option value="">Tous les statuts</option>
                                <option value="pending">En attente</option>
                                <option value="accepted">Accepté</option>
                                <option value="dismissed">Rejeté</option>
                            </select>
                        </div>
                        <div class="relative">
                            <input 
                                type="text"
                                v-model="searchQuery"
                                placeholder="Rechercher..."
                                class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:ring-primary focus:border-primary sm:text-sm"
                            >
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Table des signalements -->
                <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                    <div class="min-w-full overflow-x-auto">
                        <table class="w-full whitespace-nowrap">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">
                                        Date
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[200px]">
                                        Signalé par
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[200px]">
                                        Profil signalé
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[200px]">
                                        Modérateur
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[200px]">
                                        Raison
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[120px]">
                                        Statut
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[150px]">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="report in filteredReports" :key="report.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ formatDate(report.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <img 
                                                    :src="report.reporter.profile_photo_url || 'https://via.placeholder.com/40'" 
                                                    :alt="report.reporter.name"
                                                    class="h-10 w-10 rounded-full"
                                                >
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ report.reporter.name }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <img 
                                                    :src="report.reported_profile.main_photo_path || 'https://via.placeholder.com/40'" 
                                                    :alt="report.reported_profile.name"
                                                    class="h-10 w-10 rounded-full object-cover"
                                                >
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ report.reported_profile.name }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div v-if="report.reported_user" class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <img 
                                                    :src="report.reported_user.profile_photo_url || 'https://via.placeholder.com/40'" 
                                                    :alt="report.reported_user.name"
                                                    class="h-10 w-10 rounded-full"
                                                >
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ report.reported_user.name }}
                                                </div>
                                            </div>
                                        </div>
                                        <span v-else class="text-sm text-gray-500">Non assigné</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">{{ report.reason }}</div>
                                        <div v-if="report.description" class="text-sm text-gray-500">
                                            {{ report.description }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span :class="[
                                            'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                                            {
                                                'bg-yellow-100 text-yellow-800': report.status === 'pending',
                                                'bg-green-100 text-green-800': report.status === 'accepted',
                                                'bg-red-100 text-red-800': report.status === 'dismissed'
                                            }
                                        ]">
                                            {{ formatStatus(report.status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-3">
                                            <button 
                                                v-if="report.status === 'pending'"
                                                @click="acceptReport(report)"
                                                class="text-green-600 hover:text-green-900"
                                                title="Accepter le signalement"
                                            >
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button 
                                                v-if="report.status === 'pending'"
                                                @click="dismissReport(report)"
                                                class="text-red-600 hover:text-red-900"
                                                title="Rejeter le signalement"
                                            >
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <button
                                                @click="showDetails(report)"
                                                class="text-primary hover:text-primary-dark"
                                                title="Voir les détails"
                                            >
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="mt-4">
                    <Pagination 
                        :links="reports.links" 
                        :meta="reports.meta"
                    />
                </div>
            </div>
        </div>

        <!-- Modal de détails -->
        <Modal :show="showModal" @close="closeModal">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    Détails du signalement
                </h3>
                <div v-if="selectedReport" class="space-y-4">
                    <div>
                        <h4 class="text-sm font-medium text-gray-500">Profil signalé</h4>
                        <div class="mt-1 flex items-center">
                            <img 
                                :src="selectedReport.reported_profile.main_photo_path || 'https://via.placeholder.com/64'" 
                                :alt="selectedReport.reported_profile.name"
                                class="h-16 w-16 rounded-full object-cover"
                            >
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-900">
                                    {{ selectedReport.reported_profile.name }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    {{ selectedReport.reported_profile.gender === 'male' ? 'Homme' : 'Femme' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-sm font-medium text-gray-500">Signalé par</h4>
                        <div class="mt-1 flex items-center">
                            <img 
                                :src="selectedReport.reporter.profile_photo_url || 'https://via.placeholder.com/64'" 
                                :alt="selectedReport.reporter.name"
                                class="h-16 w-16 rounded-full"
                            >
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-900">
                                    {{ selectedReport.reporter.name }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    {{ selectedReport.reporter.email }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedReport.reported_user">
                        <h4 class="text-sm font-medium text-gray-500">Modérateur assigné</h4>
                        <div class="mt-1 flex items-center">
                            <img 
                                :src="selectedReport.reported_user.profile_photo_url || 'https://via.placeholder.com/64'" 
                                :alt="selectedReport.reported_user.name"
                                class="h-16 w-16 rounded-full"
                            >
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-900">
                                    {{ selectedReport.reported_user.name }}
                                </p>
                                <p class="text-sm text-gray-500">
                                    {{ selectedReport.reported_user.email }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-sm font-medium text-gray-500">Raison du signalement</h4>
                        <p class="mt-1 text-sm text-gray-900">{{ selectedReport.reason }}</p>
                    </div>

                    <div v-if="selectedReport.description">
                        <h4 class="text-sm font-medium text-gray-500">Description</h4>
                        <p class="mt-1 text-sm text-gray-900">{{ selectedReport.description }}</p>
                    </div>

                    <div>
                        <h4 class="text-sm font-medium text-gray-500">Date du signalement</h4>
                        <p class="mt-1 text-sm text-gray-900">{{ formatDate(selectedReport.created_at) }}</p>
                    </div>

                    <div v-if="selectedReport.reviewed_at">
                        <h4 class="text-sm font-medium text-gray-500">Date de révision</h4>
                        <p class="mt-1 text-sm text-gray-900">{{ formatDate(selectedReport.reviewed_at) }}</p>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button
                            v-if="selectedReport.status === 'pending'"
                            @click="acceptReport(selectedReport)"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                        >
                            Accepter
                        </button>
                        <button
                            v-if="selectedReport.status === 'pending'"
                            @click="dismissReport(selectedReport)"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                        >
                            Rejeter
                        </button>
                        <button
                            @click="closeModal"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
                        >
                            Fermer
                        </button>
                    </div>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import Modal from '../../Components/Modal.vue';
import Pagination from '../../Components/Reports/Pagination.vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { debounce } from 'lodash';

// Initialisation correcte de l'état reports avec la structure complète
const reports = ref({
    data: [],
    links: [],
    meta: {
        current_page: 1,
        from: null,
        last_page: 1,
        links: [],
        path: '',
        per_page: 10,
        to: null,
        total: 0
    }
});

const searchQuery = ref('');
const filters = ref({
    status: ''
});
const showModal = ref(false);
const selectedReport = ref(null);

// Charger les signalements avec paramètres de recherche et filtres
const loadReports = async () => {
    try {
        console.log('Chargement des signalements...');
        const params = {
            status: filters.value.status,
            search: searchQuery.value,
            page: reports.value.meta.current_page
        };
        console.log('Paramètres de la requête:', params);

        const response = await axios.get(route('admin.reports.list'), { params });
        console.log('Réponse brute:', response);
        console.log('Données reçues:', response.data);
        
        // Vérifier que la réponse contient les données attendues
        if (response.data) {
            reports.value = {
                data: Array.isArray(response.data.data) ? response.data.data : [],
                links: response.data.links || [],
                meta: response.data.meta || {
                    current_page: 1,
                    from: null,
                    last_page: 1,
                    links: [],
                    path: '',
                    per_page: 10,
                    to: null,
                    total: 0
                }
            };
            console.log('État reports mis à jour:', reports.value);
        } else {
            console.error('La réponse ne contient pas de données valides:', response);
        }
    } catch (error) {
        console.error('Erreur lors du chargement des signalements:', error);
        if (error.response) {
            console.error('Réponse d\'erreur:', error.response.data);
        }
    }
};

// Debounce la fonction de recherche
const debouncedLoadReports = debounce(loadReports, 300);

// Observer les changements de recherche et filtres
watch([searchQuery, () => filters.value.status], () => {
    reports.value.meta.current_page = 1; // Réinitialiser à la première page
    debouncedLoadReports();
});

// Filtrer les signalements
const filteredReports = computed(() => {
    return reports.value.data || [];
});

// Formater le statut
const formatStatus = (status) => {
    const statuses = {
        pending: 'En attente',
        accepted: 'Accepté',
        dismissed: 'Rejeté'
    };
    return statuses[status] || status;
};

// Formater la date
const formatDate = (date) => {
    return new Date(date).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// Accepter un signalement
const acceptReport = async (report) => {
    try {
        await axios.post(route('admin.reports.accept', report.id));
        await loadReports();
        if (showModal.value) {
            closeModal();
        }
    } catch (error) {
        console.error('Erreur lors de l\'acceptation du signalement:', error);
    }
};

// Rejeter un signalement
const dismissReport = async (report) => {
    try {
        await axios.post(route('admin.reports.dismiss', report.id));
        await loadReports();
        if (showModal.value) {
            closeModal();
        }
    } catch (error) {
        console.error('Erreur lors du rejet du signalement:', error);
    }
};

// Afficher les détails
const showDetails = (report) => {
    selectedReport.value = report;
    showModal.value = true;
};

// Fermer la modal
const closeModal = () => {
    showModal.value = false;
    selectedReport.value = null;
};

// Charger les données au montage du composant
onMounted(() => {
    loadReports();
});
</script> 