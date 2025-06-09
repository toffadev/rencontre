<template>
    <MainLayout>
        <div class="main-container">
            <div class="flex flex-col space-y-4 mb-4">
                <div class="bg-white p-4 rounded-xl shadow-md">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-xl font-semibold text-pink-600">
                                Espace Modérateur
                            </h2>
                            <p class="text-sm text-gray-600">
                                Vous êtes connecté en tant que modérateur. Vous
                                pouvez discuter avec des clients en utilisant un
                                profil virtuel.
                            </p>
                        </div>
                        <div class="flex items-center gap-4">
                            <!-- Bouton de notifications -->
                            <div class="relative">
                                <button @click="showNotifications = !showNotifications"
                                    class="px-4 py-2 bg-pink-100 text-pink-600 rounded-lg hover:bg-pink-200 transition-colors duration-200 flex items-center gap-2">
                                    <i class="fas fa-bell"></i>
                                    <span v-if="notifications.filter(n => !n.read).length > 0"
                                        class="absolute -top-1 -right-1 bg-pink-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">
                                        {{ notifications.filter(n => !n.read).length }}
                                    </span>
                                </button>
                                
                                <!-- Panel de notifications -->
                                <div v-if="showNotifications"
                                    class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg z-50 max-h-96 overflow-y-auto">
                                    <div class="p-4 border-b border-gray-100">
                                        <h3 class="font-semibold text-gray-700">Notifications</h3>
                                    </div>
                                    <div v-if="notifications.length > 0" class="divide-y divide-gray-100">
                                        <div v-for="notification in notifications" :key="notification.id"
                                            @click="goToConversation(notification.clientId); markNotificationAsRead(notification.id); showNotifications = false"
                                            class="p-4 hover:bg-gray-50 cursor-pointer transition-colors duration-200"
                                            :class="{ 'bg-pink-50': !notification.read }">
                                            <div class="flex items-start gap-3">
                                                <div class="flex-1">
                                                    <p class="font-medium text-gray-800">{{ notification.clientName }}</p>
                                                    <p class="text-sm text-gray-600 truncate">{{ notification.message }}</p>
                                                    <p class="text-xs text-gray-400 mt-1">
                                                        {{ new Date(notification.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                                    </p>
                                                </div>
                                                <div v-if="!notification.read"
                                                    class="w-2 h-2 bg-pink-500 rounded-full flex-shrink-0 mt-2">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else class="p-4 text-center text-gray-500">
                                        Aucune notification
                                    </div>
                                </div>
                            </div>
                            
                            <Link href="/moderateur/profile-stats"
                                class="px-4 py-2 bg-pink-100 text-pink-600 rounded-lg hover:bg-pink-200 transition-colors duration-200 flex items-center gap-2">
                                <i class="fas fa-chart-line"></i>
                                Mon profil
                            </Link>
                        </div>
                    </div>
                </div>

                <div v-if="!currentAssignedProfile" class="bg-white p-6 rounded-xl shadow-md text-center">
                    <div class="text-lg font-medium text-gray-700">
                        En attente d'attribution...
                    </div>
                    <p class="text-gray-500 mt-2">
                        Le système vous attribuera automatiquement un profil pour
                        discuter avec des clients.
                    </p>
                    <div class="mt-4">
                        <div class="animate-pulse flex space-x-4 justify-center">
                            <div class="rounded-full bg-pink-200 h-12 w-12"></div>
                            <div class="flex-1 space-y-4 max-w-md">
                                <div class="h-4 bg-pink-200 rounded w-3/4"></div>
                                <div class="h-4 bg-pink-200 rounded w-1/2"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col lg:flex-row gap-6">
                <!-- Clients Section (à gauche) -->
                <div class="w-full lg:w-1/4 bg-white rounded-xl shadow-md overflow-hidden">
                    <!-- Tabs -->
                    <div class="flex border-b border-gray-200">
                        <button @click="activeTab = 'assigned'" :class="[
                            'flex-1 py-3 text-sm font-medium',
                            activeTab === 'assigned'
                                ? 'text-pink-600 border-b-2 border-pink-500'
                                : 'text-gray-500 hover:text-gray-700',
                        ]">
                            Client attribué
                        </button>
                        <button @click="activeTab = 'available'" :class="[
                            'flex-1 py-3 text-sm font-medium',
                            activeTab === 'available'
                                ? 'text-pink-600 border-b-2 border-pink-500'
                                : 'text-gray-500 hover:text-gray-700',
                        ]">
                            Clients disponibles
                        </button>
                    </div>

                    <!-- Tab Content: Client attribué -->
                    <div v-if="activeTab === 'assigned'" class="p-4">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold">Client attribué</h2>
                            <div v-if="assignedClient.length > 0"
                                class="bg-green-100 text-green-600 px-3 py-1 rounded-full text-sm">
                                En attente de réponse
                            </div>
                            <div v-else class="bg-yellow-100 text-yellow-600 px-3 py-1 rounded-full text-sm">
                                En attente d'attribution
                            </div>
                        </div>

                        <div class="space-y-4">
                            <!-- Liste des clients attribués -->
                            <div v-if="assignedClient.length > 0" class="space-y-4">
                                <div v-for="client in sortedAssignedClients" :key="client.id"
                                    class="client-card transition duration-300" @click="selectClient(client)">
                                    <div :class="[
                                        'bg-white rounded-lg shadow-sm p-4 flex items-center space-x-3 border border-gray-100',
                                        selectedClient &&
                                            selectedClient.id === client.id
                                            ? 'border-l-4 border-pink-500'
                                            : '',
                                    ]">
                                        <div class="relative">
                                            <template v-if="client.avatar">
                                                <img
                                                    :src="client.avatar"
                                                    :alt="client.name"
                                                    class="w-12 h-12 rounded-full object-cover"
                                                />
                                            </template>
                                            <template v-else>
                                                <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center">
                                                    <i class="fas fa-user text-gray-400 text-xl"></i>
                                                </div>
                                            </template>
                                            <div class="online-dot"></div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between">
                                                <h3 class="font-semibold truncate">
                                                    {{ client.name }}
                                                </h3>
                                                <span class="text-xs text-gray-500">{{
                                                    formatTime(client.createdAt)
                                                }}</span>
                                            </div>
                                            <p class="text-sm text-gray-500">
                                                <span v-if="client.lastMessage" class="truncate block">{{ client.lastMessage
                                                    }}</span>
                                                <span v-else class="text-gray-400 italic">Nouvelle conversation</span>
                                            </p>
                                            <div class="flex items-center mt-1 text-xs">
                                                <img :src="client.profilePhoto" alt="Profile"
                                                    class="w-4 h-4 rounded-full mr-1" />
                                                <span class="text-gray-600">{{
                                                    client.profileName
                                                    }}</span>
                                            </div>
                                        </div>
                                        <div v-if="client.unreadCount"
                                            class="bg-pink-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">
                                            {{ client.unreadCount }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- État vide -->
                            <div v-else class="text-center py-8">
                                <p class="text-gray-500">
                                    Aucun client ne vous a été attribué pour le
                                    moment.
                                </p>
                                <p class="text-gray-400 text-sm mt-2">
                                    Le système vous attribuera automatiquement un
                                    client qui attend une réponse, ou consultez les
                                    clients disponibles.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Content: Clients disponibles -->
                    <div v-if="activeTab === 'available'" class="p-4">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-semibold">
                                Clients disponibles
                            </h2>
                            <button @click="loadAvailableClients"
                                class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>

                        <div class="space-y-4">
                            <!-- Liste des clients disponibles -->
                            <div v-if="availableClients.length > 0">
                                <div v-for="client in availableClients" :key="client.id"
                                    class="client-card transition duration-300 cursor-pointer"
                                    @click="startConversation(client)">
                                    <div
                                        class="bg-white rounded-lg shadow-sm p-4 flex items-center space-x-3 border border-gray-100 hover:border-pink-200">
                                        <div class="relative">
                                            <template v-if="client.avatar">
                                                <img
                                                    :src="client.avatar"
                                                    :alt="client.name"
                                                    class="w-12 h-12 rounded-full object-cover"
                                                />
                                            </template>
                                            <template v-else>
                                                <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center">
                                                    <i class="fas fa-user text-gray-400 text-xl"></i>
                                                </div>
                                            </template>
                                            <div class="online-dot"></div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h3 class="font-semibold truncate">
                                                {{ client.name }}
                                            </h3>
                                            <p class="text-sm text-gray-500">
                                                <span v-if="client.lastMessage" class="truncate block">{{ client.lastMessage
                                                    }}</span>
                                                <span v-else-if="client.hasHistory"
                                                    class="text-gray-400 italic">Conversation précédente</span>
                                                <span v-else class="text-green-500 italic">Nouveau client</span>
                                            </p>
                                            <p class="text-xs text-gray-400 mt-1">
                                                {{ client.lastActivity }}
                                            </p>
                                        </div>
                                        <button
                                            class="p-2 rounded-full bg-pink-100 text-pink-600 hover:bg-pink-200 transition">
                                            <i class="fas fa-comments"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- État de chargement -->
                            <div v-else-if="loading" class="py-8">
                                <div class="animate-pulse flex space-x-4 justify-center">
                                    <div class="rounded-full bg-pink-200 h-12 w-12"></div>
                                    <div class="flex-1 space-y-4 max-w-md">
                                        <div class="h-4 bg-pink-200 rounded w-3/4"></div>
                                        <div class="h-4 bg-pink-200 rounded w-1/2"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- État vide -->
                            <div v-else class="text-center py-8">
                                <p class="text-gray-500">
                                    Aucun client disponible pour le moment.
                                </p>
                                <p class="text-gray-400 text-sm mt-2">
                                    Réessayez plus tard ou attendez qu'un client
                                    soit attribué automatiquement.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chat Section -->
                <div class="w-full lg:w-2/4 flex flex-col" ref="chatSection">
                    <!-- Version mobile du ClientInfoPanel -->
                    <div class="lg:hidden">
                        <ClientInfoDrawer 
                            v-if="selectedClient"
                            :key="`drawer-${selectedClient.id}`"
                            :client-id="selectedClient.id"
                            @edit="openFullInfoModal"
                        />
                    </div>

                    <!-- Profil attribué -->
                    <div v-if="currentAssignedProfile" class="bg-white rounded-xl shadow-md p-4 mb-4">
                        <div class="flex items-center space-x-4">
                            <img :src="currentAssignedProfile.main_photo_path || '/images/default-avatar.png'"
                                 :alt="currentAssignedProfile.name"
                                 class="w-16 h-16 rounded-full object-cover" />
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800">
                                    {{ currentAssignedProfile.name }}
                                </h3>
                                <p class="text-sm text-gray-600">
                                    Profil virtuel attribué
                                </p>
                                <div class="flex items-center mt-1 text-xs text-gray-500">
                                    <span class="mr-2">
                                        <i class="fas fa-venus-mars"></i>
                                        {{ currentAssignedProfile.gender === 'female' ? 'Femme' : 'Homme' }}
                                    </span>
                                    <span v-if="currentAssignedProfile.age">
                                        <i class="fas fa-birthday-cake ml-2 mr-1"></i>
                                        {{ currentAssignedProfile.age }} ans
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Content -->
                    <div v-if="selectedClient" class="bg-white rounded-xl shadow-md overflow-hidden flex flex-col h-[calc(100vh-theme(spacing.32))]">
                        <!-- Chat Header -->
                        <div class="border-b border-gray-200 p-4 flex items-center space-x-3">
                            <div class="relative">
                                <template v-if="selectedClient.avatar">
                                    <img
                                        :src="selectedClient.avatar"
                                        :alt="selectedClient.name"
                                        class="w-12 h-12 rounded-full object-cover"
                                    />
                                </template>
                                <template v-else>
                                    <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center">
                                        <i class="fas fa-user text-gray-400 text-xl"></i>
                                    </div>
                                </template>
                                <div class="online-dot"></div>
                            </div>
                            <div>
                                <h3 class="font-semibold">
                                    {{ selectedClient.name }}
                                </h3>
                                <p class="text-sm text-gray-500">
                                    En discussion avec vous
                                </p>
                            </div>
                            <div class="ml-auto flex items-center space-x-2">
                                <div class="text-sm text-gray-500">
                                    <span class="font-medium">Client ID:</span>
                                    {{ selectedClient.id }}
                                </div>
                                <button @click="openFullInfoModal" 
                                        class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Chat Messages -->
                        <div class="chat-container flex-1 overflow-y-auto p-4 space-y-3" ref="chatContainer" @scroll="handleScroll">
                            <!-- Indicateur de chargement des messages plus anciens -->
                            <div v-if="isLoadingMore" class="text-center py-2">
                                <div class="inline-block animate-spin rounded-full h-4 w-4 border-2 border-pink-500 border-t-transparent"></div>
                                <span class="text-xs text-gray-500 ml-2">Chargement des messages...</span>
                            </div>

                            <!-- Indicateur de messages plus anciens disponibles -->
                            <div v-if="hasMoreMessages && !isLoadingMore" class="text-center text-xs text-gray-500 my-2">
                                Faites défiler vers le haut pour charger plus de messages
                            </div>

                            <!-- Date -->
                            <div class="text-center text-xs text-gray-500 my-4">
                                Aujourd'hui
                            </div>

                            <div v-for="(message, index) in currentChatMessages" :key="message.id || index" :class="`flex space-x-2 ${message.isFromClient ? '' : 'justify-end'}`">
                                <template v-if="message.isFromClient">
                                    <template v-if="selectedClient.avatar">
                                        <img
                                            :src="selectedClient.avatar"
                                            :alt="selectedClient.name"
                                            class="w-8 h-8 rounded-full object-cover flex-shrink-0"
                                        />
                                    </template>
                                    <template v-else>
                                        <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-user text-gray-400 text-sm"></i>
                                        </div>
                                    </template>
                                    <div>
                                        <div class="message-in px-4 py-2 max-w-xs lg:max-w-md">
                                            <!-- Contenu du message -->
                                            <div v-if="message.content">{{ message.content }}</div>
                                            
                                            <!-- Image attachée -->
                                            <div v-if="message.attachment && message.attachment.mime_type.startsWith('image/')" class="mt-2">
                                                <img
                                                    :src="message.attachment.url"
                                                    :alt="message.attachment.file_name"
                                                    class="max-w-full rounded-lg cursor-pointer"
                                                    @click="showImagePreview(message.attachment)"
                                                />
                                            </div>
                                        </div>
                                        <div class="flex items-center mt-1 text-xs text-gray-500">
                                            <span>{{ message.time }}</span>
                                            <span class="mx-1">•</span>
                                            <span>{{ selectedClient.name }}</span>
                                        </div>
                                    </div>
                                </template>
                                <template v-else>
                                    <div>
                                        <div class="message-out px-4 py-2 max-w-xs lg:max-w-md">
                                            <!-- Contenu du message -->
                                            <div v-if="message.content">{{ message.content }}</div>
                                            
                                            <!-- Image attachée -->
                                            <div v-if="message.attachment && message.attachment.mime_type.startsWith('image/')" class="mt-2">
                                                <img
                                                    :src="message.attachment.url"
                                                    :alt="message.attachment.file_name"
                                                    class="max-w-full rounded-lg cursor-pointer"
                                                    @click="showImagePreview(message.attachment)"
                                                />
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-end mt-1 text-xs text-gray-500">
                                            <span>{{ message.time }}</span>
                                            <span class="mx-1">•</span>
                                            <span>{{
                                                currentAssignedProfile?.name ||
                                                "Vous"
                                            }}</span>
                                        </div>
                                    </div>
                                    <img :src="currentAssignedProfile?.main_photo_path ||
                                        'https://via.placeholder.com/64'
                                        " :alt="currentAssignedProfile?.name || 'Profil'
                                            " class="w-8 h-8 rounded-full object-cover flex-shrink-0" />
                                </template>
                            </div>
                        </div>

                        <!-- Message Input -->
                        <div class="border-t border-gray-200 bg-white z-50 p-4 mb-16 lg:mb-0">
                            <div class="flex flex-col space-y-2">
                                <!-- Prévisualisation de l'image -->
                                <div v-if="selectedFile" class="flex justify-end">
                                    <div class="relative inline-block">
                                        <img
                                            :src="previewUrl"
                                            class="max-h-32 rounded-lg"
                                            alt="Preview"
                                        />
                                        <button
                                            @click="removeSelectedFile"
                                            class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center"
                                        >
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="flex items-center space-x-2">
                                    <input
                                        type="file"
                                        ref="fileInput"
                                        class="hidden"
                                        accept="image/*"
                                        @change="handleFileUpload"
                                    />
                                    <button
                                        class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition"
                                        title="Ajouter une image"
                                        @click="$refs.fileInput.click()"
                                    >
                                        <i class="fas fa-image"></i>
                                    </button>
                                    <div class="flex-1 relative">
                                        <input
                                            v-model="newMessage"
                                            type="text"
                                            placeholder="Écrire un message..."
                                            class="w-full px-4 py-2 bg-gray-100 rounded-full focus:outline-none focus:ring-2 focus:ring-pink-500"
                                            @keyup.enter="sendMessage"
                                        />
                                    </div>
                                    <button
                                        class="p-2 rounded-full bg-pink-500 text-white hover:bg-pink-600 transition"
                                        @click="sendMessage"
                                    >
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- État vide pour le chat -->
                    <div v-else class="bg-white rounded-xl shadow-md p-8 flex-1 flex items-center justify-center">
                        <div class="text-center">
                            <div class="text-gray-400 mb-4">
                                <i class="fas fa-comments text-5xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-700">
                                Sélectionnez un client pour discuter
                            </h3>
                            <p class="text-gray-500 mt-2">
                                Choisissez un client attribué ou disponible pour
                                commencer une conversation
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Informations client (à droite) - Version desktop uniquement -->
                <div class="hidden lg:block lg:w-1/4">
                    <ClientInfoPanel v-if="selectedClient" :client-id="selectedClient.id" />
                </div>
            </div>

            <!-- Modals -->
            <Teleport to="body">
                <!-- Modal pour édition complète sur mobile -->
                <div v-if="showFullInfoModal" 
                     class="fixed inset-0 z-50 lg:hidden bg-white">
                    <div class="h-full overflow-y-auto">
                        <div class="sticky top-0 bg-white border-b border-gray-200 p-4 flex items-center justify-between z-10">
                            <h2 class="text-lg font-semibold text-gray-800">Informations du client</h2>
                            <button @click="showFullInfoModal = false" 
                                    class="p-2 rounded-full hover:bg-gray-100">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="p-4 pb-32">
                            <ClientInfoPanel v-if="selectedClient" :client-id="selectedClient.id" />
                        </div>
                    </div>
                </div>

                <!-- Modal de prévisualisation d'image -->
                <div v-if="showPreview" 
                     class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50" 
                     @click="closeImagePreview">
                    <div class="max-w-4xl max-h-full p-4">
                        <img :src="previewImage.url" 
                             :alt="previewImage.file_name" 
                             class="max-w-full max-h-[90vh] object-contain" />
                    </div>
                </div>

                <!-- Autres modals existants -->
                <ProfileActionModal
                    v-if="showActionModal"
                    :show="showActionModal"
                    :profile="selectedProfileForActions"
                    @close="closeActionModal"
                    @chat="startChat"
                />

                <ProfileReportModal
                    v-if="showReportModalFlag && selectedProfileForReport"
                    :show="showReportModalFlag"
                    :user-id="selectedProfileForReport.userId"
                    :profile-id="selectedProfileForReport.profileId"
                    @close="closeReportModal"
                    @reported="handleReported"
                />
            </Teleport>
        </div>
    </MainLayout>
</template>

<script setup>
import { ref, onMounted, watch, computed, nextTick, onUnmounted } from "vue";
import MainLayout from "@client/Layouts/MainLayout.vue";
import axios from "axios";
import Echo from "laravel-echo";
import ClientInfoPanel from "@client/Components/ClientInfoPanel.vue";
import ClientInfoDrawer from "@client/Components/ClientInfoDrawer.vue";
import ProfileActionModal from "@client/Components/ProfileActionModal.vue";
import ProfileReportModal from "@client/Components/ProfileReportModal.vue";
import { Link } from "@inertiajs/vue3";

// Créer une fonction pour configurer Axios
/* const configureAxios = () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
        axios.defaults.withCredentials = true;
    } else {
        console.warn('CSRF token not found');
    }
}; */

const configureAxios = async () => {
    // Attendre que le DOM soit complètement chargé
    await new Promise(resolve => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', resolve);
        } else {
            resolve();
        }
    });

    // Récupérer le token CSRF depuis les métadonnées
    let token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    // Si pas de token, essayer de le récupérer depuis window.Laravel
    if (!token && window.Laravel && window.Laravel.csrfToken) {
        token = window.Laravel.csrfToken;
    }

    // Si toujours pas de token, faire une requête pour l'obtenir
    if (!token) {
        try {
            await axios.get('/sanctum/csrf-cookie');
            token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        } catch (error) {
            console.error('Impossible de récupérer le token CSRF:', error);
        }
    }

    if (token) {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
        axios.defaults.withCredentials = true;
        console.log('Axios configuré avec le token CSRF');
    } else {
        console.error('CSRF token introuvable après toutes les tentatives');
    }
};

