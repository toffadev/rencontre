<template>
  <GuestLayout>
    <div class="max-w-md mx-auto bg-white rounded-xl shadow-md overflow-hidden p-8">
      <div class="mb-4 text-center">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-2">Mot de passe oublié?</h2>
        <p class="text-center text-gray-600 mb-8">Saisissez votre adresse e-mail et nous vous enverrons un lien de réinitialisation.</p>
      </div>

      <div v-if="status" class="mb-4 rounded-lg bg-green-100 p-3 text-sm font-medium text-green-700">
        {{ status }}
      </div>
      
      <form @submit.prevent="submit">
        <!-- Email Input -->
        <div class="mb-6">
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

        <div class="flex items-center justify-between">
          <a href="/login" class="text-sm text-pink-600 hover:text-pink-700 focus:outline-none">
            Retour à la connexion
          </a>

          <button type="submit" class="signup-btn bg-pink-500 text-white py-2 px-6 rounded-lg font-medium hover:bg-pink-600 transition duration-300" :disabled="form.processing">
            Envoyer le lien
          </button>
        </div>
      </form>
    </div>
  </GuestLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const props = defineProps({
  status: String,
});

const form = useForm({
  email: '',
});

const submit = () => {
  form.post('/forgot-password');
};
</script>

<style scoped>
.signup-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}
</style> 