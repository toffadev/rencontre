<template>
    <div class="flex items-center justify-between bg-white px-4 py-3 sm:px-6">
        <div class="flex flex-1 justify-between sm:hidden">
            <button v-if="hasPrevPage" @click="$emit('navigate', currentPage - 1)"
                class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Précédent
            </button>
            <button v-if="hasNextPage" @click="$emit('navigate', currentPage + 1)"
                class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Suivant
            </button>
        </div>
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-700">
                    Affichage de
                    <span class="font-medium">{{ from }}</span>
                    à
                    <span class="font-medium">{{ to }}</span>
                    sur
                    <span class="font-medium">{{ total }}</span>
                    résultats
                </p>
            </div>
            <div>
                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                    <button v-if="hasPrevPage" @click="$emit('navigate', currentPage - 1)"
                        class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                        <span class="sr-only">Précédent</span>
                        <i class="fas fa-chevron-left h-5 w-5"></i>
                    </button>

                    <template v-for="page in visiblePages" :key="page">
                        <button v-if="page === '...'"
                            class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 focus:outline-offset-0">
                            ...
                        </button>
                        <button v-else @click="$emit('navigate', page)" :class="[
                            'relative inline-flex items-center px-4 py-2 text-sm font-semibold focus:z-20',
                            currentPage === page
                                ? 'bg-pink-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pink-600'
                                : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:outline-offset-0'
                        ]">
                            {{ page }}
                        </button>
                    </template>

                    <button v-if="hasNextPage" @click="$emit('navigate', currentPage + 1)"
                        class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                        <span class="sr-only">Suivant</span>
                        <i class="fas fa-chevron-right h-5 w-5"></i>
                    </button>
                </nav>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    currentPage: {
        type: Number,
        required: true
    },
    lastPage: {
        type: Number,
        required: true
    },
    from: {
        type: Number,
        required: true
    },
    to: {
        type: Number,
        required: true
    },
    total: {
        type: Number,
        required: true
    }
})

const hasPrevPage = computed(() => props.currentPage > 1)
const hasNextPage = computed(() => props.currentPage < props.lastPage)

const visiblePages = computed(() => {
    const pages = []
    const totalVisible = 7
    const edge = 2

    if (props.lastPage <= totalVisible) {
        for (let i = 1; i <= props.lastPage; i++) {
            pages.push(i)
        }
    } else {
        // Toujours montrer les premières pages
        for (let i = 1; i <= edge; i++) {
            pages.push(i)
        }

        // Calculer les pages du milieu
        const leftBound = Math.max(edge + 1, props.currentPage - 1)
        const rightBound = Math.min(props.lastPage - edge, props.currentPage + 1)

        if (leftBound > edge + 1) {
            pages.push('...')
        }
        for (let i = leftBound; i <= rightBound; i++) {
            pages.push(i)
        }
        if (rightBound < props.lastPage - edge) {
            pages.push('...')
        }

        // Toujours montrer les dernières pages
        for (let i = props.lastPage - edge + 1; i <= props.lastPage; i++) {
            pages.push(i)
        }
    }

    return pages
})
</script>