const setupAxiosInterceptor = () => {
    // Intercepteur pour les réponses
    axios.interceptors.response.use(
        response => response,
        async error => {
            if (error.response?.status === 419) {
                console.log('Erreur 419 détectée, tentative de renouvellement du token...');

                try {
                    // Renouveler le token CSRF
                    await axios.get('/sanctum/csrf-cookie');

                    // Attendre un peu pour que le token soit bien défini
                    await new Promise(resolve => setTimeout(resolve, 100));

                    // Reconfigurer Axios avec le nouveau token
                    await configureAxios();

                    // Réessayer la requête originale
                    const originalRequest = error.config;
                    if (originalRequest) {
                        return axios(originalRequest);
                    }
                } catch (retryError) {
                    console.error('Échec du renouvellement du token:', retryError);
                    // Afficher un message à l'utilisateur
                    showAuthError();
                }
            }
            return Promise.reject(error);
        }
    );

    // Intercepteur pour les requêtes (s'assurer que le token est toujours présent)
    axios.interceptors.request.use(
        config => {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (token) {
                config.headers['X-CSRF-TOKEN'] = token;
            }
            config.headers['X-Requested-With'] = 'XMLHttpRequest';
            return config;
        },
        error => Promise.reject(error)
    );
};

// === SOLUTION 3: Fonction d'attente de l'authentification ===

