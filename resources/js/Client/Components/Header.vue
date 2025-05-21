<template>
    <header class="gradient-bg text-white shadow-lg">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <i class="fas fa-heart text-2xl"></i>
                <h1 class="text-2xl font-bold">HeartMatch</h1>
            </div>
            <div class="flex items-center space-x-4">
                <!-- Si utilisateur connecté -->
                <template v-if="$page.props.auth.user">
                    <button class="p-2 rounded-full bg-white bg-opacity-20 hover:bg-opacity-30 transition">
                        <i class="fas fa-bell text-black"></i>
                    </button>
                    <div class="relative">
                        <img ref="profileRef" @click="toggleDropdown" src="https://randomuser.me/api/portraits/women/44.jpg" alt="Profile"
                            class="w-10 h-10 rounded-full object-cover border-2 border-white cursor-pointer" />
                        <div class="online-dot"></div>
                        
                        <!-- Profile Dropdown Menu -->
                        <div ref="dropdownRef" v-if="showDropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profil</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Paramètres</a>
                            <hr class="my-1">
                            <button @click="logout" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                Déconnexion
                            </button>
                        </div>
                    </div>
                    <!-- Ajout du composant de test Echo -->
                    <EchoTest />
                </template>
                <!-- Si utilisateur non connecté -->
                <template v-else>
                    <Link :href="route('login')" class="px-4 py-2 rounded-md bg-white text-pink-500 font-medium hover:bg-opacity-90 transition">
                        Connexion
                    </Link>
                </template>
            </div>
        </div>
    </header>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import EchoTest from '../../Components/EchoTest.vue';

const showDropdown = ref(false);
const dropdownRef = ref(null);
const profileRef = ref(null);
const page = usePage();

const toggleDropdown = (e) => {
    e.stopPropagation();
    showDropdown.value = !showDropdown.value;
};

// Close dropdown when clicking outside
const closeDropdown = (e) => {
    // Don't close if clicking on the profile image or inside the dropdown
    if (profileRef.value && profileRef.value.contains(e.target)) {
        return;
    }
    
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        showDropdown.value = false;
    }
};

// Add event listener when component is mounted
onMounted(() => {
    document.addEventListener('click', closeDropdown);
});

// Remove event listener when component is unmounted
onBeforeUnmount(() => {
    document.removeEventListener('click', closeDropdown);
});

// Logout function
const logout = () => {
    router.post(route('logout'));
};
</script>

<style scoped>
.gradient-bg {
    background: linear-gradient(135deg, #f9a8d4 0%, #f472b6 100%);
}

.online-dot {
    width: 12px;
    height: 12px;
    background-color: #10b981;
    border-radius: 50%;
    position: absolute;
    bottom: 0;
    right: 0;
    border: 2px solid white;
}
</style>
