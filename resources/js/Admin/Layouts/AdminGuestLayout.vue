<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-900">
        <div class="w-full sm:max-w-md px-6 py-4 bg-gray-800 shadow-md overflow-hidden sm:rounded-lg">
            <div class="mb-6 text-center">
                <h1 class="text-3xl font-bold text-white">{{ title }}</h1>
                <p class="text-gray-400 mt-2">{{ description }}</p>
            </div>
            
            <slot />
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: {
        type: String,
        default: 'Administration'
    },
    description: {
        type: String,
        default: 'Espace réservé aux administrateurs'
    }
});
</script> 