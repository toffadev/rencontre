<template>
    <MainLayout>
        <div class="max-w-2xl mx-auto pb-20 sm:pb-8">
            <div class="bg-white/90 backdrop-blur-sm rounded-xl shadow-xl p-4 sm:p-8 border border-pink-100">
                <!-- Affichage des erreurs -->
                <div v-if="Object.keys($page.props.errors).length > 0" class="mb-8">
                    <div class="bg-red-50 border-l-4 border-red-400 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">
                                    Certains champs nécessitent votre attention
                                </h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <ul class="list-disc pl-5 space-y-1">
                                        <li v-for="(error, key) in $page.props.errors" :key="key">
                                            {{ error }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Header avec message suggestif -->
                <div class="text-center mb-12">
                    <h1 class="text-3xl font-bold text-gray-900 mb-3">Configurez votre profil</h1>
                    <p class="text-gray-600 text-lg mb-2">
                        Plus votre profil est complet, plus vous avez de chances de faire des rencontres intéressantes
                    </p>
                    <p class="text-pink-500 text-sm italic">
                        "Soyez authentique, partagez vos désirs et trouvez des partenaires qui vous correspondent vraiment"
                    </p>
                </div>

                <!-- Progress Steps avec animation -->
                <div class="flex items-center mb-16 max-w-md mx-auto">
                    <div v-for="(step, index) in steps" :key="index" class="relative flex-1">
                        <div class="h-1 bg-gray-200">
                            <div
                                class="absolute h-1 bg-gradient-to-r from-pink-400 to-pink-600 transition-all duration-500 ease-in-out"
                                :style="{ width: currentStep > index ? '100%' : '0%' }"
                            ></div>
                        </div>
                        <div class="absolute left-0 -top-3 w-full">
                            <div class="flex flex-col items-center">
                                <div
                                    :class="[
                                        'rounded-full transition duration-500 ease-in-out h-8 w-8 border-2 flex items-center justify-center',
                                        currentStep > index
                                            ? 'bg-gradient-to-r from-pink-500 to-purple-600 border-pink-500 text-white'
                                            : currentStep === index
                                            ? 'border-pink-500 text-pink-500'
                                            : 'border-gray-300 text-gray-400',
                                    ]"
                                >
                                    <span v-if="currentStep <= index">{{ index + 1 }}</span>
                                    <i v-else class="fas fa-check text-white"></i>
                                </div>
                                <span 
                                    class="absolute top-10 text-xs text-center w-32 -ml-[4rem]"
                                    :class="currentStep >= index ? 'text-pink-500 font-medium' : 'text-gray-400'"
                                >
                                    {{ step }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form avec animations et styles améliorés -->
                <form @submit.prevent="submitForm" class="space-y-8">
                    <!-- Step 1: Photo et Informations de base -->
                    <div v-if="currentStep === 0" class="space-y-8 animate-fade-in">
                        <!-- Profile Photo avec animation au survol -->
                        <div class="flex flex-col items-center space-y-4">
                            <div class="relative group">
                                <img
                                    :src="previewImage || form.profile_photo_url || '/images/profile-placeholder.jpg'"
                                    alt="Photo de profil"
                                    class="w-40 h-40 rounded-full object-cover border-4 border-pink-100 group-hover:border-pink-300 transition-all duration-300 shadow-lg"
                                />
                                <div class="absolute inset-0 bg-black/30 rounded-full opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                    <p class="text-white text-sm font-medium">Choisir une photo</p>
                                </div>
                                <label
                                    class="absolute bottom-2 right-2 bg-gradient-to-r from-pink-500 to-purple-600 text-white rounded-full w-10 h-10 flex items-center justify-center cursor-pointer hover:scale-110 transition-all shadow-lg"
                                >
                                    <i class="fas fa-camera text-lg"></i>
                                    <input
                                        type="file"
                                        class="hidden"
                                        accept="image/*"
                                        @change="handleImageUpload"
                                    />
                                </label>
                            </div>
                            <p class="text-sm text-gray-500">Une belle photo augmente vos chances de rencontres</p>
                        </div>

                        <!-- Basic Info avec style amélioré -->
                        <div class="grid gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Date de naissance <span class="text-xs text-pink-500">(18+ uniquement)</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-calendar-alt text-pink-400"></i>
                                    </div>
                                    <input
                                        type="date"
                                        v-model="form.birth_date"
                                        class="block w-full pl-10 py-3 rounded-lg border-gray-300 shadow-sm focus:border-pink-500 focus:ring-pink-500 focus:ring-2 text-gray-700 transition-all"
                                        required
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Ville <span class="text-xs text-gray-500">(où vous recherchez des rencontres)</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-map-marker-alt text-pink-400"></i>
                                    </div>
                                    <input
                                        type="text"
                                        v-model="form.city"
                                        class="block w-full pl-10 py-3 rounded-lg border-gray-300 shadow-sm focus:border-pink-500 focus:ring-pink-500 focus:ring-2 transition-all"
                                        placeholder="Ex: Paris"
                                        required
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Préférences et Bio -->
                    <div v-if="currentStep === 1" class="space-y-10 animate-fade-in">
                        <!-- Sexual Orientation avec design amélioré -->
                        <div>
                            <label class="block text-base font-medium text-gray-900 mb-6 text-center">
                                Quelle est votre orientation ?
                            </label>
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <input
                                        type="radio"
                                        id="heterosexual"
                                        v-model="form.sexual_orientation"
                                        value="heterosexual"
                                        class="hidden peer"
                                        required
                                    />
                                    <label
                                        for="heterosexual"
                                        class="flex flex-col items-center justify-center p-6 border-2 rounded-xl cursor-pointer transition-all duration-200 peer-checked:border-pink-500 peer-checked:bg-pink-50 hover:bg-gray-50 hover:border-pink-300 shadow-sm"
                                    >
                                        <i class="fas fa-venus-mars text-3xl mb-3 text-pink-500"></i>
                                        <span class="font-medium">Hétérosexuel(le)</span>
                                        <p class="text-xs text-gray-500 mt-1">Attiré(e) par le sexe opposé</p>
                                    </label>
                                </div>
                                <div>
                                    <input
                                        type="radio"
                                        id="homosexual"
                                        v-model="form.sexual_orientation"
                                        value="homosexual"
                                        class="hidden peer"
                                    />
                                    <label
                                        for="homosexual"
                                        class="flex flex-col items-center justify-center p-6 border-2 rounded-xl cursor-pointer transition-all duration-200 peer-checked:border-pink-500 peer-checked:bg-pink-50 hover:bg-gray-50 hover:border-pink-300 shadow-sm"
                                    >
                                        <i class="fas fa-venus-double text-3xl mb-3 text-pink-500"></i>
                                        <span class="font-medium">Homosexuel(le)</span>
                                        <p class="text-xs text-gray-500 mt-1">Attiré(e) par le même sexe</p>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Seeking Gender avec design amélioré -->
                        <div>
                            <label class="block text-base font-medium text-gray-900 mb-6 text-center">
                                Qui recherchez-vous pour des moments intimes ?
                            </label>
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <input
                                        type="radio"
                                        id="male"
                                        v-model="form.seeking_gender"
                                        value="male"
                                        class="hidden peer"
                                        required
                                    />
                                    <label
                                        for="male"
                                        class="flex flex-col items-center justify-center p-6 border-2 rounded-xl cursor-pointer transition-all duration-200 peer-checked:border-pink-500 peer-checked:bg-pink-50 hover:bg-gray-50 hover:border-pink-300 shadow-sm"
                                    >
                                        <i class="fas fa-mars text-3xl mb-3 text-blue-500"></i>
                                        <span class="font-medium">Un homme</span>
                                        <p class="text-xs text-gray-500 mt-1">Pour des rencontres passionnées</p>
                                    </label>
                                </div>
                                <div>
                                    <input
                                        type="radio"
                                        id="female"
                                        v-model="form.seeking_gender"
                                        value="female"
                                        class="hidden peer"
                                    />
                                    <label
                                        for="female"
                                        class="flex flex-col items-center justify-center p-6 border-2 rounded-xl cursor-pointer transition-all duration-200 peer-checked:border-pink-500 peer-checked:bg-pink-50 hover:bg-gray-50 hover:border-pink-300 shadow-sm"
                                    >
                                        <i class="fas fa-venus text-3xl mb-3 text-pink-500"></i>
                                        <span class="font-medium">Une femme</span>
                                        <p class="text-xs text-gray-500 mt-1">Pour des moments sensuels</p>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Bio avec style amélioré -->
                        <div class="mt-8">
                            <label class="block text-base font-medium text-gray-900 mb-2">
                                Parlez-nous de vous et de vos désirs
                                <span class="text-red-500">*</span>
                            </label>
                            <p class="text-sm text-gray-500 mb-4">
                                Décrivez qui vous êtes, ce que vous aimez et ce que vous recherchez dans vos rencontres intimes
                            </p>
                            <textarea
                                v-model="form.bio"
                                rows="4"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-pink-500 focus:ring-pink-500 focus:ring-2 resize-none transition-all"
                                :class="{ 'border-red-300': $page.props.errors.bio }"
                                placeholder="Je suis une personne passionnée qui recherche..."
                                required
                            ></textarea>
                            <p class="mt-2 text-sm flex justify-between">
                                <span :class="{ 'text-red-500': form.bio.length < 10, 'text-gray-500': form.bio.length >= 10 }">
                                    {{ form.bio ? form.bio.length : 0 }}/1000 caractères
                                </span>
                                <span v-if="form.bio && form.bio.length < 50" class="text-yellow-600">
                                    <i class="fas fa-lightbulb mr-1"></i> Une description plus détaillée attire davantage de partenaires
                                </span>
                            </p>
                        </div>
                    </div>

                    <!-- Navigation Buttons avec style amélioré -->
                    <div class="flex flex-col sm:flex-row justify-between gap-4 sm:gap-2 pt-8">
                        <button
                            type="button"
                            v-if="currentStep > 0"
                            @click="currentStep--"
                            class="w-full sm:w-auto px-6 py-3 border-2 border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 hover:border-pink-300 transition-colors duration-300 flex items-center justify-center"
                        >
                            <i class="fas fa-arrow-left mr-2"></i>
                            Précédent
                        </button>
                        <button
                            v-if="currentStep < steps.length - 1"
                            @click="nextStep"
                            type="button"
                            class="w-full sm:w-auto px-6 py-3 bg-gradient-to-r from-pink-500 to-purple-600 text-white rounded-lg text-sm font-medium hover:from-pink-600 hover:to-purple-700 transition-colors duration-300 flex items-center justify-center shadow-md"
                        >
                            Suivant
                            <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                        <button
                            type="submit"
                            v-if="currentStep === steps.length - 1"
                            class="w-full sm:w-auto px-6 py-3 bg-gradient-to-r from-pink-500 to-purple-600 text-white rounded-lg text-sm font-medium hover:from-pink-600 hover:to-purple-700 transition-colors duration-300 flex items-center justify-center shadow-md transform hover:scale-[1.02]"
                        >
                            Commencer à rencontrer
                            <i class="fas fa-heart ml-2"></i>
                        </button>
                    </div>
                </form>

                <!-- Témoignage pour encourager -->
                <div v-if="currentStep === 1" class="mt-10 bg-pink-50 p-4 rounded-lg border border-pink-100 animate-fade-in">
                    <p class="text-sm italic text-gray-600">"Après avoir complété mon profil avec des détails sur mes préférences, j'ai trouvé des partenaires parfaitement compatibles. Les rencontres ont été au-delà de mes espérances !" <span class="font-medium">- Marc, 32 ans</span></p>
                </div>
            </div>
        </div>
    </MainLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import MainLayout from '@client/Layouts/MainLayout.vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    user: Object,
    profile: Object,
});

