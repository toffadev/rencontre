<template>
    <div class="w-full bg-white rounded-xl shadow-md p-4 mb-6">
        <!-- Header avec filtres -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <h2 class="text-xl font-semibold text-gray-800">
                <span class="text-pink-500">{{ filteredProfiles.length }}</span> profils à découvrir
            </h2>
            <div class="flex flex-wrap items-center gap-4">
                <!-- Filtres -->
                <div class="flex items-center space-x-2">
                    <select v-model="selectedFilter"
                        class="bg-pink-50 text-pink-600 rounded-lg px-3 py-1.5 border-none focus:ring-2 focus:ring-pink-500 transition-all duration-200">
                        <option value="all">Tous les profils</option>
                        <option value="online">En ligne</option>
                        <option value="new">Nouveaux</option>
                        <option value="popular">Populaires</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Mobile Carousel -->
        <div class="block lg:hidden mb-6">
            <div class="relative">
                <!-- Swipe Container -->
                <div class="overflow-x-auto scrollbar-hide snap-x snap-mandatory flex space-x-4 pb-4"
                    ref="mobileCarousel" @touchstart="handleTouchStart" @touchmove="handleTouchMove"
                    @touchend="handleTouchEnd">
                    <div v-for="profile in filteredProfiles" :key="profile.id"
                        class="snap-start flex-shrink-0 w-48 relative group cursor-pointer transform transition-all duration-300 hover:scale-105"
                        @click="showProfileActions(profile)">
                        <!-- Card Container -->
                        <div class="relative rounded-2xl overflow-hidden shadow-lg bg-white h-72">
                            <!-- Image principale -->
                            <img :src="profile.main_photo_path || 'https://via.placeholder.com/192'" :alt="profile.name"
                                class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110" />

                            <!-- Overlay gradient -->
                            <div
                                class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent opacity-80">
                            </div>

                            <!-- Badges -->
                            <div class="absolute top-3 left-3 flex flex-col gap-2">
                                <div v-if="isOnline(profile)"
                                    class="bg-green-500 text-white text-xs px-3 py-1 rounded-full flex items-center">
                                    <span class="w-2 h-2 bg-white rounded-full mr-1"></span>
                                    En ligne
                                </div>
                                <div v-if="isNewProfile(profile)"
                                    class="bg-pink-500 text-white text-xs px-3 py-1 rounded-full">
                                    Nouveau
                                </div>
                            </div>

                            <!-- Informations -->
                            <div class="absolute bottom-0 left-0 right-0 p-4">
                                <h3 class="text-white font-semibold text-lg mb-1">{{ profile.name }}</h3>
                                <div class="flex items-center space-x-2 text-white/90 text-sm">
                                    <span>{{ formatGender(profile.gender) }}</span>
                                    <span>•</span>
                                    <span>{{ formatLocation(profile) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Arrows -->
                <button @click="scrollMobile('left')"
                    class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-4 w-8 h-8 bg-white rounded-full shadow-lg flex items-center justify-center text-pink-500 hover:bg-pink-50 transition-all duration-200"
                    v-show="canScrollLeft">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button @click="scrollMobile('right')"
                    class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-4 w-8 h-8 bg-white rounded-full shadow-lg flex items-center justify-center text-pink-500 hover:bg-pink-50 transition-all duration-200"
                    v-show="canScrollRight">
                    <i class="fas fa-chevron-right"></i>
                </button>

                <!-- Scroll Indicator -->
                <div class="absolute -bottom-4 left-1/2 -translate-x-1/2 flex space-x-1">
                    <div v-for="(_, index) in scrollIndicators" :key="index"
                        class="w-1.5 h-1.5 rounded-full transition-all duration-200"
                        :class="currentScrollIndex === index ? 'bg-pink-500 w-3' : 'bg-gray-300'"></div>
                </div>
            </div>
        </div>

        <!-- Desktop Grid -->
        <div class="hidden lg:block">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-6">
                <div v-for="profile in currentPageProfiles" :key="profile.id"
                    class="group cursor-pointer relative overflow-hidden rounded-2xl shadow-lg transform transition-all duration-300 hover:-translate-y-2 hover:shadow-xl"
                    @click="showProfileActions(profile)">
                    <!-- Card Container -->
                    <div class="aspect-[3/4] relative">
                        <!-- Image principale avec effet de zoom -->
                        <img :src="profile.main_photo_path || 'https://via.placeholder.com/192'" :alt="profile.name"
                            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" />

                        <!-- Overlay gradient -->
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent opacity-0 group-hover:opacity-80 transition-opacity duration-300">
                        </div>

                        <!-- Badges -->
                        <div class="absolute top-3 left-3 flex flex-col gap-2">
                            <div v-if="isOnline(profile)"
                                class="bg-green-500 text-white text-xs px-3 py-1 rounded-full flex items-center">
                                <span class="w-2 h-2 bg-white rounded-full mr-1"></span>
                                En ligne
                            </div>
                            <div v-if="isNewProfile(profile)"
                                class="bg-pink-500 text-white text-xs px-3 py-1 rounded-full">
                                Nouveau
                            </div>
                        </div>

                        <!-- Informations avec animation -->
                        <div
                            class="absolute bottom-0 left-0 right-0 p-4 transform translate-y-full group-hover:translate-y-0 transition-transform duration-300">
                            <h3 class="text-white font-semibold text-lg mb-1">{{ profile.name }}</h3>
                            <div class="flex items-center space-x-2 text-white/90 text-sm">
                                <span>{{ formatGender(profile.gender) }}</span>
                                <span>•</span>
                                <span>{{ formatLocation(profile) }}</span>
                            </div>
                            <div class="flex items-center mt-2 space-x-2">
                                <button
                                    class="bg-pink-500 text-white text-sm px-4 py-1 rounded-full hover:bg-pink-600 transition-colors duration-200">
                                    Discuter
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="mt-8 flex justify-center">
                <div class="flex items-center space-x-2">
                    <button @click="previousPage"
                        class="w-10 h-10 rounded-full flex items-center justify-center transition-all duration-200"
                        :class="currentPage === 1 ? 'text-gray-400 cursor-not-allowed' : 'text-pink-500 hover:bg-pink-50'"
                        :disabled="currentPage === 1">
                        <i class="fas fa-chevron-left"></i>
                    </button>

                    <div v-for="page in displayedPages" :key="page" class="relative">
                        <button @click="goToPage(page)"
                            class="w-10 h-10 rounded-full flex items-center justify-center transition-all duration-200"
                            :class="page === currentPage ? 'bg-pink-500 text-white' : 'text-gray-600 hover:bg-pink-50'">
                            {{ page }}
                        </button>
                    </div>

                    <button @click="nextPage"
                        class="w-10 h-10 rounded-full flex items-center justify-center transition-all duration-200"
                        :class="currentPage === totalPages ? 'text-gray-400 cursor-not-allowed' : 'text-pink-500 hover:bg-pink-50'"
                        :disabled="currentPage === totalPages">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    profiles: {
        type: Array,
        required: true
    }
});