const waitForAuthentication = async (maxAttempts = 10, delay = 500) => {
    for (let i = 0; i < maxAttempts; i++) {
        // Vérifier si l'utilisateur est authentifié
        const isAuthenticated = window.Laravel && window.Laravel.user && window.Laravel.user.id;
        const hasCSRFToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        if (isAuthenticated && hasCSRFToken) {
            console.log('Authentification confirmée');
            return true;
        }

        console.log(`Attente de l'authentification... tentative ${i + 1}/${maxAttempts}`);
        await new Promise(resolve => setTimeout(resolve, delay));
    }

    console.error('Timeout: authentification non confirmée après', maxAttempts, 'tentatives');
    return false;
};

const showAuthError = () => {
    // Vous pouvez adapter cette fonction selon votre UI
    console.error('Erreur d\'authentification persistante');

    // Option 1: Recharger automatiquement
    setTimeout(() => {
        window.location.reload();
    }, 2000);

    // Option 2: Afficher un toast ou une notification
    // showToast('Problème d\'authentification, rechargement...', 'error');
};

// État des données
const currentAssignedProfile = ref(null);
const assignedClient = ref([]);
const selectedClient = ref(null);
const availableClients = ref([]);
const newMessage = ref("");
const chatMessages = ref({});
const chatContainer = ref(null);
const loading = ref(false);
const activeTab = ref("assigned");
const notifications = ref([]);
const showNotifications = ref(false);
const isLoadingMore = ref(false);
const hasMoreMessages = ref(true);
const currentPage = ref({});
const messagesPerPage = 20;

