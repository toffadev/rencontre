<template>
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">
            Statistiques de la conversation
        </h2>

        <!-- Messages Stats -->
        <div class="space-y-6">
            <div>
                <h3 class="text-sm font-medium text-gray-500 mb-2">Messages</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-gray-50 rounded-lg p-3 text-center">
                        <div class="text-2xl font-bold text-gray-900">
                            {{ stats.total_messages }}
                        </div>
                        <div class="text-xs text-gray-500">Total</div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 text-center">
                        <div class="text-2xl font-bold text-blue-600">
                            {{ stats.client_messages }}
                        </div>
                        <div class="text-xs text-gray-500">Client</div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 text-center">
                        <div class="text-2xl font-bold text-pink-600">
                            {{ stats.profile_messages }}
                        </div>
                        <div class="text-xs text-gray-500">Profil</div>
                    </div>
                </div>
            </div>

            <!-- Engagement Stats -->
            <div>
                <h3 class="text-sm font-medium text-gray-500 mb-2">Engagement</h3>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-gray-600">Taux de réponse</span>
                        <span class="text-sm font-medium text-gray-900">
                            {{ stats.engagement_rate }}%
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div 
                            class="bg-pink-500 h-2 rounded-full" 
                            :style="{ width: `${stats.engagement_rate}%` }"
                        ></div>
                    </div>
                </div>
            </div>

            <!-- Time Stats -->
            <div>
                <h3 class="text-sm font-medium text-gray-500 mb-2">Temps</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Durée totale</span>
                        <span class="text-sm font-medium text-gray-900">
                            {{ stats.conversation_duration }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Temps de réponse moyen</span>
                        <span class="text-sm font-medium text-gray-900">
                            {{ formatResponseTime(stats.average_response_time) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Premier message</span>
                        <span class="text-sm font-medium text-gray-900">
                            {{ formatDate(stats.first_message_at) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Dernier message</span>
                        <span class="text-sm font-medium text-gray-900">
                            {{ formatDate(stats.last_message_at) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Points Stats -->
            <div>
                <h3 class="text-sm font-medium text-gray-500 mb-2">Points</h3>
                <div class="bg-gray-50 rounded-lg p-4 flex items-center justify-between">
                    <span class="text-sm text-gray-600">Points dépensés</span>
                    <span class="text-lg font-bold text-pink-600">
                        {{ stats.points_spent }}
                        <i class="fas fa-coins ml-1 text-sm"></i>
                    </span>
                </div>
            </div>

            <!-- Moderator Stats -->
            <div>
                <h3 class="text-sm font-medium text-gray-500 mb-2">Modération</h3>
                <div class="bg-gray-50 rounded-lg p-4 flex items-center justify-between">
                    <span class="text-sm text-gray-600">Modérateurs impliqués</span>
                    <span class="text-lg font-bold text-gray-900">
                        {{ stats.moderators_involved }}
                        <i class="fas fa-user-shield ml-1 text-sm"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    stats: {
        type: Object,
        required: true
    }
});

const formatResponseTime = (seconds) => {
    if (seconds < 60) {
        return `${seconds} secondes`;
    } else if (seconds < 3600) {
        return `${Math.round(seconds / 60)} minutes`;
    } else {
        return `${Math.round(seconds / 3600)} heures`;
    }
};

const formatDate = (timestamp) => {
    return new Date(timestamp).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script> 