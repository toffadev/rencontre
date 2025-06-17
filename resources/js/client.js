import './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { route } from 'ziggy-js';
import { createPinia } from 'pinia';
import { useClientStore } from './stores/clientStore';
import { useModeratorStore } from './stores/moderatorStore';

// Créer l'instance Pinia
const pinia = createPinia();
window.pinia = pinia;

// Initialiser Axios avec le token CSRF
const csrfMetaTag = document.querySelector('meta[name="csrf-token"]');
if (csrfMetaTag && csrfMetaTag.content) {
  window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfMetaTag.content;
  window.axios.defaults.withCredentials = true;
}

// Fonction pour initialiser l'application après le chargement complet
function initializeApplication() {
  // Sur la page de connexion, ne pas essayer d'initialiser les stores
  if (!window.Laravel || !window.Laravel.user) {
    // Si nous avons les props Inertia mais pas window.Laravel, synchroniser
    if (window.page && window.page.props && window.page.props.auth && window.page.props.auth.user) {
      console.log('🔄 Synchronisation des données utilisateur depuis Inertia dans client.js');
      if (!window.Laravel) window.Laravel = {};
      window.Laravel.user = window.page.props.auth.user;
    } else {
      console.log('🔍 Aucun utilisateur connecté, initialisation des stores ignorée');
      return;
    }
  }
  
  // Récupérer le type d'utilisateur depuis window.Laravel
  const userType = window.Laravel.user.type;
  
  // Initialiser le store approprié selon le type d'utilisateur
  if (userType === 'client') {
    // Initialiser le store client
    const clientStore = useClientStore(window.pinia);
    clientStore.initialize().then(() => {
      console.log('✅ Store client initialisé avec succès');
    }).catch(error => {
      console.error('❌ Erreur lors de l\'initialisation du store client:', error);
    });
  } else if (userType === 'moderateur') {
    // Initialiser le store modérateur
    const moderatorStore = useModeratorStore(window.pinia);
    moderatorStore.initialize().then(() => {
      console.log('✅ Store modérateur initialisé avec succès');
    }).catch(error => {
      console.error('❌ Erreur lors de l\'initialisation du store modérateur:', error);
    });
  }
}

createInertiaApp({
  resolve: (name) => resolvePageComponent(`./Client/Pages/${name}.vue`, import.meta.glob('./Client/Pages/**/*.vue')),
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) });
    app.config.globalProperties.$route = route;
    app.use(plugin);
    app.use(pinia); // Utiliser l'instance Pinia
    
    // Initialiser immédiatement si les props sont disponibles
    if (props.initialPage && props.initialPage.props && props.initialPage.props.auth) {
      console.log('🔄 Props Inertia disponibles immédiatement, initialisation...');
      // Synchroniser avec window.Laravel
      if (!window.Laravel) window.Laravel = {};
      window.Laravel.user = props.initialPage.props.auth.user;
      // Initialiser après un court délai pour permettre le montage
      setTimeout(initializeApplication, 50);
    } else {
      // Sinon attendre un peu plus longtemps
      setTimeout(initializeApplication, 300);
    }
    
    return app.mount(el);
  },
  progress: {
    color: '#4B5563',
  },
});

// Ajouter un événement pour réinitialiser après chaque navigation
document.addEventListener('inertia:success', (event) => {
  // Réinitialiser l'application après chaque navigation
  if (event.detail && event.detail.page && event.detail.page.props && event.detail.page.props.auth) {
    console.log('🔄 Navigation Inertia terminée, synchronisation des données...');
    if (!window.Laravel) window.Laravel = {};
    window.Laravel.user = event.detail.page.props.auth.user;
    initializeApplication();
  }
});