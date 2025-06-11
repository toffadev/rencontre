<template>
    <div class="px-6 py-4 bg-white border-t border-gray-200">
        <div class="flex items-center justify-between">
            <!-- Mobile version -->
            <div class="flex-1 flex justify-between sm:hidden">
                <button
                    @click="$emit('page-change', currentPage - 1)"
                    :disabled="currentPage === 1"
                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Précédent
                </button>
                <button
                    @click="$emit('page-change', currentPage + 1)"
                    :disabled="currentPage === totalPages"
                    class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Suivant
                </button>
            </div>

            <!-- Desktop version -->
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        Affichage de
                        <span class="font-medium">{{ (currentPage - 1) * perPage + 1 }}</span>
                        à
                        <span class="font-medium">{{ Math.min(currentPage * perPage, total) }}</span>
                        sur
                        <span class="font-medium">{{ total }}</span>
                        résultats
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <!-- Previous button -->
                        <button
                            @click="$emit('page-change', currentPage - 1)"
                            :disabled="currentPage === 1"
                            class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span class="sr-only">Précédent</span>
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        
                        <!-- Page numbers -->
                        <template v-for="page in displayedPages" :key="page">
                            <button
                                v-if="page !== '...'"
                                @click="$emit('page-change', page)"
                                :class="[
                                    'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                                    currentPage === page
                                        ? 'z-10 bg-pink-50 border-pink-500 text-pink-600'
                                        : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                                ]"
                            >
                                {{ page }}
                            </button>
                            <span
                                v-else
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700"
                            >
                                ...
                            </span>
                        </template>

                        <!-- Next button -->
                        <button
                            @click="$emit('page-change', currentPage + 1)"
                            :disabled="currentPage === totalPages"
                            class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span class="sr-only">Suivant</span>
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    currentPage: {
        type: Number,
        required: true
    },
    totalPages: {
        type: Number,
        required: true
    },
    perPage: {
        type: Number,
        required: true
    },
    total: {
        type: Number,
        required: true
    }
});

const displayedPages = computed(() => {
    const pages = [];
    const maxDisplayed = 5;
    
    if (props.totalPages <= maxDisplayed) {
        // Si le nombre total de pages est inférieur ou égal au maximum à afficher,
        // afficher toutes les pages
        for (let i = 1; i <= props.totalPages; i++) {
            pages.push(i);
        }
    } else {
        // Toujours afficher la première page
        pages.push(1);
        
        if (props.currentPage > 3) {
            pages.push('...');
        }
        
        // Pages autour de la page courante
        for (let i = Math.max(2, props.currentPage - 1); i <= Math.min(props.totalPages - 1, props.currentPage + 1); i++) {
            pages.push(i);
        }
        
        if (props.currentPage < props.totalPages - 2) {
            pages.push('...');
        }
        
        // Toujours afficher la dernière page
        pages.push(props.totalPages);
    }
    
    return pages;
});

defineEmits(['page-change']);
</script> 