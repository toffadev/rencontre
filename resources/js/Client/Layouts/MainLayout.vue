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
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

body {
    font-family: 'Poppins', sans-serif;
    background-color: #fdf2f8;
}

.profile-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.chat-container {
    height: calc(100vh - 200px);
}

@media (max-width: 768px) {
    .chat-container {
        height: calc(100vh - 150px);
    }
}
</style> 