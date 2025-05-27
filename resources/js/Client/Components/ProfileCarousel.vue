<template>
    <div class="w-full bg-white rounded-xl shadow-md p-4 mb-6">
        <!-- Header avec filtres -->
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">Découvrir des profils</h2>
            <div class="flex items-center space-x-4">
                <!-- Filtres -->
                <div class="flex items-center space-x-2">
                    <select 
                        v-model="selectedFilter"
                        class="bg-pink-50 text-pink-600 rounded-lg px-3 py-1.5 border-none focus:ring-2 focus:ring-pink-500"
                    >
                        <option value="all">Tous les profils</option>
                        <option value="online">En ligne</option>
                        <option value="new">Nouveaux</option>
                        <option value="popular">Populaires</option>
                    </select>
                </div>
                <!-- Pagination info -->
                <div class="text-sm text-gray-500">
                    Page {{ currentPage }} sur {{ totalPages }}
                </div>
            </div>
        </div>
        
        <!-- Grid Container -->
        <div class="mb-4">
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
                <div 
                    v-for="profile in currentPageProfiles" 
                    :key="profile.id"
                    class="cursor-pointer transform transition hover:scale-105"
                    @click="showProfileActions(profile)"
                >
                    <div class="relative rounded-lg overflow-hidden shadow-md bg-white aspect-square">
                        <img 
                            :src="profile.main_photo_path || 'https://via.placeholder.com/192'" 
                            :alt="profile.name"
                            class="w-full h-full object-cover"
                        />
                        <!-- Online indicator -->
                        <div 
                            v-if="true"
                            class="absolute top-2 right-2 bg-green-500 text-white text-xs px-2 py-1 rounded-full"
                        >
                            En ligne
                        </div>
                        <!-- New badge -->
                        <div 
                            v-if="isNewProfile(profile)"
                            class="absolute top-2 left-2 bg-pink-500 text-white text-xs px-2 py-1 rounded-full"
                        >
                            Nouveau
                        </div>
                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-3">
                            <h3 class="text-white font-semibold text-sm">{{ profile.name }}</h3>
                            <div class="flex items-center space-x-2">
                                <span class="text-white/90 text-xs">{{ formatGender(profile.gender) }}</span>
                                <span class="text-white/90 text-xs">•</span>
                                <span class="text-white/90 text-xs">{{ formatLocation(profile) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination Controls -->
        <div class="flex justify-between items-center">
            <button 
                @click="previousPage"
                class="px-4 py-2 bg-pink-100 text-pink-600 rounded-lg hover:bg-pink-200 transition disabled:opacity-50"
                :disabled="currentPage === 1"
            >
                <i class="fas fa-chevron-left mr-2"></i>
                Précédent
            </button>

            <!-- Page numbers -->
            <div class="flex items-center space-x-2">
                <button 
                    v-for="page in displayedPages" 
                    :key="page"
                    @click="goToPage(page)"
                    class="w-8 h-8 rounded-full flex items-center justify-center transition"
                    :class="page === currentPage ? 'bg-pink-500 text-white' : 'bg-pink-100 text-pink-600 hover:bg-pink-200'"
                >
                    {{ page }}
                </button>
            </div>

            <button 
                @click="nextPage"
                class="px-4 py-2 bg-pink-100 text-pink-600 rounded-lg hover:bg-pink-200 transition disabled:opacity-50"
                :disabled="currentPage === totalPages"
            >
                Suivant
                <i class="fas fa-chevron-right ml-2"></i>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    profiles: {
        type: Array,
        required: true
    }
});

const emit = defineEmits(['showActions']);

// Pagination
const currentPage = ref(1);
const itemsPerPage = 12; // 2 rows of 6 items
const selectedFilter = ref('all');

// Filtrer les profils
const filteredProfiles = computed(() => {
    let filtered = [...props.profiles];
    
    switch (selectedFilter.value) {
        case 'online':
            filtered = filtered;
            break;
        case 'new':
            filtered = filtered.filter(p => isNewProfile(p));
            break;
        case 'popular':
            filtered = filtered.sort((a, b) => (b.popularity || 0) - (a.popularity || 0));
            break;
    }
    
    return filtered;
});

// Calculer les profils pour la page courante
const currentPageProfiles = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredProfiles.value.slice(start, start + itemsPerPage);
});

// Calculer le nombre total de pages
const totalPages = computed(() => {
    return Math.ceil(filteredProfiles.value.length / itemsPerPage);
});

// Calculer les numéros de page à afficher
const displayedPages = computed(() => {
    const pages = [];
    const maxDisplayed = 5;
    
    if (totalPages.value <= maxDisplayed) {
        for (let i = 1; i <= totalPages.value; i++) {
            pages.push(i);
        }
    } else {
        let start = Math.max(1, currentPage.value - 2);
        let end = Math.min(totalPages.value, start + maxDisplayed - 1);
        
        if (end - start < maxDisplayed - 1) {
            start = Math.max(1, end - maxDisplayed + 1);
        }
        
        for (let i = start; i <= end; i++) {
            pages.push(i);
        }
    }
    
    return pages;
});

// Navigation
function previousPage() {
    if (currentPage.value > 1) {
        currentPage.value--;
    }
}

function nextPage() {
    if (currentPage.value < totalPages.value) {
        currentPage.value++;
    }
}

function goToPage(page) {
    currentPage.value = page;
}

// Vérifier si un profil est nouveau (moins de 7 jours)
function isNewProfile(profile) {
    if (!profile.created_at) return false;
    const createdDate = new Date(profile.created_at);
    const now = new Date();
    const diffTime = Math.abs(now - createdDate);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays <= 7;
}

function showProfileActions(profile) {
    emit('showActions', profile);
}

function formatGender(gender) {
    const genders = {
        male: "Homme",
        female: "Femme",
        other: "Autre"
    };
    return genders[gender] || "Non spécifié";
}

function formatLocation(profile) {
    return "À proximité";
}

// Reset page when filter changes
watch(selectedFilter, () => {
    currentPage.value = 1;
});
</script>

<style scoped>
.aspect-square {
    aspect-ratio: 1 / 1;
}
</style> 