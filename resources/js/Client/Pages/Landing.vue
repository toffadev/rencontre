<template>
  <div class="landing-page">
    <!-- Hero Section avec vidéo de fond -->
    <div class="relative h-screen overflow-hidden">
      <!-- Vidéo de fond (à remplacer par votre vidéo) -->
      <div class="absolute inset-0 z-0">
        <div class="absolute inset-0 bg-gradient-to-b from-black/70 via-black/50 to-black/80 z-10"></div>
        <video class="w-full h-full object-cover" autoplay loop muted playsinline>
          <source src="/videos/background.mp4" type="video/mp4" />
        </video>
      </div>
      
      <!-- Contenu du hero -->
      <div class="relative z-20 flex flex-col h-full items-center justify-center text-center px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
          <h1 class="text-4xl sm:text-5xl md:text-6xl font-extrabold text-white mb-6">
            <span class="block">Découvrez des rencontres</span>
            <span class="block text-pink-500">passionnantes et sensuelles</span>
          </h1>
          <p class="text-xl sm:text-2xl text-gray-300 mb-10">
            Des milliers de personnes à la recherche de plaisir vous attendent déjà dans votre région
          </p>
          <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <Link :href="route('register')" class="px-8 py-4 bg-gradient-to-r from-pink-500 to-purple-600 hover:from-pink-600 hover:to-purple-700 text-white font-bold rounded-full text-lg transition-all transform hover:scale-105 shadow-lg">
              S'inscrire gratuitement
            </Link>
            <Link :href="route('login')" class="px-8 py-4 bg-transparent border-2 border-white hover:bg-white/10 text-white font-bold rounded-full text-lg transition-all">
              Se connecter
            </Link>
          </div>
          <p class="mt-6 text-gray-400 text-sm">
            <span class="bg-pink-500 text-white px-2 py-1 rounded-full text-xs font-bold">18+</span> 
            Site réservé aux adultes consentants
          </p>
        </div>
      </div>
      
      <!-- Scroll indicator -->
      <div class="absolute bottom-10 left-1/2 transform -translate-x-1/2 z-20 animate-bounce">
        <i class="fas fa-chevron-down text-white text-2xl"></i>
      </div>
    </div>
    
    <!-- Features Section avec images suggestives -->
    <div class="bg-black text-white py-20 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto">
        <h2 class="text-3xl sm:text-4xl font-bold text-center mb-16">
          Pourquoi nous choisir pour vos <span class="text-pink-500">rencontres intimes</span>
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
          <div class="text-center relative group overflow-hidden rounded-xl">
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent z-10"></div>
            <img src="/images/feature-privacy.jpg" alt="Anonymat" class="w-full h-64 object-cover object-center group-hover:scale-105 transition-transform duration-500" />
            <div class="absolute bottom-0 left-0 right-0 p-6 z-20">
              <div class="bg-gradient-to-br from-pink-500 to-purple-600 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-mask text-2xl text-white"></i>
              </div>
              <h3 class="text-xl font-bold mb-4">Anonymat total</h3>
              <p class="text-gray-300">Votre vie privée est notre priorité. Explorez vos désirs en toute discrétion.</p>
            </div>
          </div>
          
          <div class="text-center relative group overflow-hidden rounded-xl">
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent z-10"></div>
            <img src="/images/feature-profiles.jpg" alt="Profils vérifiés" class="w-full h-64 object-cover object-center group-hover:scale-105 transition-transform duration-500" />
            <div class="absolute bottom-0 left-0 right-0 p-6 z-20">
              <div class="bg-gradient-to-br from-pink-500 to-purple-600 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-fire text-2xl text-white"></i>
              </div>
              <h3 class="text-xl font-bold mb-4">Profils séduisants</h3>
              <p class="text-gray-300">Des profils authentiques et torrides prêts à échanger des moments intenses avec vous.</p>
            </div>
          </div>
          
          <div class="text-center relative group overflow-hidden rounded-xl">
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent z-10"></div>
            <img src="/images/feature-local.jpg" alt="Rencontres locales" class="w-full h-64 object-cover object-center group-hover:scale-105 transition-transform duration-500" />
            <div class="absolute bottom-0 left-0 right-0 p-6 z-20">
              <div class="bg-gradient-to-br from-pink-500 to-purple-600 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-map-marker-alt text-2xl text-white"></i>
              </div>
              <h3 class="text-xl font-bold mb-4">Rencontres locales</h3>
              <p class="text-gray-300">Trouvez des partenaires près de chez vous pour concrétiser vos fantasmes rapidement.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Testimonials avec photos séduisantes -->
    <div class="bg-gradient-to-b from-gray-900 to-black py-20 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto">
        <h2 class="text-3xl sm:text-4xl font-bold text-center text-white mb-16">
          Ce que disent nos <span class="text-pink-500">membres satisfaits</span>
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div class="bg-gray-800/50 backdrop-blur-sm p-6 rounded-xl border border-gray-700 transform transition-all hover:scale-[1.02]">
            <div class="flex items-center mb-4">
              <img src="/images/testimonial-male.jpg" alt="Michael" class="w-16 h-16 rounded-full object-cover mr-4 border-2 border-pink-500" />
              <div>
                <h4 class="text-white font-bold">Michael, 34</h4>
                <div class="flex text-yellow-500">
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                </div>
              </div>
            </div>
            <p class="text-gray-300 italic">"J'ai rencontré des femmes incroyables qui partagent mes fantasmes. Les conversations sont torrides et les rencontres encore plus chaudes ! Je n'aurais jamais cru que ce serait si facile."</p>
          </div>
          
          <div class="bg-gray-800/50 backdrop-blur-sm p-6 rounded-xl border border-gray-700 transform transition-all hover:scale-[1.02]">
            <div class="flex items-center mb-4">
              <img src="/images/testimonial-female.jpg" alt="Sophie" class="w-16 h-16 rounded-full object-cover mr-4 border-2 border-pink-500" />
              <div>
                <h4 class="text-white font-bold">Sophie, 28</h4>
                <div class="flex text-yellow-500">
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star-half-alt"></i>
                </div>
              </div>
            </div>
            <p class="text-gray-300 italic">"Enfin une plateforme où je peux être moi-même et explorer mes désirs sans jugement. Les hommes ici savent ce qu'ils veulent et comment satisfaire une femme !"</p>
          </div>
          
          <div class="bg-gray-800/50 backdrop-blur-sm p-6 rounded-xl border border-gray-700 transform transition-all hover:scale-[1.02]">
            <div class="flex items-center mb-4">
              <img src="/images/testimonial-couple.jpg" alt="Thomas et Julie" class="w-16 h-16 rounded-full object-cover mr-4 border-2 border-pink-500" />
              <div>
                <h4 class="text-white font-bold">Thomas & Julie</h4>
                <div class="flex text-yellow-500">
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                </div>
              </div>
            </div>
            <p class="text-gray-300 italic">"En tant que couple libertin, nous avons trouvé exactement ce que nous cherchions. Les rencontres sont excitantes et les profils sont vraiment authentiques. Notre vie intime n'a jamais été aussi épanouie !"</p>
          </div>
        </div>
      </div>
    </div>
    
    <!-- How it works avec images suggestives -->
    <div class="bg-black py-20 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto">
        <h2 class="text-3xl sm:text-4xl font-bold text-center text-white mb-16">
          Comment ça <span class="text-pink-500">fonctionne</span>
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
          <div class="text-center relative">
            <div class="relative mx-auto mb-6 overflow-hidden rounded-xl w-48 h-48 group">
              <img src="/images/step1-signup.jpg" alt="Inscription" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
              <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent flex items-end justify-center p-4">
                <div class="bg-gradient-to-br from-pink-500 to-purple-600 w-12 h-12 rounded-full flex items-center justify-center absolute top-4 right-4">
                  <span class="text-white font-bold text-xl">1</span>
                </div>
              </div>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">Inscrivez-vous</h3>
            <p class="text-gray-400">Créez votre profil en quelques minutes, c'est gratuit et confidentiel</p>
          </div>
          
          <div class="text-center relative">
            <div class="relative mx-auto mb-6 overflow-hidden rounded-xl w-48 h-48 group">
              <img src="/images/step2-explore.jpg" alt="Exploration" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
              <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent flex items-end justify-center p-4">
                <div class="bg-gradient-to-br from-pink-500 to-purple-600 w-12 h-12 rounded-full flex items-center justify-center absolute top-4 right-4">
                  <span class="text-white font-bold text-xl">2</span>
                </div>
              </div>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">Explorez</h3>
            <p class="text-gray-400">Découvrez des profils qui correspondent à vos désirs et fantasmes</p>
          </div>
          
          <div class="text-center relative">
            <div class="relative mx-auto mb-6 overflow-hidden rounded-xl w-48 h-48 group">
              <img src="/images/step3-chat.jpg" alt="Discussion" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
              <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent flex items-end justify-center p-4">
                <div class="bg-gradient-to-br from-pink-500 to-purple-600 w-12 h-12 rounded-full flex items-center justify-center absolute top-4 right-4">
                  <span class="text-white font-bold text-xl">3</span>
                </div>
              </div>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">Discutez</h3>
            <p class="text-gray-400">Engagez des conversations torrides et sans tabou</p>
          </div>
          
          <div class="text-center relative">
            <div class="relative mx-auto mb-6 overflow-hidden rounded-xl w-48 h-48 group">
              <img src="/images/step4-meet.jpg" alt="Rencontre" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
              <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent flex items-end justify-center p-4">
                <div class="bg-gradient-to-br from-pink-500 to-purple-600 w-12 h-12 rounded-full flex items-center justify-center absolute top-4 right-4">
                  <span class="text-white font-bold text-xl">4</span>
                </div>
              </div>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">Rencontrez</h3>
            <p class="text-gray-400">Passez du virtuel au réel quand vous êtes prêts tous les deux</p>
          </div>
        </div>
      </div>
    </div>
    
    <!-- CTA Section avec image séduisante -->
    <div class="relative py-20 px-4 sm:px-6 lg:px-8 overflow-hidden">
      <!-- Background image -->
      <div class="absolute inset-0 z-0">
        <div class="absolute inset-0 bg-gradient-to-r from-black/90 to-black/70 z-10"></div>
        <img src="/images/cta-background.jpg" alt="Couple" class="w-full h-full object-cover" />
      </div>
      
      <div class="relative z-20 max-w-4xl mx-auto text-center">
        <h2 class="text-3xl sm:text-5xl font-bold text-white mb-8">
          Prêt à vivre des <span class="text-pink-500">expériences inoubliables</span> ?
        </h2>
        <p class="text-xl text-gray-300 mb-10">
          Des milliers de personnes vous attendent déjà. Ne perdez plus une minute !
        </p>
        <Link :href="route('register')" class="px-10 py-5 bg-gradient-to-r from-pink-500 to-purple-600 hover:from-pink-600 hover:to-purple-700 text-white font-bold rounded-full text-xl transition-all transform hover:scale-105 shadow-lg inline-block animate-pulse-slow">
          Commencer maintenant
        </Link>
        <p class="mt-6 text-sm text-gray-400">
          Déjà plus de 50,000 rencontres réussies ce mois-ci
        </p>
      </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-black text-white py-12 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-4 gap-8">
        <div>
          <h3 class="text-xl font-bold mb-4">Notre plateforme</h3>
          <ul class="space-y-2">
            <li><a href="#" class="text-gray-400 hover:text-pink-500 transition">À propos</a></li>
            <li><a href="#" class="text-gray-400 hover:text-pink-500 transition">Comment ça marche</a></li>
            <li><a href="#" class="text-gray-400 hover:text-pink-500 transition">Témoignages</a></li>
            <li><a href="#" class="text-gray-400 hover:text-pink-500 transition">Blog</a></li>
          </ul>
        </div>
        
        <div>
          <h3 class="text-xl font-bold mb-4">Assistance</h3>
          <ul class="space-y-2">
            <li><a href="#" class="text-gray-400 hover:text-pink-500 transition">Centre d'aide</a></li>
            <li><a href="#" class="text-gray-400 hover:text-pink-500 transition">Sécurité</a></li>
            <li><a href="#" class="text-gray-400 hover:text-pink-500 transition">Contact</a></li>
          </ul>
        </div>
        
        <div>
          <h3 class="text-xl font-bold mb-4">Légal</h3>
          <ul class="space-y-2">
            <li><a href="#" class="text-gray-400 hover:text-pink-500 transition">Conditions d'utilisation</a></li>
            <li><a href="#" class="text-gray-400 hover:text-pink-500 transition">Politique de confidentialité</a></li>
            <li><a href="#" class="text-gray-400 hover:text-pink-500 transition">Cookies</a></li>
          </ul>
        </div>
        
        <div>
          <h3 class="text-xl font-bold mb-4">Suivez-nous</h3>
          <div class="flex space-x-4">
            <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-pink-600 transition">
              <i class="fab fa-instagram text-white"></i>
            </a>
            <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-pink-600 transition">
              <i class="fab fa-twitter text-white"></i>
            </a>
            <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-pink-600 transition">
              <i class="fab fa-facebook-f text-white"></i>
            </a>
            <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-pink-600 transition">
              <i class="fab fa-tiktok text-white"></i>
            </a>
          </div>
          <p class="mt-6 text-gray-400">
            Recevez nos conseils de séduction et offres spéciales
          </p>
          <form class="mt-2 flex">
            <input type="email" placeholder="Votre email" class="px-4 py-2 rounded-l-full bg-gray-800 border-0 focus:ring-2 focus:ring-pink-500 text-white w-full" />
            <button type="submit" class="bg-gradient-to-r from-pink-500 to-purple-600 hover:from-pink-600 hover:to-purple-700 text-white px-4 rounded-r-full">
              <i class="fas fa-arrow-right"></i>
            </button>
          </form>
        </div>
      </div>
      
      <div class="max-w-7xl mx-auto mt-12 pt-8 border-t border-gray-800 text-center text-gray-500 text-sm">
        <p>© 2023 VotreApp. Tous droits réservés. Réservé aux adultes de plus de 18 ans.</p>
      </div>
    </footer>
    
    <!-- Age verification modal (can be implemented with a state) -->
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
</script>

<style scoped>
.landing-page {
  font-family: 'Montserrat', sans-serif;
}

.divider {
  display: flex;
  align-items: center;
  text-align: center;
  color: white;
}

.divider::before,
.divider::after {
  content: '';
  flex: 1;
  border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

.divider::before {
  margin-right: 1rem;
}

.divider::after {
  margin-left: 1rem;
}

/* Animation pour le bouton CTA */
@keyframes pulse-slow {
  0% {
    box-shadow: 0 0 0 0 rgba(236, 72, 153, 0.7);
  }
  70% {
    box-shadow: 0 0 0 15px rgba(236, 72, 153, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(236, 72, 153, 0);
  }
}

.animate-pulse-slow {
  animation: pulse-slow 3s infinite;
}

/* Responsive adjustments */
@media (max-width: 640px) {
  .landing-page h1 {
    font-size: 2.5rem;
  }
  
  .landing-page h2 {
    font-size: 2rem;
  }
}
</style>