// Ajouter les refs manquantes
const showActionModal = ref(false);
const showReportModalFlag = ref(false);
const selectedProfileForActions = ref(null);
const selectedProfileForReport = ref(null);

// Messages pour la conversation actuelle
const currentChatMessages = computed(() => {
    if (!selectedClient.value) return [];
    return chatMessages.value[selectedClient.value.id] || [];
});

// Clients triés par date de dernier message
const sortedAssignedClients = computed(() => {
    if (!assignedClient.value) return [];
    return [...assignedClient.value].sort((a, b) => {
        const dateA = new Date(a.createdAt);
        const dateB = new Date(b.createdAt);
        return dateB - dateA; // Tri décroissant (plus récent au plus ancien)
    });
});

// Charger les données réelles depuis l'API (profil et client attribués)
const loadAssignedData = async () => {
    try {
        console.log("Chargement des données du modérateur...");

        // Charger le profil attribué
        const profileResponse = await axios.get("/moderateur/profile");
        console.log("Réponse des profils:", profileResponse.data);

        if (profileResponse.data.primaryProfile) {
            currentAssignedProfile.value = profileResponse.data.primaryProfile;
            console.log(
                "Profil principal attribué:",
                currentAssignedProfile.value
            );

            // Charger le client attribué
            const clientsResponse = await axios.get("/moderateur/clients");
            console.log("Réponse des clients:", clientsResponse.data);

            if (
                clientsResponse.data.clients &&
                clientsResponse.data.clients.length > 0
            ) {
                // Au lieu de prendre juste le premier client, on garde tous les clients
                const newClients = clientsResponse.data.clients;

                // Mettre à jour la liste des clients attribués
                assignedClient.value = newClients;

                // Si aucun client n'est sélectionné, sélectionner le plus récent
                if (!selectedClient.value && newClients.length > 0) {
                    selectedClient.value = newClients[0];
                    await loadMessages(newClients[0].id);
                }

                console.log("Clients attribués:", newClients);
            } else {
                console.log("Aucun client attribué");
                assignedClient.value = [];
            }
        } else {
            console.log("Aucun profil attribué");
            currentAssignedProfile.value = null;
            assignedClient.value = [];
        }

        // Charger les clients disponibles
        await loadAvailableClients();
    } catch (error) {
        console.error("Erreur lors du chargement des données:", error);
    }
};

