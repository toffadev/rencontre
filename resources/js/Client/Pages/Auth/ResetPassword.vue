<template>
  <GuestLayout>
    <div class="max-w-md mx-auto bg-white rounded-xl shadow-md overflow-hidden p-8">
      <div class="mb-4 text-center">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-2">Réinitialiser le mot de passe</h2>
        <p class="text-center text-gray-600 mb-8">Veuillez créer un nouveau mot de passe sécurisé</p>
      </div>
      
      <form @submit.prevent="submit">
        <!-- Email Input -->
        <div class="mb-4">
          <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Adresse email</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <i class="fas fa-envelope text-gray-400"></i>
            </div>
            <input 
              type="email" 
              id="email" 
              v-model="form.email" 
              class="input-field w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-pink-500" 
              placeholder="votre@email.com"
              required 
              autofocus
            >
          </div>
          <div v-if="form.errors.email" class="text-red-600 mt-1 text-sm">{{ form.errors.email }}</div>
        </div>
        
        <!-- Password Input -->
        <div class="mb-4">
          <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Nouveau mot de passe</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <i class="fas fa-lock text-gray-400"></i>
            </div>
            <input 
              type="password" 
              id="password" 
              v-model="form.password" 
              class="input-field w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-pink-500" 
              placeholder="••••••••"
              required
            >
            <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer">
              <i class="fas fa-eye-slash text-gray-400 hover:text-gray-600" @click="togglePassword"></i>
            </div>
          </div>
          <p class="text-xs text-gray-500 mt-1">Minimum 8 caractères avec des chiffres et lettres</p>
          <div v-if="form.errors.password" class="text-red-600 mt-1 text-sm">{{ form.errors.password }}</div>
        </div>
        
        <!-- Password Confirmation -->
        <div class="mb-6">
          <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirmer le mot de passe</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <i class="fas fa-lock text-gray-400"></i>
            </div>
            <input 
              type="password" 
              id="password_confirmation" 
              v-model="form.password_confirmation" 
              class="input-field w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-pink-500" 
              placeholder="••••••••"
              required
            >
          </div>
        </div>
        
        <!-- Submit Button -->
        <input type="hidden" name="token" v-model="form.token">
        <button type="submit" class="signup-btn w-full bg-pink-500 text-white py-3 px-4 rounded-lg font-medium hover:bg-pink-600 transition duration-300 mb-4" :disabled="form.processing">
          Réinitialiser le mot de passe
        </button>
        
        <!-- Login Link -->
        <p class="text-center text-sm text-gray-600 mt-4">
          <a href="/login" class="text-pink-600 font-medium hover:text-pink-700">Retour à la connexion</a>
        </p>
      </form>
    </div>
  </GuestLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const props = defineProps({
  token: String,
  email: String,
});

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
});

const togglePassword = () => {
  const password = document.querySelector('#password');
  const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
  password.setAttribute('type', type);
  
  const icon = document.querySelector('.fa-eye-slash, .fa-eye');
  icon.classList.toggle('fa-eye-slash');
  icon.classList.toggle('fa-eye');
};

const submit = () => {
  form.post('/reset-password');
};
</script>

<style scoped>
.signup-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}
</style> 