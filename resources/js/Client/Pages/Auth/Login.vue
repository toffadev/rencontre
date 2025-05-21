<template>
  <GuestLayout>
    <div class="max-w-md mx-auto bg-white rounded-xl shadow-md overflow-hidden p-8">
      <div class="mb-4 text-center">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-2">Content de vous revoir !</h2>
        <p class="text-center text-gray-600 mb-8">Connectez-vous pour retrouver vos matches</p>
      </div>

      <div v-if="status" class="mb-4 rounded-lg bg-green-100 p-3 text-sm font-medium text-green-700">
        {{ status }}
      </div>
      
      <!-- Social Login Buttons -->
      <div class="space-y-3 mb-6">
        <button class="w-full flex items-center justify-center space-x-2 bg-white border border-gray-300 rounded-lg py-3 px-4 hover:bg-gray-50 transition">
          <img src="https://upload.wikimedia.org/wikipedia/commons/5/53/Google_%22G%22_Logo.svg" alt="Google" class="w-5 h-5">
          <span>Se connecter avec Google</span>
        </button>
        <button class="w-full flex items-center justify-center space-x-2 bg-blue-600 text-white rounded-lg py-3 px-4 hover:bg-blue-700 transition">
          <i class="fab fa-facebook-f"></i>
          <span>Se connecter avec Facebook</span>
        </button>
      </div>
      
      <!-- Divider -->
      <div class="divider text-sm mb-6">OU</div>
      
      <!-- Login Form -->
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
          <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
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
          <div v-if="form.errors.password" class="text-red-600 mt-1 text-sm">{{ form.errors.password }}</div>
        </div>
        
        <!-- Remember me & Forgot password -->
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center">
            <input id="remember" type="checkbox" v-model="form.remember" class="h-4 w-4 text-pink-600 focus:ring-pink-500 border-gray-300 rounded">
            <label for="remember" class="ml-2 block text-sm text-gray-700">Se souvenir de moi</label>
          </div>
          <div>
            <Link :href="route('password.request')" class="text-sm text-pink-600 hover:text-pink-700 forgot-password">Mot de passe oublié ?</Link>
          </div>
        </div>
        
        <!-- Submit Button -->
        <button type="submit" class="login-btn w-full bg-pink-500 text-white py-3 px-4 rounded-lg font-medium hover:bg-pink-600 transition duration-300 mb-4" :disabled="form.processing">
          Se connecter
        </button>
        
        <!-- Signup Link -->
        <p class="text-center text-sm text-gray-600">
          Pas encore membre ? <Link :href="route('register')" class="text-pink-600 font-medium hover:text-pink-700">Créez un compte</Link>
        </p>
      </form>

      <!-- Admin Login Link -->
      <div class="mt-6 text-center border-t pt-4 border-gray-200">
        <Link :href="route('admin.login')" class="text-sm text-gray-500 hover:text-pink-600">
          Accès administration
        </Link>
      </div>
    </div>
  </GuestLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import GuestLayout from '@client/Layouts/GuestLayout.vue';
import { route } from 'ziggy-js';

const props = defineProps({
  status: String,
});

const form = useForm({
  email: '',
  password: '',
  remember: false,
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
  form.post(route('login'));
};
</script> 