// Charger les clients disponibles
const loadAvailableClients = async () => {
    if (!currentAssignedProfile.value) return;

    try {
        loading.value = true;
        const response = await axios.get("/moderateur/available-clients");
        if (response.data.availableClients) {
            availableClients.value = response.data.availableClients;
        }
    } catch (error) {
        console.error(
            "Erreur lors du chargement des clients disponibles:",
            error
        );
    } finally {
        loading.value = false;
    }
};

// Dans la section script, ajoutons la ref
const chatSection = ref(null);

// Modifions la fonction selectClient
const selectClient = async (client) => {
    selectedClient.value = client;
    hasMoreMessages.value = true;
    currentPage.value[client.id] = 1;

    try {
        // S'assurer que nous avons le bon profil pour ce client
        const profileId = currentAssignedProfile.value?.id;
        if (!profileId) {
            console.error("Aucun profil attribué");
            return;
        }

        // Charger les messages initiaux
        await loadMessages(client.id, 1, false);

        // Marquer la notification comme lue si elle existe
        const notification = notifications.value.find(
            n => n.clientId === client.id && !n.read
        );
        if (notification) {
            markNotificationAsRead(notification.id);
        }

        // Faire défiler jusqu'à la section de chat sur mobile
        nextTick(() => {
            if (window.innerWidth < 1024) { // Vérifier si on est sur mobile
                chatSection.value?.scrollIntoView({ behavior: 'smooth' });
            }
            // Faire défiler le conteneur de messages vers le bas
            if (chatContainer.value) {
                chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
            }
        });
    } catch (error) {
        console.error("Erreur lors de la sélection du client:", error);
    }
};

// Modifions la fonction startConversation
const startConversation = async (client) => {
    try {
        loading.value = true;

        // Vérifier qu'un profil est attribué
        if (!currentAssignedProfile.value) {
            console.error(
                "Impossible de démarrer une conversation: aucun profil attribué"
            );
            return;
        }

        const profileId = currentAssignedProfile.value.id;
        console.log(
            `Démarrage d'une conversation avec client_id=${client.id} et profile_id=${profileId}`
        );

        const response = await axios.post("/moderateur/start-conversation", {
            client_id: client.id,
            profile_id: profileId,
        });

        if (response.data.success) {
            console.log("Conversation démarrée avec succès:", response.data);
            // Stocker les messages
            chatMessages.value[client.id] = response.data.messages;

            // Sélectionner ce client
            selectedClient.value = {
                ...client,
                ...response.data.client,
            };

            // Changer l'onglet
            activeTab.value = "assigned";

            // Faire défiler jusqu'à la section de chat sur mobile
            nextTick(() => {
                if (window.innerWidth < 1024) { // Vérifier si on est sur mobile
                    chatSection.value?.scrollIntoView({ behavior: 'smooth' });
                }
                // Faire défiler le conteneur de messages vers le bas
                if (chatContainer.value) {
                    chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
                }
            });
        }
    } catch (error) {
        console.error("Erreur lors du démarrage de la conversation:", error);
        console.error("Détails:", {
            status: error.response?.status,
            data: error.response?.data,
        });
    } finally {
        loading.value = false;
    }
};

// Charger les messages pour un client spécifique
const loadMessages = async (clientId, page = 1, append = false) => {
    try {
        if (!currentAssignedProfile.value) {
            console.error("Impossible de charger les messages: aucun profil attribué");
            return;
        }

        if (isLoadingMore.value) return;
        isLoadingMore.value = true;

        const profileId = currentAssignedProfile.value.id;
        console.log(`Chargement des messages pour client_id=${clientId} et profile_id=${profileId}, page=${page}`);

        const response = await axios.get("/moderateur/messages", {
            params: {
                client_id: clientId,
                profile_id: profileId,
                page: page,
                per_page: messagesPerPage
            },
        });

        if (response.data.messages) {
            console.log(`${response.data.messages.length} messages chargés`);
            
            // Initialiser si nécessaire
            if (!chatMessages.value[clientId]) {
                chatMessages.value[clientId] = [];
            }
            if (!currentPage.value[clientId]) {
                currentPage.value[clientId] = 1;
            }

            // Sauvegarder la position de défilement actuelle
            const container = chatContainer.value;
            const previousScrollHeight = container?.scrollHeight || 0;
            const previousScrollTop = container?.scrollTop || 0;

            // Mettre à jour les messages
            if (append) {
                // Ajouter au début pour les messages plus anciens
                chatMessages.value[clientId] = [...response.data.messages, ...chatMessages.value[clientId]];
            } else {
                chatMessages.value[clientId] = response.data.messages;
            }

            // Mettre à jour la pagination
            hasMoreMessages.value = response.data.messages.length >= messagesPerPage;
            currentPage.value[clientId] = page;

            // Attendre que le DOM soit mis à jour
            await nextTick();

            // Restaurer la position de défilement ou défiler vers le bas
            if (container) {
                if (append && previousScrollHeight > 0) {
                    const newScrollHeight = container.scrollHeight;
                    container.scrollTop = previousScrollTop + (newScrollHeight - previousScrollHeight);
                } else {
                    container.scrollTop = container.scrollHeight;
                }
            }
        } else {
            console.log("Aucun message trouvé");
            chatMessages.value[clientId] = [];
            hasMoreMessages.value = false;
        }
    } catch (error) {
        console.error("Erreur lors du chargement des messages:", error);
        console.error("Détails:", {
            status: error.response?.status,
            data: error.response?.data,
        });
    } finally {
        isLoadingMore.value = false;
    }
};

// Ajouter la fonction de chargement des messages plus anciens
const loadMoreMessages = async (clientId) => {
    if (!hasMoreMessages.value || isLoadingMore.value) return;
    
    const nextPage = (currentPage.value[clientId] || 1) + 1;
    const previousScrollHeight = chatContainer.value?.scrollHeight;
    const previousScrollTop = chatContainer.value?.scrollTop;
    
    await loadMessages(clientId, nextPage, true);

    // Maintenir la position de défilement après le chargement
    nextTick(() => {
        if (chatContainer.value && previousScrollHeight) {
            const newScrollHeight = chatContainer.value.scrollHeight;
            chatContainer.value.scrollTop = previousScrollTop + (newScrollHeight - previousScrollHeight);
        }
    });
};

