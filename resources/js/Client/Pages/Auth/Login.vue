<template>
  <GuestLayout>
    <div class="min-h-full flex items-center justify-center relative overflow-hidden">
      <!-- Carrousel d'images de fond -->
      <div class="absolute inset-0 z-0">
        <div class="carousel-container">
          <div v-for="(image, index) in backgroundImages" :key="index" 
               :class="['carousel-slide', {'opacity-0': currentSlide !== index}]"
               :style="{
                 'background-image': `url(${image})`,
                 'transform': `translateX(${getSlidePosition(index)})`
               }">
          </div>
          <div class="absolute inset-0 bg-black/50"></div>
        </div>
      </div>
      
      <!-- Contenu du formulaire -->
      <div class="max-w-xs w-full mx-auto bg-white/90 backdrop-blur-sm rounded-xl shadow-xl overflow-hidden p-5 border border-pink-100 z-10 my-8">
        <div class="mb-4 text-center">
          <h2 class="text-2xl font-bold text-center text-gray-800 mb-1">Content de vous revoir !</h2>
          <p class="text-center text-gray-600 text-sm mb-4">Connectez-vous pour retrouver vos matches</p>
        </div>

        <div v-if="status" class="mb-3 rounded-lg bg-green-100 p-2 text-sm font-medium text-green-700">
          {{ status }}
        </div>
        
        <!-- Social Login Buttons -->
        <!-- <div class="space-y-2 mb-4">
          <button class="w-full flex items-center justify-center space-x-2 bg-white border border-gray-300 rounded-lg py-2 px-3 hover:bg-gray-50 transition duration-300 transform hover:scale-[1.02] text-sm">
            <img src="https://upload.wikimedia.org/wikipedia/commons/5/53/Google_%22G%22_Logo.svg" alt="Google" class="w-4 h-4">
            <span>Se connecter avec Google</span>
          </button>
          <button class="w-full flex items-center justify-center space-x-2 bg-blue-600 text-white rounded-lg py-2 px-3 hover:bg-blue-700 transition duration-300 transform hover:scale-[1.02] text-sm">
            <i class="fab fa-facebook-f"></i>
            <span>Se connecter avec Facebook</span>
          </button>
        </div>
        
        
        <div class="flex items-center my-3">
          <div class="flex-grow border-t border-gray-300"></div>
          <span class="px-3 text-gray-500 text-xs">OU</span>
          <div class="flex-grow border-t border-gray-300"></div>
        </div> -->
        
        <!-- Login Form -->
        <form @submit.prevent="submit" class="space-y-3">
          <!-- Email Input -->
          <div>
            <label for="email" class="block text-xs font-medium text-gray-700 mb-1">Adresse email</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fas fa-envelope text-pink-400 text-sm"></i>
              </div>
              <input 
                type="email" 
                id="email" 
                v-model="form.email"
                class="input-field w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500 focus:border-pink-500 transition-all"
                placeholder="votre@email.com"
                required
                autofocus
              >
            </div>
            <div v-if="form.errors.email" class="text-red-600 mt-1 text-xs">{{ form.errors.email }}</div>
          </div>
          
          <!-- Password Input -->
          <div>
            <label for="password" class="block text-xs font-medium text-gray-700 mb-1">Mot de passe</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fas fa-lock text-pink-400 text-sm"></i>
              </div>
              <input 
                type="password" 
                id="password" 
                v-model="form.password"
                class="input-field w-full pl-9 pr-9 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500 focus:border-pink-500 transition-all"
                placeholder="••••••••"
                required
              >
              <div class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer">
                <i class="fas fa-eye-slash text-gray-400 hover:text-pink-500 transition text-sm" @click="togglePassword"></i>
              </div>
            </div>
            <div v-if="form.errors.password" class="text-red-600 mt-1 text-xs">{{ form.errors.password }}</div>
          </div>
          
          <!-- Remember me & Forgot password -->
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <input id="remember" type="checkbox" v-model="form.remember" class="h-3 w-3 text-pink-600 focus:ring-pink-500 border-gray-300 rounded">
              <label for="remember" class="ml-2 block text-xs text-gray-700">Se souvenir de moi</label>
            </div>
            <div>
              <Link :href="route('password.request')" class="text-xs text-pink-600 hover:text-pink-700 forgot-password font-medium">Mot de passe oublié ?</Link>
            </div>
          </div>
          
          <!-- Submit Button -->
          <button type="submit" class="login-btn w-full bg-gradient-to-r from-pink-500 to-purple-600 text-white py-2 px-4 rounded-lg font-medium hover:from-pink-600 hover:to-purple-700 transition duration-300 transform hover:scale-[1.02] shadow-md text-sm" :disabled="form.processing">
            Se connecter
          </button>
          
          <!-- Signup Link -->
          <p class="text-center text-xs text-gray-600">
            Pas encore membre ? <Link :href="route('register')" class="text-pink-600 font-medium hover:text-pink-700">Créez un compte</Link>
          </p>
        </form>

        <!-- Admin Login Link -->
        <div class="mt-4 text-center border-t pt-3 border-gray-200">
          <Link :href="route('admin.login')" class="text-xs text-gray-500 hover:text-pink-600">
            Accès administration
          </Link>
        </div>
      </div>
    </div>
  </GuestLayout>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import authService from '@/services/AuthenticationService';
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

const backgroundImages = [
  '/rencontre/imageregister1.jpg',
  '/rencontre/imageregister2.jpg',
  '/rencontre/imageregister3.jpg',
  '/rencontre/imageregister4.jpg'
];

const currentSlide = ref(0);
let intervalId = null;

const getSlidePosition = (index) => {
  // Alternance de la position (gauche/droite)
  if (index === currentSlide.value) {
    return '0%';
  } else if ((index % 2) === 0) {
    return '-100%';
  } else {
    return '100%';
  }
};

const nextSlide = () => {
  currentSlide.value = (currentSlide.value + 1) % backgroundImages.length;
};

onMounted(() => {
  // Démarrer le carrousel
  intervalId = setInterval(nextSlide, 5000);
});

onBeforeUnmount(() => {
  // Nettoyer l'intervalle lors de la destruction du composant
  clearInterval(intervalId);
});

const togglePassword = () => {
  const password = document.querySelector('#password');
  const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
  password.setAttribute('type', type);
  
  const icon = document.querySelector('.fa-eye-slash, .fa-eye');
  icon.classList.toggle('fa-eye-slash');
  icon.classList.toggle('fa-eye');
};

/* const submit = () => {
  form.post(route('login'));
}; */

const submit = () => {
  form.post(route('login'), {
    onSuccess: async () => {
      await authService.reinitializeAfterAuth();
      // Redirige ou recharge la page si besoin
      window.location.reload();
    }
  });
};

</script>

<style scoped>
.login-btn {
  background-size: 200% auto;
  transition: all 0.3s ease;
}

.login-btn:hover {
  background-position: right center;
}

.forgot-password {
  position: relative;
  display: inline-block;
}

.forgot-password:after {
  content: '';
  position: absolute;
  width: 0;
  height: 1px;
  bottom: -1px;
  left: 0;
  background-color: #db2777;
  transition: width 0.3s ease;
}

.forgot-password:hover:after {
  width: 100%;
}

.carousel-container {
  position: relative;
  width: 100%;
  height: 100%;
  overflow: hidden;
}

.carousel-slide {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background-size: cover;
  background-position: center;
  transition: transform 1.5s ease, opacity 1.5s ease;
}
</style> 