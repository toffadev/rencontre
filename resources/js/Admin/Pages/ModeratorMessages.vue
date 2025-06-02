<template>
    <AdminLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Messages du modérateur {{ moderator.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Composant de filtrage -->
                <FilterModeratorMessage
                    :available-filters="filters"
                    @filter="handleFilterChange"
                />

                <!-- Statistiques -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <div v-for="(stat, index) in statistics" :key="index" class="bg-white p-4 rounded-lg shadow-sm">
                        <h3 class="text-sm font-medium text-gray-500">{{ stat.label }}</h3>
                        <p class="mt-1 text-2xl font-semibold text-gray-900">{{ stat.value }}</p>
                    </div>
                </div>

                <!-- Table des messages -->
                <ModeratorMessageTable
                    :messages="messages"
                    :loading="loading"
                    @view-conversation="openConversation"
                    @view-details="openMessageDetails"
                    @sort="handleSort"
                />

                <!-- Pagination -->
                <div class="mt-4">
                    <Pagination 
                        v-model="paginationState"
                        @paginate="fetchMessages"
                    />
                </div>
            </div>
        </div>

        <!-- Modals -->
        <ConversationModal
            v-if="showConversationModal"
            :conversation="selectedConversation"
            @close="closeConversationModal"
        />

        <MessageDetailModal
            v-if="showMessageDetailModal"
            :message="selectedMessage"
            @close="closeMessageDetailModal"
        />
    </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Inertia } from '@inertiajs/inertia';
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue';
import FilterModeratorMessage from '@/Admin/Components/FilterModeratorMessage.vue';
import ModeratorMessageTable from '@/Admin/Components/ModeratorMessageTable.vue';
import ConversationModal from '@/Admin/Components/ConversationModal.vue';
import MessageDetailModal from '@/Admin/Components/MessageDetailModal.vue';
import Pagination from '@/Admin/Components/Pagination.vue';

const props = defineProps({
    moderator: {
        type: Object,
        required: true
    },
    filters: {
        type: Object,
        required: true
    }
});

const messages = ref({
    data: [],
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0
});
const statistics = ref([]);
const loading = ref(true);
const currentFilters = ref({});
const currentSort = ref({
    field: 'created_at',
    direction: 'desc'
});

const paginationState = ref({
    currentPage: 1,
    lastPage: 1,
    perPage: 20,
    total: 0
});

// Modal states
const showConversationModal = ref(false);
const showMessageDetailModal = ref(false);
const selectedConversation = ref(null);
const selectedMessage = ref(null);

const fetchMessages = async () => {
    loading.value = true;
    try {
        const response = await axios.get(`/admin/moderators/${props.moderator.id}/messages/data`, {
            params: {
                ...currentFilters.value,
                sort_field: currentSort.value.field,
                sort_direction: currentSort.value.direction,
                page: paginationState.value.currentPage
            }
        });
        
        messages.value = response.data.messages;
        paginationState.value = {
            currentPage: messages.value.current_page,
            lastPage: messages.value.last_page,
            perPage: messages.value.per_page,
            total: messages.value.total
        };
        
        updateStatistics(response.data.stats);
    } catch (error) {
        console.error('Error fetching messages:', error);
        // Reset to default state in case of error
        messages.value = {
            data: [],
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 0
        };
        
        paginationState.value = {
            currentPage: 1,
            lastPage: 1,
            perPage: 20,
            total: 0
        };
    }
    loading.value = false;
};

const updateStatistics = (stats) => {
    statistics.value = [
        { label: 'Total messages', value: stats.total_messages },
        { label: 'Messages courts', value: stats.short_messages },
        { label: 'Messages longs', value: stats.long_messages },
        { label: 'Points gagnés', value: stats.total_points }
    ];
};

const handleFilterChange = (filters) => {
    currentFilters.value = filters;
    fetchMessages();
};

const handleSort = (sortData) => {
    currentSort.value = sortData;
    fetchMessages();
};

const openConversation = async (messageData) => {
    try {
        const response = await axios.get(`/admin/moderators/conversation`, {
            params: {
                moderator_id: props.moderator.id,
                client_id: messageData.client_id,
                profile_id: messageData.profile_id
            }
        });
        selectedConversation.value = response.data.messages;
        showConversationModal.value = true;
    } catch (error) {
        console.error('Error fetching conversation:', error);
    }
};

const closeConversationModal = () => {
    showConversationModal.value = false;
    selectedConversation.value = null;
};

const openMessageDetails = (message) => {
    selectedMessage.value = message;
    showMessageDetailModal.value = true;
};

const closeMessageDetailModal = () => {
    showMessageDetailModal.value = false;
    selectedMessage.value = null;
};

onMounted(() => {
    fetchMessages();
});
</script> 