// Ajouter la fonction de gestion du défilement
const handleScroll = async (event) => {
    const container = event.target;
    if (container.scrollTop <= 100 && selectedClient.value) { // Déclencher quand on est proche du haut
        await loadMoreMessages(selectedClient.value.id);
    }
};

// Configurer l'application et charger les données initiales
/* onMounted(async () => {
    try {
        // Configurer Axios en premier
        configureAxios();

        // Ajouter un intercepteur pour renouveler le token si nécessaire
        axios.interceptors.response.use(
            response => response,
            async error => {
                if (error.response?.status === 419) {
                    // Renouveler le token CSRF
                    await axios.get('/sanctum/csrf-cookie');
                    // Reconfigurer Axios avec le nouveau token
                    configureAxios();
                    // Réessayer la requête originale
                    const originalRequest = error.config;
                    return axios(originalRequest);
                }
                return Promise.reject(error);
            }
        );

        // Charger les données depuis l'API
        await loadAssignedData();

        // Configurer Laravel Echo pour les communications en temps réel
        if (window.Echo) {
            console.log(
                "Configuration de Laravel Echo pour recevoir les notifications en temps réel"
            );

            // Récupérer l'ID du modérateur depuis l'API
            const userResponse = await axios.get("/api/user");
            const moderatorId = userResponse.data.id;

            if (!moderatorId) {
                console.error("ID du modérateur non disponible");
                return;
            }

            console.log(`ID du modérateur connecté: ${moderatorId}`);

            // Écouter les notifications d'attribution de profil
            console.log(`Souscription au canal: moderator.${moderatorId}`);

            window.Echo.private(`moderator.${moderatorId}`)
                .listen(".profile.assigned", async (data) => {
                    console.log("Événement profile.assigned reçu:", data);
                    
                    // Recharger les données après l'attribution d'un profil
                    await loadAssignedData();

                    // Si le profil attribué est différent du profil actuel et qu'il est principal
                    if (data.profile && 
                        data.profile.id !== currentAssignedProfile.value?.id && 
                        data.is_primary) {
                        
                        currentAssignedProfile.value = data.profile;
                        
                        // Si un client est associé à ce changement de profil
                        if (data.client_id) {
                            try {
                                // Charger les messages du client
                                const clientResponse = await axios.get("/moderateur/messages", {
                                    params: {
                                        client_id: data.client_id,
                                        profile_id: data.profile.id,
                                    },
                                });

                                if (clientResponse.data.messages) {
                                    chatMessages.value[data.client_id] = clientResponse.data.messages;
                                    
                                    // Trouver et sélectionner le client
                                    const clientInfo = assignedClient.value.find(
                                        (c) => c.id === data.client_id
                                    );
                                    
                                    if (clientInfo) {
                                        selectedClient.value = clientInfo;
                                        
                                        // Faire défiler vers le bas du chat
                                        nextTick(() => {
                                            if (chatContainer.value) {
                                                chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
                                            }
                                        });
                                    }
                                }
                            } catch (error) {
                                console.error("Erreur lors du chargement des messages:", error);
                            }
                        }
                    }
                })
                .listen(".client.assigned", async (data) => {
                    console.log("Événement client.assigned reçu:", data);
                    // Recharger les données après l'attribution d'un client
                    await loadAssignedData();

                    // Si c'est un nouveau client et qu'il n'y a pas de client sélectionné,
                    // on le sélectionne automatiquement
                    if (!selectedClient.value && data.client) {
                        const clientInfo = assignedClient.value.find(
                            (c) => c.id === data.client.id
                        );
                        if (clientInfo) {
                            selectedClient.value = clientInfo;
                            await loadMessages(clientInfo.id);
                        }
                    }
                })
                .error((error) => {
                    console.error(
                        `Erreur sur le canal moderator.${moderatorId}:`,
                        error
                    );
                });

            // Si un profil est déjà attribué, écouter les messages sur son canal
            if (currentAssignedProfile.value) {
                listenToProfileMessages(currentAssignedProfile.value.id);
            }
        } else {
            console.error(
                "Laravel Echo n'est pas disponible, les notifications en temps réel ne fonctionneront pas"
            );
        }
    } catch (error) {
        console.error("Erreur lors de l'initialisation:", error);
    }
}); */