const emit = defineEmits(['showActions']);

// Pagination et filtres
const currentPage = ref(1);
const itemsPerPage = 18; // 3 rows of 6 items
const selectedFilter = ref('all');
const mobileCarousel = ref(null);
const touchStartX = ref(null);
const touchEndX = ref(null);
const scrollLeft = ref(0);
const canScrollLeft = ref(false);
const canScrollRight = ref(false);
const currentScrollIndex = ref(0);

// Nombre d'indicateurs de défilement (mobile)
const scrollIndicators = computed(() => {
    if (!mobileCarousel.value) return [];

    const totalWidth = mobileCarousel.value.scrollWidth || 0;
    const viewWidth = mobileCarousel.value.clientWidth || 0;

    if (totalWidth <= viewWidth) return [0];

    const count = Math.ceil(totalWidth / viewWidth);
    return count > 0 ? Array.from({ length: count }).map((_, i) => i) : [0];
});

// Filtrer les profils
const filteredProfiles = computed(() => {
    let filtered = [...props.profiles];

    switch (selectedFilter.value) {
        case 'online':
            filtered = filtered.filter(p => isOnline(p));
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

// Gestion du carrousel mobile
function handleTouchStart(e) {
    touchStartX.value = e.touches[0].clientX;
    scrollLeft.value = mobileCarousel.value.scrollLeft;
}

function handleTouchMove(e) {
    if (!touchStartX.value) return;

    const x = e.touches[0].clientX;
    const walk = (touchStartX.value - x) * 2;
    mobileCarousel.value.scrollLeft = scrollLeft.value + walk;
}

function handleTouchEnd() {
    touchStartX.value = null;
    updateScrollButtons();
}

function scrollMobile(direction) {
    const container = mobileCarousel.value;
    const scrollAmount = container.clientWidth;

    if (direction === 'left') {
        container.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    } else {
        container.scrollBy({ left: scrollAmount, behavior: 'smooth' });
    }
}

function updateScrollButtons() {
    if (!mobileCarousel.value) return;

    const container = mobileCarousel.value;
    canScrollLeft.value = container.scrollLeft > 0;
    canScrollRight.value = container.scrollLeft < (container.scrollWidth - container.clientWidth);

    // Update scroll indicator
    const scrollPercentage = container.scrollLeft / (container.scrollWidth - container.clientWidth);
    currentScrollIndex.value = Math.round(scrollPercentage * (scrollIndicators.value.length - 1));
}

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

// Vérifier si un profil est en ligne
function isOnline(profile) {
    return true; // À implémenter selon votre logique
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

// Scroll event listener for mobile carousel
function handleScroll() {
    updateScrollButtons();
}

// Lifecycle hooks
onMounted(() => {
    if (mobileCarousel.value) {
        mobileCarousel.value.addEventListener('scroll', handleScroll);
        updateScrollButtons();
    }
});

onUnmounted(() => {
    if (mobileCarousel.value) {
        mobileCarousel.value.removeEventListener('scroll', handleScroll);
    }
});

// Reset page when filter changes
watch(selectedFilter, () => {
    currentPage.value = 1;
});
</script>

<style scoped>
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

.snap-x {
    scroll-snap-type: x mandatory;
}

.snap-start {
    scroll-snap-align: start;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.grid>div {
    animation: fadeIn 0.5s ease-out;
    animation-fill-mode: both;
}

.grid>div:nth-child(1) {
    animation-delay: 0.1s;
}

.grid>div:nth-child(2) {
    animation-delay: 0.2s;
}

.grid>div:nth-child(3) {
    animation-delay: 0.3s;
}

.grid>div:nth-child(4) {
    animation-delay: 0.4s;
}

.grid>div:nth-child(5) {
    animation-delay: 0.5s;
}

.grid>div:nth-child(6) {
    animation-delay: 0.6s;
}
</style>