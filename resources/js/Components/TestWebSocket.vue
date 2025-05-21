<template>
    <div class="p-4">
        <h2 class="text-xl font-bold mb-4">Test WebSocket</h2>
        <div v-if="message" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
            {{ message }}
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const message = ref('');

onMounted(() => {
    window.Echo.channel('test-channel')
        .listen('TestEvent', (e) => {
            message.value = e.message;
        });
});
</script> 