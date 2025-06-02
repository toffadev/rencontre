<template>
    <AdminLayout>
        <!-- En-tête avec statistiques globales -->
        <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg shadow-sm p-6 relative overflow-hidden">
                <div class="absolute right-4 top-4 text-3xl opacity-10">
                    <i class="fas fa-user-circle text-blue-500"></i>
                </div>
                <div class="text-sm text-gray-500">Total Profils Actifs</div>
                <div class="text-2xl font-bold mt-2">{{ stats.activeProfiles }}</div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6 relative overflow-hidden">
                <div class="absolute right-4 top-4 text-3xl opacity-10">
                    <i class="fas fa-comments text-green-500"></i>
                </div>
                <div class="text-sm text-gray-500">Messages Aujourd'hui</div>
                <div class="text-2xl font-bold mt-2">{{ stats.todayMessages }}</div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6 relative overflow-hidden">
                <div class="absolute right-4 top-4 text-3xl opacity-10">
                    <i class="fas fa-coins text-yellow-500"></i>
                </div>
                <div class="text-sm text-gray-500">Points Générés (24h)</div>
                <div class="text-2xl font-bold mt-2">{{ stats.pointsGenerated }}</div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6 relative overflow-hidden">
                <div class="absolute right-4 top-4 text-3xl opacity-10">
                    <i class="fas fa-chart-line text-pink-500"></i>
                </div>
                <div class="text-sm text-gray-500">Taux de Réponse Moyen</div>
                <div class="text-2xl font-bold mt-2">{{ stats.averageResponseRate }}%</div>
            </div>
        </div>

        <!-- Filtres -->
        <FilterProfilePerformance
            v-model:filters="filters"
            :moderators="moderators"
            @filter="loadProfiles"
        />

        <!-- Tableau principal -->
        <ProfilePerformanceTable
            :profiles="profiles"
            :loading="loading"
            :sort="sort"
            @update:sort="updateSort"
            @view-messages="viewProfileMessages"
            @view-details="openProfileDetails"
            @assign-moderator="openModeratorAssignment"
        />

        <!-- Pagination -->
        <div class="mt-4">
            <Pagination 
                :current-page="pagination.currentPage"
                :last-page="pagination.lastPage"
                :from="pagination.from"
                :to="pagination.to"
                :total="pagination.total"
                @navigate="handlePagination"
            />
        </div>

        <!-- Modals -->
        <ProfileDetailModal
            v-if="showProfileDetail"
            :profile="selectedProfile"
            @close="showProfileDetail = false"
        />

        <ModeratorAssignmentModal
            v-if="showModeratorAssignment"
            :profile="selectedProfile"
            :current-moderators="selectedProfile?.moderators"
            @close="showModeratorAssignment = false"
            @assign="assignModerator"
        />
    </AdminLayout>
</template>

<script setup>
import { ref, onMounted, watch } from "vue";
import AdminLayout from "@/Admin/Layouts/AdminLayout.vue";
import StatCard from "@/Admin/Components/StatCard.vue";
import FilterProfilePerformance from "@/Admin/Components/ProfilePeformance/FilterProfilePerformance.vue";
import ProfilePerformanceTable from "@/Admin/Components/ProfilePeformance/ProfilePerformanceTable.vue";
import ProfileDetailModal from "@/Admin/Components/ProfilePeformance/ProfileDetailModal.vue";
import ModeratorAssignmentModal from "@/Admin/Components/ProfilePeformance/ModeratorAssignmentModal.vue";
import Pagination from "@/Admin/Components/ProfilePeformance/Pagination.vue";
import { router } from "@inertiajs/vue3";

// État
const profiles = ref([]);
const loading = ref(true);
const stats = ref({
    activeProfiles: 0,
    todayMessages: 0,
    pointsGenerated: 0,
    averageResponseRate: 0,
});
const filters = ref({
    moderator_id: null,
    period: "all",
    status: "all",
    search: "",
});
const sort = ref({
    field: "messages_received",
    direction: "desc",
});
const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    from: 0,
    to: 0,
    total: 0
});
const showProfileDetail = ref(false);
const showModeratorAssignment = ref(false);
const selectedProfile = ref(null);

// Chargement des données
const loadProfiles = async (page = 1) => {
    loading.value = true;
    try {
        const response = await axios.get("/admin/profile-performance/data", {
            params: {
                ...filters.value,
                sort: sort.value,
                page,
            },
        });
        profiles.value = response.data.data;
        stats.value = response.data.stats;
        pagination.value = {
            currentPage: response.data.current_page,
            lastPage: response.data.last_page,
            from: response.data.from,
            to: response.data.to,
            total: response.data.total
        };
        loading.value = false;
    } catch (error) {
        console.error("Erreur lors du chargement des profils:", error);
        loading.value = false;
    }
};

// Actions
const viewProfileMessages = (profileId) => {
    router.visit(`/admin/profile-performance/${profileId}/messages`);
};

const openProfileDetails = (profile) => {
    selectedProfile.value = profile;
    showProfileDetail.value = true;
};

const openModeratorAssignment = (profile) => {
    selectedProfile.value = profile;
    showModeratorAssignment.value = true;
};

const assignModerator = async (data) => {
    try {
        await axios.post(
            `/admin/profiles/${selectedProfile.value.id}/assign-moderator`,
            data
        );
        await loadProfiles();
        showModeratorAssignment.value = false;
    } catch (error) {
        console.error("Erreur lors de l'assignation du modérateur:", error);
    }
};

const handlePagination = (page) => {
    loadProfiles(page);
};

const updateSort = (field) => {
    sort.value = {
        field,
        direction: sort.value.field === field && sort.value.direction === 'asc' ? 'desc' : 'asc'
    };
    loadProfiles();
};

// Watchers et lifecycle hooks
watch(
    filters,
    () => {
        loadProfiles();
    },
    { deep: true }
);

onMounted(() => {
    loadProfiles();
});
</script>
