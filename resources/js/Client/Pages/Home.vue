<template>
    <MainLayout>
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Profiles Section -->
            <div class="w-full lg:w-1/3 bg-white rounded-xl shadow-md overflow-hidden p-4">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">Profils à proximité</h2>
                    <div class="flex space-x-2">
                        <button class="p-2 rounded-full bg-pink-100 text-pink-600 hover:bg-pink-200 transition">
                            <i class="fas fa-sliders-h"></i>
                        </button>
                        <button class="p-2 rounded-full bg-pink-100 text-pink-600 hover:bg-pink-200 transition">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                
                <div class="space-y-4">
                    <!-- Profile Card 1 -->
                    <div class="profile-card transition duration-300 cursor-pointer" @click="selectProfile('Thomas, 28')">
                        <div class="bg-white rounded-lg shadow-sm p-4 flex items-center space-x-3 border border-gray-100">
                            <div class="relative">
                                <img src="https://randomuser.me/api/portraits/men/32.jpg" alt="Profile" class="w-16 h-16 rounded-full object-cover">
                                <div class="online-dot"></div>
                            </div>
                            <div>
                                <h3 class="font-semibold">Thomas, 28</h3>
                                <p class="text-sm text-gray-500">Paris, 5km</p>
                                <div class="flex mt-1 space-x-1">
                                    <span class="px-2 py-1 bg-pink-100 text-pink-600 text-xs rounded-full">Sportif</span>
                                    <span class="px-2 py-1 bg-pink-100 text-pink-600 text-xs rounded-full">Voyages</span>
                                </div>
                            </div>
                            <button class="ml-auto p-2 rounded-full bg-pink-500 text-white hover:bg-pink-600 transition">
                                <i class="fas fa-comment-dots"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Profile Card 2 -->
                    <div class="profile-card transition duration-300 cursor-pointer" @click="selectProfile('Sophie, 25')">
                        <div class="bg-white rounded-lg shadow-sm p-4 flex items-center space-x-3 border border-gray-100">
                            <div class="relative">
                                <img src="https://randomuser.me/api/portraits/women/65.jpg" alt="Profile" class="w-16 h-16 rounded-full object-cover">
                                <div class="online-dot"></div>
                            </div>
                            <div>
                                <h3 class="font-semibold">Sophie, 25</h3>
                                <p class="text-sm text-gray-500">Lyon, 2km</p>
                                <div class="flex mt-1 space-x-1">
                                    <span class="px-2 py-1 bg-pink-100 text-pink-600 text-xs rounded-full">Musique</span>
                                    <span class="px-2 py-1 bg-pink-100 text-pink-600 text-xs rounded-full">Cinéma</span>
                                </div>
                            </div>
                            <button class="ml-auto p-2 rounded-full bg-gray-200 text-gray-600 hover:bg-gray-300 transition">
                                <i class="fas fa-comment-dots"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- More profile cards here -->
                </div>
                
                <div class="mt-4 text-center">
                    <button class="px-4 py-2 bg-pink-500 text-white rounded-full hover:bg-pink-600 transition font-medium">
                        Voir plus de profils
                    </button>
                </div>
            </div>
            
            <!-- Chat Section -->
            <div class="w-full lg:w-2/3 bg-white rounded-xl shadow-md overflow-hidden">
                <!-- Chat Header -->
                <div class="border-b border-gray-200 p-4 flex items-center space-x-3">
                    <div class="relative">
                        <img src="https://randomuser.me/api/portraits/men/32.jpg" alt="Profile" class="w-12 h-12 rounded-full object-cover">
                        <div class="online-dot"></div>
                    </div>
                    <div>
                        <h3 class="font-semibold">Thomas</h3>
                        <p class="text-sm text-gray-500">En ligne maintenant</p>
                    </div>
                    <div class="ml-auto flex space-x-2">
                        <button class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition">
                            <i class="fas fa-phone-alt"></i>
                        </button>
                        <button class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition">
                            <i class="fas fa-video"></i>
                        </button>
                        <button class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Chat Messages -->
                <div class="chat-container overflow-y-auto p-4 space-y-3">
                    <!-- Chat content here -->
                    <div class="text-center text-xs text-gray-500 my-4">
                        Aujourd'hui
                    </div>
                    
                    <div v-for="(message, index) in chatMessages" :key="index" :class="`flex space-x-2 ${message.isOutgoing ? 'justify-end' : ''}`">
                        <img v-if="!message.isOutgoing" src="https://randomuser.me/api/portraits/men/32.jpg" alt="Profile" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                        <div>
                            <div :class="`${message.isOutgoing ? 'message-out' : 'message-in'} px-4 py-2 max-w-xs lg:max-w-md`">
                                {{ message.content }}
                            </div>
                            <p class="text-xs text-gray-500 mt-1" :class="{ 'text-right': message.isOutgoing }">{{ message.time }}</p>
                        </div>
                        <img v-if="message.isOutgoing" src="https://randomuser.me/api/portraits/women/44.jpg" alt="Profile" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                    </div>
                </div>
                
                <!-- Message Input -->
                <div class="border-t border-gray-200 p-4">
                    <div class="flex items-center space-x-2">
                        <button class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition">
                            <i class="fas fa-plus"></i>
                        </button>
                        <input v-model="newMessage" type="text" placeholder="Écrire un message..." class="flex-1 px-4 py-2 bg-gray-100 rounded-full focus:outline-none focus:ring-2 focus:ring-pink-500" @keyup.enter="sendMessage">
                        <button class="p-2 rounded-full bg-pink-500 text-white hover:bg-pink-600 transition" @click="sendMessage">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import MainLayout from '@client/Layouts/MainLayout.vue';

const chatMessages = ref([
    {
        content: "Salut ! Comment ça va ? J'ai vu que tu aimais les voyages aussi. Où es-tu allé récemment ?",
        time: '10:24',
        isOutgoing: false
    },
    {
        content: "Salut Thomas ! Ça va bien merci :) Je suis allé en Italie le mois dernier, c'était magnifique ! Et toi ?",
        time: '10:26',
        isOutgoing: true
    },
    {
        content: "Super ! J'adore l'Italie. Moi je suis allé en Grèce cet été. Les paysages étaient incroyables !",
        time: '10:28',
        isOutgoing: false
    },
    {
        content: "Oh la Grèce c'est sur ma liste ! Tu recommandes quelle île en particulier ?",
        time: '10:30',
        isOutgoing: true
    },
    {
        content: "Santorin est magnifique, mais j'ai aussi adoré Naxos qui est plus authentique et moins touristique.",
        time: '10:32',
        isOutgoing: false
    },
    {
        content: "Merci pour les conseils ! Ça te dit qu'on en parle autour d'un café un de ces jours ? 😊",
        time: '10:34',
        isOutgoing: true
    }
]);

const newMessage = ref('');

function sendMessage() {
    if (newMessage.value.trim() === '') return;
    
    const now = new Date();
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    
    chatMessages.value.push({
        content: newMessage.value,
        time: `${hours}:${minutes}`,
        isOutgoing: true
    });
    
    newMessage.value = '';
    
    // Scroll to bottom
    setTimeout(() => {
        const chatContainer = document.querySelector('.chat-container');
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }, 50);
}

function selectProfile(name) {
    console.log('Selected profile:', name);
    // You can add more functionality here
}

onMounted(() => {
    // Auto-scroll chat to bottom
    const chatContainer = document.querySelector('.chat-container');
    chatContainer.scrollTop = chatContainer.scrollHeight;
});
</script>

<style scoped>
/* Les styles sont maintenant gérés au niveau du MainLayout pour la cohérence dans toute l'application client */
</style>