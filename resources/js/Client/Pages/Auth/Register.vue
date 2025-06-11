<template>
  <GuestLayout>
    <div class="min-h-full flex items-center justify-center relative">
      <!-- Image de fond -->
      <div class="absolute inset-0 z-0">
        <img src="/rencontre/imageregister3.jpg" alt="Background" class="w-full h-full object-cover" />
        <div class="absolute inset-0 bg-black/50"></div>
      </div>
      
      <!-- Contenu du formulaire -->
      <div class="max-w-xs w-full mx-auto bg-white/90 backdrop-blur-sm rounded-xl shadow-xl overflow-hidden p-5 border border-pink-100 z-10 my-8">
        <div class="mb-4 text-center">
          <h2 class="text-2xl font-bold text-center text-gray-800 mb-1">Créez votre compte</h2>
          <p class="text-center text-gray-600 text-sm mb-2">Rejoignez notre communauté</p>
        </div>
        
        <!-- Divider avec texte suggestif -->
        <div class="mb-3 text-center">
          <p class="text-pink-500 italic text-xs">"Des milliers de personnes vous attendent..."</p>
        </div>
        
        <!-- Registration Form -->
        <form @submit.prevent="submit" class="space-y-3">
          <!-- Name Input -->
          <div>
            <label for="name" class="block text-xs font-medium text-gray-700 mb-1">Nom complet</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fas fa-user text-pink-400 text-sm"></i>
              </div>
              <input 
                type="text" 
                id="name" 
                v-model="form.name" 
                class="input-field w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500 focus:border-pink-500 transition-all" 
                placeholder="Votre nom complet"
                required 
                autofocus
              >
            </div>
            <div v-if="form.errors.name" class="text-red-600 mt-1 text-xs">{{ form.errors.name }}</div>
          </div>
          
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
            <p class="text-xs text-gray-500 mt-1">Min 8 caractères avec chiffres et lettres</p>
            <div v-if="form.errors.password" class="text-red-600 mt-1 text-xs">{{ form.errors.password }}</div>
          </div>
          
          <!-- Password Confirmation -->
          <div>
            <label for="password_confirmation" class="block text-xs font-medium text-gray-700 mb-1">Confirmer le mot de passe</label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fas fa-lock text-pink-400 text-sm"></i>
              </div>
              <input 
                type="password" 
                id="password_confirmation" 
                v-model="form.password_confirmation" 
                class="input-field w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500 focus:border-pink-500 transition-all" 
                placeholder="••••••••"
                required
              >
            </div>
          </div>
          
          <!-- Date of Birth with improved styling -->
          <div>
            <label for="dob" class="block text-xs font-medium text-gray-700 mb-1">Date de naissance <span class="text-xs text-pink-500">(18+)</span></label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fas fa-calendar-alt text-pink-400 text-sm"></i>
              </div>
              <input 
                type="date" 
                id="dob" 
                v-model="form.dob" 
                class="input-field w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500 focus:border-pink-500 transition-all"
                required
              >
            </div>
            <div v-if="form.errors.dob" class="text-red-600 mt-1 text-xs">{{ form.errors.dob }}</div>
          </div>
          
          <!-- Gender Selection with improved styling -->
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Je suis</label>
            <div class="grid grid-cols-2 gap-2 gender-selector">
              <div>
                <input type="radio" id="male" name="gender" value="male" v-model="form.gender" class="hidden peer" checked>
                <label for="male" class="flex items-center justify-center p-2 border border-gray-300 rounded-lg cursor-pointer peer-checked:border-pink-500 peer-checked:bg-pink-50 transition hover:border-pink-300 text-sm">
                  <i class="fas fa-mars text-blue-500 mr-1"></i>
                  <span>Homme</span>
                </label>
              </div>
              <div>
                <input type="radio" id="female" name="gender" value="female" v-model="form.gender" class="hidden peer">
                <label for="female" class="flex items-center justify-center p-2 border border-gray-300 rounded-lg cursor-pointer peer-checked:border-pink-500 peer-checked:bg-pink-50 transition hover:border-pink-300 text-sm">
                  <i class="fas fa-venus text-pink-500 mr-1"></i>
                  <span>Femme</span>
                </label>
              </div>
            </div>
            <div v-if="form.errors.gender" class="text-red-600 mt-1 text-xs">{{ form.errors.gender }}</div>
          </div>
          
          <!-- Terms Checkbox with improved styling -->
          <div class="mt-1">
            <div class="flex items-start">
              <div class="flex items-center h-5">
                <input id="terms" type="checkbox" v-model="form.terms" class="checkbox h-3 w-3 text-pink-600 focus:ring-pink-500 border-gray-300 rounded">
              </div>
              <div class="ml-2 text-xs">
                <label for="terms" class="font-medium text-gray-700">J'accepte les <a href="#" class="text-pink-600 hover:text-pink-700 underline">Conditions</a> et la <a href="#" class="text-pink-600 hover:text-pink-700 underline">Confidentialité</a></label>
              </div>
            </div>
            <div v-if="form.errors.terms" class="text-red-600 mt-1 text-xs">{{ form.errors.terms }}</div>
          </div>
          
          <!-- Submit Button with gradient and animation -->
          <button type="submit" class="signup-btn w-full bg-gradient-to-r from-pink-500 to-purple-600 text-white py-2 px-4 rounded-lg font-medium hover:from-pink-600 hover:to-purple-700 transition duration-300 transform hover:scale-[1.02] shadow-md mt-2 text-sm" :disabled="form.processing">
            S'inscrire gratuitement
          </button>
          
          <!-- Login Link -->
          <p class="text-center text-xs text-gray-600 mt-2">
            Déjà membre ? <Link :href="route('login')" class="text-pink-600 font-medium hover:text-pink-700">Connectez-vous</Link>
          </p>
        </form>
      </div>
    </div>
  </GuestLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import GuestLayout from '@client/Layouts/GuestLayout.vue';
import { route } from 'ziggy-js';

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  dob: '',
  gender: 'male',
  terms: false,
});

const togglePassword = () => {
  const password = document.querySelector('#password');
  const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
  password.setAttribute('type', type);
  
  const icon = document.querySelector('.fa-eye-slash, .fa-eye');
  icon.classList.toggle('fa-eye-slash');
  icon.classList.toggle('fa-eye');
};

onMounted(() => {
  // Set max date for date of birth (18 years ago)
  const today = new Date();
  const minDate = new Date();
  minDate.setFullYear(today.getFullYear() - 100);
  
  const maxDate = new Date();
  maxDate.setFullYear(today.getFullYear() - 18);
  
  const dobInput = document.getElementById('dob');
  dobInput.min = minDate.toISOString().split('T')[0];
  dobInput.max = maxDate.toISOString().split('T')[0];
  dobInput.value = maxDate.toISOString().split('T')[0];
  
  // Set the form.dob value
  form.dob = maxDate.toISOString().split('T')[0];
});

const submit = () => {
  form.post('/register', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
};
</script>

<style scoped>
.checkbox:checked {
  background-color: #f472b6;
  border-color: #f472b6;
}

.signup-btn {
  background-size: 200% auto;
  transition: all 0.3s ease;
}

.signup-btn:hover {
  background-position: right center;
}

.gender-selector input:checked + label {
  border-color: #f472b6;
  background-color: #fdf2f8;
}

.input-field:focus {
  box-shadow: 0 0 0 3px rgba(244, 114, 182, 0.15);
}
</style> 