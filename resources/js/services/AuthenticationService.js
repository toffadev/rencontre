/**
 * Service centralisé pour la gestion de l'authentification
 * Gère les tokens CSRF, les sessions et les retries
 */
import axios from 'axios';

class AuthenticationService {
    constructor() {
        this.csrfToken = null;
        this.isRefreshing = false;
        this.refreshPromise = null;
        this.refreshListeners = [];
        this.setupInterceptors();
    }

    /**
     * Configure les intercepteurs Axios pour gérer automatiquement les erreurs CSRF
     */
    setupInterceptors() {
        // Intercepteur de requête pour ajouter le token CSRF
        axios.interceptors.request.use(config => {
            const token = this.getCSRFToken();
            if (token) {
                config.headers['X-CSRF-TOKEN'] = token;
            }
            config.headers['X-Requested-With'] = 'XMLHttpRequest';
            config.headers['Accept'] = 'application/json';
            config.withCredentials = true;
            return config;
        });

        // Intercepteur de réponse pour gérer les erreurs CSRF
        axios.interceptors.response.use(
            response => response,
            async error => {
                const originalRequest = error.config;
                
                // Éviter les boucles infinies
                if (originalRequest._retry) {
                    return Promise.reject(error);
                }

                // Gérer les erreurs CSRF (419) ou les erreurs 500 liées au CSRF
                if (error.response?.status === 419 || 
                    (error.response?.status === 500 && 
                     error.response?.data?.message?.includes('CSRF'))) {
                    
                    originalRequest._retry = true;
                    
                    try {
                        await this.refreshCSRFToken();
                        const token = this.getCSRFToken();
                        originalRequest.headers['X-CSRF-TOKEN'] = token;
                        return axios(originalRequest);
                    } catch (refreshError) {
                        console.error('Échec du renouvellement du token CSRF:', refreshError);
                        this.handleAuthenticationFailure();
                        return Promise.reject(refreshError);
                    }
                }
                
                // Gérer les erreurs d'authentification
                if (error.response?.status === 401) {
                    this.handleAuthenticationFailure();
                }
                
                return Promise.reject(error);
            }
        );
    }

    /**
     * Récupère le token CSRF depuis différentes sources
     */
    getCSRFToken() {
        // Si nous avons déjà un token en mémoire, l'utiliser
        if (this.csrfToken) {
            return this.csrfToken;
        }
        
        // Essayer de récupérer depuis les meta tags
        const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (metaToken) {
            this.csrfToken = metaToken;
            return metaToken;
        }
        
        // Essayer de récupérer depuis l'objet Laravel global
        if (window.Laravel && window.Laravel.csrfToken) {
            this.csrfToken = window.Laravel.csrfToken;
            return window.Laravel.csrfToken;
        }
        
        console.warn('Aucun token CSRF trouvé');
        return null;
    }

    /**
     * Rafraîchit le token CSRF en appelant l'endpoint sanctum/csrf-cookie
     */
    async refreshCSRFToken() {
        // Si un rafraîchissement est déjà en cours, retourner la promesse existante
        if (this.isRefreshing) {
            return this.refreshPromise;
        }
        
        this.isRefreshing = true;
        
        try {
            this.refreshPromise = new Promise(async (resolve, reject) => {
                try {
                    console.log('Rafraîchissement du token CSRF...');
                    await axios.get('/sanctum/csrf-cookie');
                    
                    // Attendre un peu pour s'assurer que le cookie est bien défini
                    await new Promise(r => setTimeout(r, 100));
                    
                    // Récupérer le nouveau token depuis les meta tags
                    const newToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    
                    if (newToken) {
                        this.csrfToken = newToken;
                        console.log('Token CSRF rafraîchi avec succès');
                        resolve(newToken);
                    } else {
                        console.error('Échec du rafraîchissement du token CSRF: token non trouvé');
                        reject(new Error('Token CSRF non trouvé après rafraîchissement'));
                    }
                } catch (error) {
                    console.error('Échec du rafraîchissement du token CSRF:', error);
                    reject(error);
                } finally {
                    this.isRefreshing = false;
                }
            });
            
            return await this.refreshPromise;
        } catch (error) {
            this.isRefreshing = false;
            throw error;
        }
    }

    /**
     * Vérifie l'état d'authentification de l'utilisateur
     */
    async checkAuthentication() {
        try {
            const response = await axios.get('/auth/check', { timeout: 5000 });
            return response.data.authenticated;
        } catch (error) {
            console.error('Erreur lors de la vérification de l\'authentification:', error);
            return false;
        }
    }

    /**
     * Gère les échecs d'authentification
     */
    handleAuthenticationFailure() {
        console.error('Échec d\'authentification détecté');
        
        // Stocker l'URL actuelle pour redirection après reconnexion
        const currentPath = window.location.pathname;
        if (currentPath !== '/login') {
            sessionStorage.setItem('auth_redirect', currentPath);
        }
        
        // Afficher une notification à l'utilisateur
        this.showAuthErrorNotification();
    }

    /**
     * Affiche une notification d'erreur d'authentification
     */
    showAuthErrorNotification() {
        // Vérifier si une notification existe déjà pour éviter les doublons
        if (document.querySelector('.auth-error-notification')) {
            return;
        }
        
        const notification = document.createElement('div');
        notification.className = 'auth-error-notification fixed top-4 right-4 bg-red-500 text-white p-4 rounded-lg shadow-lg z-50';
        notification.innerHTML = `
            <div class="flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <div>
                    <p class="font-bold">Session expirée</p>
                    <p class="text-sm">Votre session a expiré. Reconnexion...</p>
                </div>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Rediriger vers la page de connexion après un court délai
        setTimeout(() => {
            window.location.href = '/login';
        }, 3000);
    }

    /**
     * Initialise le service d'authentification
     */
    async initialize() {
        // Récupérer le token CSRF initial
        const token = this.getCSRFToken();
        
        // Si aucun token n'est trouvé, essayer de le rafraîchir
        if (!token) {
            try {
                await this.refreshCSRFToken();
            } catch (error) {
                console.error('Échec de l\'initialisation du token CSRF:', error);
            }
        }
        
        return this;
    }
}

// Créer une instance singleton
const authService = new AuthenticationService();

export default authService;