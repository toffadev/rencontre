<template>
  <GuestLayout>
    <div class="max-w-md mx-auto bg-white rounded-xl shadow-md overflow-hidden p-8">
      <div class="mb-4 text-center">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-2">Créez votre compte</h2>
        <p class="text-center text-gray-600 mb-8">Rejoignez notre communauté et trouvez l'amour</p>
      </div>
      
      <!-- Social Login Buttons -->
      <div class="space-y-3 mb-6">
        <button type="button" class="w-full flex items-center justify-center space-x-2 bg-white border border-gray-300 rounded-lg py-3 px-4 hover:bg-gray-50 transition">
          <img src="https://upload.wikimedia.org/wikipedia/commons/5/53/Google_%22G%22_Logo.svg" alt="Google" class="w-5 h-5">
          <span>S'inscrire avec Google</span>
        </button>
        <button type="button" class="w-full flex items-center justify-center space-x-2 bg-blue-600 text-white rounded-lg py-3 px-4 hover:bg-blue-700 transition">
          <i class="fab fa-facebook-f"></i>
          <span>S'inscrire avec Facebook</span>
        </button>
      </div>
      
      <!-- Divider -->
      <div class="divider text-sm mb-6">OU</div>
      
      <!-- Registration Form -->
      <form @submit.prevent="submit">
        <!-- Name Input -->
        <div class="mb-4">
          <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nom complet</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <i class="fas fa-user text-gray-400"></i>
            </div>
            <input 
              type="text" 
              id="name" 
              v-model="form.name" 
              class="input-field w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-pink-500" 
              placeholder="Votre nom complet"
              required 
              autofocus
            >
          </div>
          <div v-if="form.errors.name" class="text-red-600 mt-1 text-sm">{{ form.errors.name }}</div>
        </div>
        
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
          <p class="text-xs text-gray-500 mt-1">Minimum 8 caractères avec des chiffres et lettres</p>
          <div v-if="form.errors.password" class="text-red-600 mt-1 text-sm">{{ form.errors.password }}</div>
        </div>
        
        <!-- Password Confirmation -->
        <div class="mb-4">
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
        
        <!-- Date of Birth -->
        <div class="mb-4">
          <label for="dob" class="block text-sm font-medium text-gray-700 mb-1">Date de naissance</label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <i class="fas fa-calendar-alt text-gray-400"></i>
            </div>
            <input 
              type="date" 
              id="dob" 
              v-model="form.dob" 
              class="input-field w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-pink-500"
              required
            >
          </div>
          <div v-if="form.errors.dob" class="text-red-600 mt-1 text-sm">{{ form.errors.dob }}</div>
        </div>
        
        <!-- Gender Selection -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">Je suis</label>
          <div class="grid grid-cols-2 gap-3 gender-selector">
            <div>
              <input type="radio" id="male" name="gender" value="male" v-model="form.gender" class="hidden peer" checked>
              <label for="male" class="flex items-center justify-center p-3 border border-gray-300 rounded-lg cursor-pointer peer-checked:border-pink-500 peer-checked:bg-pink-50 transition">
                <i class="fas fa-mars text-blue-500 mr-2"></i>
                <span>Homme</span>
              </label>
            </div>
            <div>
              <input type="radio" id="female" name="gender" value="female" v-model="form.gender" class="hidden peer">
              <label for="female" class="flex items-center justify-center p-3 border border-gray-300 rounded-lg cursor-pointer peer-checked:border-pink-500 peer-checked:bg-pink-50 transition">
                <i class="fas fa-venus text-pink-500 mr-2"></i>
                <span>Femme</span>
              </label>
            </div>
          </div>
          <div v-if="form.errors.gender" class="text-red-600 mt-1 text-sm">{{ form.errors.gender }}</div>
        </div>
        
        <!-- Terms Checkbox -->
        <div class="mb-6">
          <div class="flex items-start">
            <div class="flex items-center h-5">
              <input id="terms" type="checkbox" v-model="form.terms" class="checkbox h-4 w-4 text-pink-600 focus:ring-pink-500 border-gray-300 rounded">
            </div>
            <div class="ml-3 text-sm">
              <label for="terms" class="font-medium text-gray-700">J'accepte les <a href="#" class="text-pink-600 hover:text-pink-700">Conditions d'utilisation</a> et la <a href="#" class="text-pink-600 hover:text-pink-700">Politique de confidentialité</a></label>
            </div>
          </div>
          <div v-if="form.errors.terms" class="text-red-600 mt-1 text-sm">{{ form.errors.terms }}</div>
        </div>
        
        <!-- Submit Button -->
        <button type="submit" class="signup-btn w-full bg-pink-500 text-white py-3 px-4 rounded-lg font-medium hover:bg-pink-600 transition duration-300 mb-4" :disabled="form.processing">
          S'inscrire gratuitement
        </button>
        
        <!-- Login Link -->
        <p class="text-center text-sm text-gray-600">
          Vous avez déjà un compte ? <a href="/login" class="text-pink-600 font-medium hover:text-pink-700">Connectez-vous</a>
        </p>
      </form>
    </div>
  </GuestLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
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

.signup-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

.gender-selector input:checked + label {
  border-color: #f472b6;
  background-color: #fdf2f8;
}
</style> 