onMounted(async () => {
    try {
        console.log('Initialisation du composant modérateur...');

        // Attendre que l'authentification soit prête
        const isReady = await waitForAuthentication();
        if (!isReady) {
            console.error('Authentification non prête, rechargement de la page...');
            window.location.reload();
            return;
        }

        // Configurer Axios
        await configureAxios();

        // Configurer l'intercepteur
        setupAxiosInterceptor();

        // Petite pause pour s'assurer que tout est bien initialisé
        await new Promise(resolve => setTimeout(resolve, 200));

        // Charger les données depuis l'API
        await loadAssignedData();

        // Configurer Laravel Echo
        if (window.Echo) {
            console.log(
                "Configuration de Laravel Echo pour recevoir les notifications en temps réel"
            );

            // Récupérer l'ID du modérateur depuis l'API
            const userResponse = await axios.get("/api/user");
            const moderatorId = userResponse.data.id;

            if (!moderatorId) {
                console.error("ID du modérateur non disponible");
                return;
            }

            console.log(`ID du modérateur connecté: ${moderatorId}`);

            // Écouter les notifications d'attribution de profil
            console.log(`Souscription au canal: moderator.${moderatorId}`);

            window.Echo.private(`moderator.${moderatorId}`)
                .listen(".profile.assigned", async (data) => {
                    console.log("Événement profile.assigned reçu:", data);

                    // Recharger les données après l'attribution d'un profil
                    await loadAssignedData();

                    // Si le profil attribué est différent du profil actuel et qu'il est principal
                    if (data.profile &&
                        data.profile.id !== currentAssignedProfile.value?.id &&
                        data.is_primary) {

                        currentAssignedProfile.value = data.profile;

                        // Si un client est associé à ce changement de profil
                        if (data.client_id) {
                            try {
                                // Charger les messages du client
                                const clientResponse = await axios.get("/moderateur/messages", {
                                    params: {
                                        client_id: data.client_id,
                                        profile_id: data.profile.id,
                                    },
                                });

                                if (clientResponse.data.messages) {
                                    chatMessages.value[data.client_id] = clientResponse.data.messages;

                                    // Trouver et sélectionner le client
                                    const clientInfo = assignedClient.value.find(
                                        (c) => c.id === data.client_id
                                    );

                                    if (clientInfo) {
                                        selectedClient.value = clientInfo;

                                        // Faire défiler vers le bas du chat
                                        nextTick(() => {
                                            if (chatContainer.value) {
                                                chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
                                            }
                                        });
                                    }
                                }
                            } catch (error) {
                                console.error("Erreur lors du chargement des messages:", error);
                            }
                        }
                    }
                })
                .listen(".client.assigned", async (data) => {
                    console.log("Événement client.assigned reçu:", data);
                    // Recharger les données après l'attribution d'un client
                    await loadAssignedData();

                    // Si c'est un nouveau client et qu'il n'y a pas de client sélectionné,
                    // on le sélectionne automatiquement
                    if (!selectedClient.value && data.client) {
                        const clientInfo = assignedClient.value.find(
                            (c) => c.id === data.client.id
                        );
                        if (clientInfo) {
                            selectedClient.value = clientInfo;
                            await loadMessages(clientInfo.id);
                        }
                    }
                })
                .error((error) => {
                    console.error(
                        `Erreur sur le canal moderator.${moderatorId}:`,
                        error
                    );
                });

            // Si un profil est déjà attribué, écouter les messages sur son canal
            if (currentAssignedProfile.value) {
                listenToProfileMessages(currentAssignedProfile.value.id);
            }
        } else {
            console.error("Laravel Echo n'est pas disponible, les notifications en temps réel ne fonctionneront pas");
        }

        console.log('Initialisation du composant modérateur terminée');

    } catch (error) {
        console.error("Erreur lors de l'initialisation:", error);
        // En cas d'erreur, proposer de recharger
        if (confirm('Une erreur s\'est produite lors de l\'initialisation. Recharger la page ?')) {
            window.location.reload();
        }
    }
});
// Ajouter la fonction de gestion des notifications
const addNotification = (message, clientId, clientName) => {
    const notification = {
        id: Date.now(),
        message,
        clientId,
        clientName,
        timestamp: new Date(),
        read: false
    };
    notifications.value.unshift(notification);
    // Limiter à 50 notifications maximum
    if (notifications.value.length > 50) {
        notifications.value = notifications.value.slice(0, 50);
    }
};

// Modifier la fonction listenToProfileMessages pour ajouter la notification
const listenToProfileMessages = (profileId) => {
    console.log(`Écoute des messages pour le profil ${profileId}`);
    console.log(`Souscription au canal: profile.${profileId}`);

    // Désabonner des anciens listeners s'ils existent pour éviter les doublons
    if (window.Echo) {
        window.Echo.leave(`profile.${profileId}`);
    }

    window.Echo.private(`profile.${profileId}`)
        .listen(".message.sent", async (data) => {
            console.log("Nouveau message reçu sur le canal profile:", data);
            // Ajouter la notification
            if (data.is_from_client) {
                const clientId = data.client_id;

                // Vérifier si le message n'existe pas déjà
                if (chatMessages.value[clientId]?.some(msg => msg.id === data.id)) {
                    console.log("Message déjà existant, ignoré");
                    return;
                }

                // Ajouter la notification
                const clientName = assignedClient.value.find(c => c.id === clientId)?.name || 'Client';
                addNotification(data.content, clientId, clientName);

                // Formater le message
                const message = {
                    id: data.id,
                    content: data.content,
                    isFromClient: true,
                    time: new Date(data.created_at).toLocaleTimeString([], {
                        hour: "2-digit",
                        minute: "2-digit",
                    }),
                };

                // Initialiser le tableau de messages si nécessaire
                if (!chatMessages.value[clientId]) {
                    chatMessages.value[clientId] = [];
                }

                // Ajouter directement le nouveau message
                chatMessages.value[clientId].push(message);

                try {
                    // Mettre à jour la liste des clients en arrière-plan
                    const clientExists = assignedClient.value.some(c => c.id === clientId);
                    
                    if (!clientExists) {
                        await loadAssignedData();
                    } else {
                        // Mettre à jour le dernier message et le compteur dans la liste des clients
                        const clientIndex = assignedClient.value.findIndex(c => c.id === clientId);
                        if (clientIndex !== -1) {
                            assignedClient.value[clientIndex] = {
                                ...assignedClient.value[clientIndex],
                                lastMessage: message.content,
                                unreadCount: (assignedClient.value[clientIndex].unreadCount || 0) + 1,
                                createdAt: new Date().toISOString() // Mettre à jour la date pour le tri
                            };
                        }
                    }

                    // Faire défiler si c'est la conversation actuelle
                    if (selectedClient.value && selectedClient.value.id === clientId) {
                        nextTick(() => {
                            if (chatContainer.value) {
                                chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
                            }
                        });
                    }
                } catch (error) {
                    console.error("Erreur lors de la mise à jour des données:", error);
                }
            } else {
                console.log("Message ignoré car non provenant d'un client");
            }
        })
        .error((error) => {
            console.error(`Erreur sur le canal profile.${profileId}:`, error);
        });
};

// Nettoyer les listeners lors du démontage du composant
onUnmounted(() => {
    if (currentAssignedProfile.value && window.Echo) {
        window.Echo.leave(`profile.${currentAssignedProfile.value.id}`);
    }
});

// Modifier le watch sur currentAssignedProfile pour gérer automatiquement les changements de profil
watch(currentAssignedProfile, async (newProfile, oldProfile) => {
    if (newProfile && window.Echo) {
        listenToProfileMessages(newProfile.id);

        // Si le profil a changé, mettre à jour l'interface
        if (oldProfile && newProfile.id !== oldProfile.id) {
            // Récupérer les clients pour le nouveau profil
            await loadAssignedData();

            // Si nous avons des clients attribués, sélectionner automatiquement le plus récent
            if (assignedClient.value.length > 0) {
                const mostRecentClient = assignedClient.value[0];
                await selectClient(mostRecentClient);

                // Faire défiler vers le bas du chat
                nextTick(() => {
                    if (chatContainer.value) {
                        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
                    }
                });
            }
        }
    }
});

// Ajouter ces refs dans la section script
const fileInput = ref(null);
const selectedFile = ref(null);
const previewUrl = ref(null);
const showPreview = ref(false);
const previewImage = ref(null);

// Ajouter ces fonctions dans la section script
function handleFileUpload(event) {
    const file = event.target.files[0];
    if (file) {
        // Vérifier le type de fichier
        if (!file.type.startsWith('image/')) {
            alert('Seules les images sont autorisées');
            return;
        }
        
        // Vérifier la taille du fichier (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            alert('La taille du fichier ne doit pas dépasser 5MB');
            return;
        }

        selectedFile.value = file;
        previewUrl.value = URL.createObjectURL(file);
    }
}

