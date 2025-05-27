<template>
    <AdminLayout>
        <div class="flex flex-col h-[calc(100vh-4rem)]">
            <!-- Page Header -->
            <div class="bg-white shadow px-4 py-4 sm:px-6 mb-4">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-semibold text-gray-900">
                        Visualiseur de Conversations
                    </h1>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500">
                            {{ totalConversations }} conversations
                        </span>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="flex-1 px-4 sm:px-6 pb-4">
                <div class="flex h-full space-x-4">
                    <!-- Left Sidebar - Client/Profile Selection -->
                    <div class="w-96">
                        <ClientProfileSelector
                            v-model="selectedConversation"
                            @update:modelValue="handleConversationSelect"
                        />
                    </div>

                    <!-- Center - Conversation Thread -->
                    <div class="flex-auto">
                        <ConversationThread
                            v-if="selectedConversation"
                            :messages="messages"
                            :client-name="selectedConversation.client.name"
                            :client-avatar="selectedConversation.client.avatar"
                            :profile-name="selectedConversation.profile.name"
                            :profile-avatar="selectedConversation.profile.main_photo_path"
                            :loading="loading"
                            @load-more="loadMoreMessages"
                        />
                        <div v-else class="bg-white rounded-lg shadow h-full flex items-center justify-center">
                            <div class="text-center">
                                <div class="text-gray-400 mb-4">
                                    <i class="fas fa-comments text-5xl"></i>
                                </div>
                                <h3 class="text-lg font-medium text-gray-700">
                                    Sélectionnez une conversation
                                </h3>
                                <p class="text-gray-500 mt-2">
                                    Choisissez un client et un profil pour voir leur conversation
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Right Sidebar - Statistics -->
                    <div class="w-96">
                        <ConversationStats
                            v-if="selectedConversation && stats"
                            :stats="stats"
                        />
                        <div v-else-if="selectedConversation && loading" class="bg-white rounded-lg shadow p-6">
                            <div class="animate-pulse space-y-4">
                                <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                                <div class="space-y-3">
                                    <div class="h-4 bg-gray-200 rounded"></div>
                                    <div class="h-4 bg-gray-200 rounded w-5/6"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AdminLayout from '@/Admin/Layouts/AdminLayout.vue'
import ClientProfileSelector from '@/Admin/Components/ClientProfileSelector.vue';
import ConversationThread from '@/Admin/Components/ConversationThread.vue';
import ConversationStats from '@/Admin/Components/ConversationStats.vue';
import axios from 'axios';

const selectedConversation = ref(null);
const messages = ref([]);
const stats = ref(null);
const loading = ref(false);
const totalConversations = ref(0);
const currentPage = ref(1);

// Load conversation data when a client and profile are selected
const handleConversationSelect = async (conversation) => {
    if (!conversation) {
        selectedConversation.value = null;
        messages.value = [];
        stats.value = null;
        return;
    }

    try {
        loading.value = true;
        currentPage.value = 1;
        
        const response = await axios.get(
            `/admin/conversations/conversation/${conversation.client.id}/${conversation.profile.id}`
        );

        selectedConversation.value = conversation;
        messages.value = response.data.messages;
        stats.value = response.data.statistics;
    } catch (error) {
        console.error('Error loading conversation:', error);
    } finally {
        loading.value = false;
    }
};

// Load more messages when scrolling up
const loadMoreMessages = async () => {
    if (!selectedConversation.value || loading.value) return;

    try {
        loading.value = true;
        currentPage.value++;

        const response = await axios.get(
            `/admin/conversations/conversation/${selectedConversation.value.client.id}/${selectedConversation.value.profile.id}`,
            {
                params: {
                    page: currentPage.value
                }
            }
        );

        // Prepend new messages to existing ones
        messages.value = [...response.data.messages, ...messages.value];

        // Update stats
        stats.value = response.data.statistics;
    } catch (error) {
        console.error('Error loading more messages:', error);
        currentPage.value--; // Revert page increment on error
    } finally {
        loading.value = false;
    }
};
</script> 