<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import AdminGuestLayout from '@admin/Layouts/AdminGuestLayout.vue'
import { route } from 'ziggy-js';

const props = defineProps({
    status: String,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('admin.login.submit'));
};
</script>

<template>
    <AdminGuestLayout
        title="Espace Administration"
        description="Connectez-vous pour accéder au tableau de bord administrateur"
    >
        <div v-if="status" class="mb-4 font-medium text-sm text-green-400">
            {{ status }}
        </div>
        
        <!-- Message d'erreur global -->
        <div v-if="form.errors.email || form.errors.password" class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
            <p v-if="form.errors.email" class="font-medium">{{ form.errors.email }}</p>
            <p v-else-if="form.errors.password" class="font-medium">{{ form.errors.password }}</p>
            <p v-else class="font-medium">Identifiants incorrects. Veuillez réessayer.</p>
        </div>
        
        <form @submit.prevent="submit">
            <div>
                <label class="block font-medium text-sm text-gray-300" for="email">
                    Email
                </label>
                <input id="email" type="email" v-model="form.email" required autofocus autocomplete="username"
                    class="mt-1 block w-full text-white bg-gray-700 border-gray-600 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                />
            </div>

            <div class="mt-4">
                <label class="block font-medium text-sm text-gray-300" for="password">
                    Mot de passe
                </label>
                <input id="password" type="password" v-model="form.password" required autocomplete="current-password"
                    class="mt-1 block w-full text-white bg-gray-700 border-gray-600 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                />
            </div>

            <div class="block mt-4">
                <label class="flex items-center">
                    <input type="checkbox" v-model="form.remember" class="rounded border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 bg-gray-700" />
                    <span class="ml-2 text-sm text-gray-300">Se souvenir de moi</span>
                </label>
            </div>

            <div class="flex items-center justify-between mt-4">
                <Link :href="route('password.request')" class="underline text-sm text-gray-300 hover:text-gray-100 focus:outline-none">
                    Mot de passe oublié?
                </Link>

                <button 
                    type="submit" 
                    class="inline-flex items-center px-4 py-2 bg-indigo-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-600 focus:bg-indigo-600 active:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150" 
                    :class="{ 'opacity-25': form.processing }" 
                    :disabled="form.processing">
                    <span v-if="form.processing">Connexion en cours...</span>
                    <span v-else>Se connecter</span>
                </button>
            </div>
        </form>
        
        <div class="mt-6 text-center">
            <Link :href="route('login')" class="text-sm text-gray-400 hover:text-white">
                Accéder à l'espace client
            </Link>
        </div>
    </AdminGuestLayout>
</template>