function removeSelectedFile() {
    selectedFile.value = null;
    previewUrl.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function showImagePreview(attachment) {
    previewImage.value = attachment;
    showPreview.value = true;
}

function closeImagePreview() {
    showPreview.value = false;
    previewImage.value = null;
}

// Modifier la fonction sendMessage pour inclure explicitement le CSRF token
async function sendMessage() {
    if ((!newMessage.value.trim() && !selectedFile.value) || !currentAssignedProfile.value || !selectedClient.value)
        return;

    const formData = new FormData();
    formData.append('client_id', selectedClient.value.id);
    formData.append('profile_id', currentAssignedProfile.value.id);
    
    if (newMessage.value.trim()) {
        formData.append('content', newMessage.value);
    }
    if (selectedFile.value) {
        formData.append('attachment', selectedFile.value);
    }

    const now = new Date();
    const timeString = now.toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
    });

    // Créer le message local
    const localMessage = {
        id: "temp-" + Date.now(),
        content: newMessage.value,
        time: timeString,
        isFromClient: false,
        date: new Date().toISOString().split("T")[0],
    };

    // Si une image est sélectionnée, ajouter la prévisualisation
    if (selectedFile.value) {
        localMessage.attachment = {
            url: previewUrl.value,
            file_name: selectedFile.value.name,
            mime_type: selectedFile.value.type
        };
    }

    // Ajouter le message localement
    if (!chatMessages.value[selectedClient.value.id]) {
        chatMessages.value[selectedClient.value.id] = [];
    }
    chatMessages.value[selectedClient.value.id].push(localMessage);

    // Réinitialiser les champs
    newMessage.value = "";
    removeSelectedFile();

    try {
        const response = await axios.post("/moderateur/send-message", formData);

        if (response.data.success) {
            const index = chatMessages.value[selectedClient.value.id].findIndex(
                (msg) => msg.id === localMessage.id
            );
            if (index !== -1) {
                chatMessages.value[selectedClient.value.id][index] = response.data.messageData;
            }
        }
    } catch (error) {
        console.error("Erreur lors de l'envoi du message:", error);
        console.error("Détails de l'erreur:", {
            status: error.response?.status,
            data: error.response?.data,
            message: error.message,
            stack: error.stack
        });
        
        // Marquer le message comme échoué
        const index = chatMessages.value[selectedClient.value.id].findIndex(
            (msg) => msg.id === localMessage.id
        );
        if (index !== -1) {
            chatMessages.value[selectedClient.value.id][index].failed = true;
        }
    }

    // Faire défiler jusqu'au bas de la conversation
    nextTick(() => {
        if (chatContainer.value) {
            chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
        }
    });
}

// Mettre en forme le genre
function formatGender(gender) {
    const genders = {
        male: "Homme",
        female: "Femme",
        other: "Autre",
    };
    return genders[gender] || "Non spécifié";
}

// Surveiller les onglets pour recharger les données si nécessaire
watch(activeTab, (newTab) => {
    if (newTab === "available") {
        loadAvailableClients();
    }
});

// Surveiller les nouveaux messages et faire défiler vers le bas
watch(currentChatMessages, () => {
    nextTick(() => {
        if (chatContainer.value) {
            chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
        }
    });
});

// Ajouter cette fonction dans la partie script
function formatTime(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
}

// Ajouter la fonction pour marquer une notification comme lue
const markNotificationAsRead = (notificationId) => {
    const index = notifications.value.findIndex(n => n.id === notificationId);
    if (index !== -1) {
        notifications.value[index].read = true;
    }
};

// Ajouter la fonction pour naviguer vers une conversation depuis une notification
const goToConversation = (clientId) => {
    const client = assignedClient.value.find(c => c.id === clientId);
    if (client) {
        selectClient(client);
        activeTab.value = 'assigned';
    }
};

// Ajouter ces nouvelles refs
const showFullInfoModal = ref(false);

// Ajouter cette nouvelle fonction
function openFullInfoModal() {
    showFullInfoModal.value = true;
}

// Fonctions pour gérer les modals
const closeActionModal = () => {
    showActionModal.value = false;
    selectedProfileForActions.value = null;
};

const closeReportModal = () => {
    showReportModalFlag.value = false;
    selectedProfileForReport.value = null;
};

const startChat = (profile) => {
    // Implémenter la logique pour démarrer un chat
    closeActionModal();
};

const handleReported = () => {
    // Implémenter la logique après un rapport
    closeReportModal();
};
</script>

<style scoped>
.client-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1),
        0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.chat-container {
    height: 400px;
    overflow-y: auto;
}

.message-in {
    background-color: #f3f4f6;
    border-radius: 18px 18px 18px 4px;
}

.message-out {
    background-color: #ec4899;
    color: white;
    border-radius: 18px 18px 4px 18px;
}

.online-dot {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 12px;
    height: 12px;
    background-color: #10b981;
    border-radius: 50%;
    border: 2px solid white;
}

.message-in img, .message-out img {
    max-width: 200px;
    height: auto;
    border-radius: 8px;
    margin-top: 4px;
}

.message-in img:hover, .message-out img:hover {
    opacity: 0.9;
    cursor: zoom-in;
}

/* Ajustement des styles pour le mobile */
@media (max-width: 1024px) {
    .chat-container {
        height: calc(100vh - 20rem); /* Augmenté pour tenir compte du menu mobile */
    }
}

/* Ajout d'un style pour le conteneur principal sur mobile */
@media (max-width: 1024px) {
    .main-container {
        padding-bottom: env(safe-area-inset-bottom, 5rem);
    }
}

/* Styles pour le modal mobile */
.modal-mobile-enter-active,
.modal-mobile-leave-active {
    transition: transform 0.3s ease-out;
}

.modal-mobile-enter-from,
.modal-mobile-leave-to {
    transform: translateY(100%);
}

/* Style pour fixer la zone de saisie en bas */
.chat-input {
    position: sticky;
    bottom: 0;
    background: white;
    z-index: 10;
}

/* Support pour le safe area sur iOS */
@supports(padding: max(0px)) {
    .chat-input {
        padding-bottom: max(1rem, env(safe-area-inset-bottom));
    }
}

/* Slide transition for mobile drawer */
.slide-enter-active,
.slide-leave-active {
    transition: transform 0.3s ease-out;
}

.slide-enter-from,
.slide-leave-to {
    transform: translateY(-100%);
}
</style>