const steps = ['Photo & Informations', 'Préférences & Bio'];
const currentStep = ref(0);
const previewImage = ref(null);

const form = reactive({
    birth_date: props.profile?.birth_date || '',
    city: props.profile?.city || '',
    sexual_orientation: props.profile?.sexual_orientation || '',
    seeking_gender: props.profile?.seeking_gender || '',
    bio: props.profile?.bio || '',
    profile_photo: null,
    profile_photo_url: props.profile?.profile_photo_url || null,
});

const handleImageUpload = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.profile_photo = file;
        previewImage.value = URL.createObjectURL(file);
    }
};

const nextStep = () => {
    if (currentStep.value < steps.length - 1) {
        currentStep.value++;
    }
};

const submitForm = () => {
    router.post('/profile-setup', form, {
        forceFormData: true,
        onSuccess: () => {
            router.visit('/');
        },
        onError: (errors) => {
            console.error('Erreurs de validation:', errors);
            // Retourner à l'étape appropriée si nécessaire
            if (errors.birth_date || errors.city || errors.profile_photo) {
                currentStep.value = 0;
            }
        },
        preserveScroll: true,
    });
};
</script>

<style scoped>
/* Animation pour les transitions entre les étapes */
.animate-fade-in {
    animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.resize-none {
    resize: none;
}

input:focus, textarea:focus {
    box-shadow: 0 0 0 3px rgba(244, 114, 182, 0.15);
}

/* Animation pour le bouton de soumission */
button[type="submit"]:hover {
    animation: pulse 1.5s infinite;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(236, 72, 153, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(236, 72, 153, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(236, 72, 153, 0);
    }
}
</style>