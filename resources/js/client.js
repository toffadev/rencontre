import './bootstrap';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { route } from 'ziggy-js';

// Initialiser Axios avec le token CSRF
const csrfMetaTag = document.querySelector('meta[name="csrf-token"]');
if (csrfMetaTag && csrfMetaTag.content) {
  window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfMetaTag.content;
}

createInertiaApp({
  resolve: (name) => resolvePageComponent(`./Client/Pages/${name}.vue`, import.meta.glob('./Client/Pages/**/*.vue')),
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) });
    app.config.globalProperties.$route = route;
    app.use(plugin);
    return app.mount(el);
  },
  progress: {
    color: '#4B5563',
  },
});