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
                    <div class="relative group">
                        <div class="flex items-center space-x-3 cursor-pointer" @click="toggleDropdown" ref="profileRef">
                            <div class="flex flex-col items-end">
                                <span class="text-sm font-medium">{{ $page.props.auth.user.name }}</span>
                                <span class="text-xs text-gray-200">En ligne</span>
                            </div>
                            <div class="relative">
                                <div v-if="!$page.props.auth.user.profile_photo_url" 
                                    class="w-10 h-10 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                                    <i class="fas fa-user text-white text-xl"></i>
                                </div>
                                <img v-else
                                    :src="$page.props.auth.user.profile_photo_url"
                                    :alt="$page.props.auth.user.name"
                                    class="w-10 h-10 rounded-full object-cover border-2 border-white"
                                />
                                <div class="online-dot"></div>
                            </div>
                        </div>
                        
                        <!-- Profile Dropdown Menu -->
                        <div ref="dropdownRef" 
                            v-if="showDropdown" 
                            class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 z-50 transform transition-all duration-200 ease-out"
                        >
                            <div class="px-4 py-2 border-b border-gray-100">
                                <p class="text-sm font-medium text-gray-900">{{ $page.props.auth.user.name }}</p>
                                <p class="text-xs text-gray-500">{{ $page.props.auth.user.email }}</p>
                            </div>
                            <Link :href="route('profile')" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition">
                                <i class="fas fa-user-circle w-5 text-gray-400"></i>
                                <span>Profil</span>
                            </Link>
                            
                            <hr class="my-1">
                            <button @click="logout" class="w-full flex items-center px-4 py-2 text-sm text-red-600 hover:bg-gray-50 transition">
                                <i class="fas fa-sign-out-alt w-5"></i>
                                <span>Déconnexion</span>
                            </button>
                        </div>
                    </div>
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
import EchoTest from './EchoTest.vue';
import axios from 'axios';

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
    if (profileRef.value && profileRef.value.contains(e.target)) {
        return;
    }
    
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        showDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', closeDropdown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', closeDropdown);
});

const logout = async () => {
    try {
        // Récupérer d'abord le jeton CSRF actuel du serveur
        const response = await axios.get('/auth/check');
        const serverToken = response.data.csrf_token;

        // Créer un formulaire dynamiquement
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = route('logout');

        // Ajouter le token CSRF du serveur
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = serverToken;

        // Ajouter au DOM et soumettre
        form.appendChild(csrfInput);
        document.body.appendChild(form);
        form.submit();
    } catch (error) {
        console.error('Erreur lors de la déconnexion:', error);
        // Redirection de secours
        window.location.href = route('login');
    }
};
</script>

<style scoped>
.online-dot {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 10px;
    height: 10px;
    background-color: #10B981;
    border-radius: 50%;
    border: 2px solid white;
}

.gradient-bg {
    background: linear-gradient(to right, #EC4899, #D946EF);
}
</style>
