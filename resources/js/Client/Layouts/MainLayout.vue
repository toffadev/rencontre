<template>
    <div class="min-h-screen flex flex-col bg-pink-50">
        <Header />
        
        <main class="flex-1 container mx-auto px-4 py-6">
            <slot />
        </main>
        
        <Footer />
    </div>
</template>

<script setup>
import Header from '@client/Components/Header.vue';
import Footer from '@client/Components/Footer.vue';
</script>

<style>
/* Styles spécifiques à la partie client */
.gradient-bg {
    background: linear-gradient(135deg, #f9a8d4 0%, #f472b6 100%);
}

.profile-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.chat-container {
    height: calc(100vh - 200px);
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

.message-in {
    background-color: #f3f4f6;
    border-radius: 18px 18px 18px 0;
}

.message-out {
    background-color: #f472b6;
    color: white;
    border-radius: 18px 18px 0 18px;
}

@media (max-width: 768px) {
    .chat-container {
        height: calc(100vh - 150px);
    }
}
</style> 