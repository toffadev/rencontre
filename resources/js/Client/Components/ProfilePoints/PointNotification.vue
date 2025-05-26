<template>
    <div
        v-if="showNotification"
        class="fixed bottom-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg shadow-lg z-50 flex items-center"
    >
        <div class="mr-3">
            <i class="fas fa-coins text-xl"></i>
        </div>
        <div>
            <p class="font-bold">Points reçus !</p>
            <p class="text-sm">
                {{ notificationMessage }}
            </p>
        </div>
        <button
            @click="showNotification = false"
            class="ml-4 text-green-700 hover:text-green-800"
        >
            <i class="fas fa-times"></i>
        </button>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import Echo from "laravel-echo";

const props = defineProps({
    userId: {
        type: Number,
        required: true,
    },
    userType: {
        type: String,
        required: true,
        validator: (value) => ["client", "moderator"].includes(value),
    },
});

const showNotification = ref(false);
const notificationMessage = ref("");
let notificationTimeout;

const handlePointsReceived = (data) => {
    notificationMessage.value = `Vous avez reçu ${data.points} points !`;
    showNotification.value = true;

    // Cacher la notification après 5 secondes
    clearTimeout(notificationTimeout);
    notificationTimeout = setTimeout(() => {
        showNotification.value = false;
    }, 5000);
};

onMounted(() => {
    const channel =
        props.userType === "client"
            ? `client.${props.userId}`
            : `moderator.${props.userId}`;

    if (window.Echo) {
        window.Echo.private(channel).listen(
            ".points.received",
            handlePointsReceived
        );
    }
});

onUnmounted(() => {
    clearTimeout(notificationTimeout);

    // Nettoyage des écouteurs d'événements
    if (window.Echo) {
        const channel =
            props.userType === "client"
                ? `client.${props.userId}`
                : `moderator.${props.userId}`;
        window.Echo.leave(channel);
    }
});
</script>
