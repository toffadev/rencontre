<template>
    <Transition name="modal">
        <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" @click="closeOnBackdrop">
            <div class="flex min-h-screen items-center justify-center p-4 text-center">
                <div class="fixed inset-0 bg-black opacity-30"></div>

                <div class="relative z-20 w-full max-w-lg transform overflow-hidden rounded-lg bg-white p-6 text-left shadow-xl transition-all">
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">
                            <slot name="title">{{ title }}</slot>
                        </h3>
                        <button
                            @click="$emit('close')"
                            class="text-gray-400 hover:text-gray-500"
                        >
                            <span class="sr-only">Fermer</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Content -->
                    <div class="mt-2">
                        <slot></slot>
                    </div>

                    <!-- Footer -->
                    <div v-if="$slots.footer" class="mt-4 flex justify-end space-x-2">
                        <slot name="footer"></slot>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    title: {
        type: String,
        default: ''
    },
    closeOnClickOutside: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits(['close']);

const closeOnBackdrop = (event) => {
    // Si on clique sur le backdrop et que closeOnClickOutside est true
    if (event.target === event.currentTarget && props.closeOnClickOutside) {
        emit('close');
    }
};
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style> 