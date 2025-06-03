<template>
    <div class="relative">
        <!-- Notification Button -->
        <button 
            @click="toggleDropdown"
            class="relative p-2 text-gray-600 hover:text-gray-900 focus:outline-none"
        >
            <i class="fas fa-bell text-xl"></i>
            <span 
                v-if="unreadCount > 0"
                class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-500 rounded-full"
            >
                {{ unreadCount }}
            </span>
        </button>

        <!-- Dropdown -->
        <div 
            v-if="isOpen"
            class="absolute right-0 mt-2 w-96 bg-white rounded-lg shadow-xl z-50"
        >
            <div class="p-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Notifications</h3>
                    <button 
                        v-if="unreadCount > 0"
                        @click="markAllAsRead"
                        class="text-sm text-primary hover:text-primary-dark"
                    >
                        Tout marquer comme lu
                    </button>
                </div>
            </div>

            <div class="max-h-96 overflow-y-auto">
                <div v-if="notifications.length === 0" class="p-4 text-center text-gray-500">
                    Aucune notification
                </div>

                <div v-else>
                    <div 
                        v-for="notification in notifications" 
                        :key="notification.id"
                        :class="[
                            'p-4 hover:bg-gray-50 cursor-pointer border-b border-gray-100',
                            { 'bg-blue-50': !notification.read_at }
                        ]"
                        @click="handleNotificationClick(notification)"
                    >
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center">
                                    <i class="fas fa-flag text-red-600"></i>
                                </div>
                            </div>
                            <div class="ml-3 flex-1">
                                <p class="text-sm font-medium text-gray-900">
                                    Nouveau signalement de profil
                                </p>
                                <p class="text-sm text-gray-500">
                                    {{ notification.data.reporter_name }} a signalé le profil de {{ notification.data.reported_profile_name }}
                                </p>
                                <p class="mt-1 text-xs text-gray-400">
                                    {{ formatDate(notification.created_at) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 border-t border-gray-200">
                <Link 
                    :href="route('admin.reports.index')"
                    class="block w-full text-center text-sm text-primary hover:text-primary-dark"
                >
                    Voir tous les signalements
                </Link>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';

// Importer la fonction route depuis le global scope
const route = window.route;

const notifications = ref([]);
const unreadCount = ref(0);
const isOpen = ref(false);

// Charger les notifications
const loadNotifications = async () => {
    try {
        const response = await axios.get('/admin/notifications');
        notifications.value = response.data.notifications;
        unreadCount.value = response.data.unread_count;
    } catch (error) {
        console.error('Erreur lors du chargement des notifications:', error);
    }
};

// Marquer toutes les notifications comme lues
const markAllAsRead = async () => {
    try {
        await axios.post('/admin/notifications/mark-all-read');
        
        // Mettre à jour l'état local
        notifications.value = notifications.value.map(notification => ({
            ...notification,
            read_at: notification.read_at || new Date().toISOString()
        }));
        unreadCount.value = 0;
    } catch (error) {
        console.error('Erreur lors du marquage des notifications:', error);
    }
};

// Gérer le clic sur une notification
const handleNotificationClick = async (notification) => {
    try {
        // Marquer la notification comme lue si elle ne l'est pas déjà
        if (!notification.read_at) {
            await axios.post(`/admin/notifications/${notification.id}/mark-read`);
            
            // Mettre à jour l'état local de la notification
            const index = notifications.value.findIndex(n => n.id === notification.id);
            if (index !== -1) {
                notifications.value[index].read_at = new Date().toISOString();
                unreadCount.value = Math.max(0, unreadCount.value - 1);
            }
        }

        // Fermer le dropdown
        isOpen.value = false;

        // Rediriger vers la page appropriée en fonction du type de notification
        if (notification.type === 'App\\Notifications\\NewProfileReport') {
            router.visit(`/admin/reports/${notification.data.report_id}`);
        }
    } catch (error) {
        console.error('Erreur lors du traitement de la notification:', error);
    }
};

// Filtrer les notifications pour n'afficher que les non lues
const filteredNotifications = computed(() => {
    return notifications.value.filter(notification => !notification.read_at);
});

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

// Toggle dropdown
const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
};

// Écouter les nouvelles notifications via Echo
const setupEchoListeners = () => {
    if (window.Echo) {
        const userId = usePage().props.userId;
        if (userId) {
            window.Echo.private(`App.Models.User.${userId}`)
                .notification((notification) => {
                    if (notification.type === 'App\\Notifications\\NewProfileReport') {
                        notifications.value.unshift({
                            id: Date.now(),
                            type: notification.type,
                            data: notification.data,
                            created_at: new Date().toISOString(),
                            read_at: null
                        });
                        unreadCount.value++;
                    }
                });
        }
    }
};

// Lifecycle hooks
onMounted(() => {
    loadNotifications();
    setupEchoListeners();
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

// Fermer le dropdown quand on clique en dehors
const handleClickOutside = (event) => {
    if (isOpen.value && !event.target.closest('.relative')) {
        isOpen.value = false;
    }
};
</script>

<style scoped>
.max-h-96 {
    max-height: 24rem;
}
</style> 