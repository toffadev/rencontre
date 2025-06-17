/**
 * Composable pour la gestion unifiée des erreurs
 * Fournit des stratégies de retry et de feedback utilisateur
 */
import { ref, computed } from 'vue';
import webSocketManager from '../services/WebSocketManager';
import authService from '../services/AuthenticationService';

export function useErrorHandler() {
    const errors = ref({});
    const retryAttempts = ref({});
    const maxRetries = ref(3);
    const isRecovering = ref(false);
    const lastErrorTime = ref(null);

    // Erreurs regroupées par catégorie
    const errorsByCategory = computed(() => {
        const categories = {
            auth: [],
            websocket: [],
            api: [],
            other: []
        };
        
        Object.entries(errors.value).forEach(([key, error]) => {
            if (key.startsWith('auth')) {
                categories.auth.push(error);
            } else if (key.startsWith('websocket')) {
                categories.websocket.push(error);
            } else if (key.startsWith('api')) {
                categories.api.push(error);
            } else {
                categories.other.push(error);
            }
        });
        
        return categories;
    });

    // Vérifier si des erreurs critiques sont présentes
    const hasCriticalErrors = computed(() => {
        return errorsByCategory.value.auth.length > 0 || 
               errorsByCategory.value.websocket.length > 0;
    });

    /**
     * Ajoute une erreur au registre
     */
    function addError(key, message, details = {}) {
        errors.value[key] = {
            message,
            details,
            timestamp: new Date(),
            handled: false
        };
        
        lastErrorTime.value = new Date();
        
        console.error(`❌ Erreur [${key}]: ${message}`, details);
        
        // Tenter une récupération automatique selon le type d'erreur
        handleErrorRecovery(key, message, details);
    }

    /**
     * Supprime une erreur du registre
     */
    function clearError(key) {
        if (errors.value[key]) {
            delete errors.value[key];
        }
    }

    /**
     * Supprime toutes les erreurs
     */
    function clearAllErrors() {
        errors.value = {};
    }

    /**
     * Marque une erreur comme traitée
     */
    function markErrorAsHandled(key) {
        if (errors.value[key]) {
            errors.value[key].handled = true;
        }
    }

    /**
     * Gère les tentatives de récupération automatique
     */
    async function handleErrorRecovery(key, message, details) {
        // Éviter les récupérations simultanées
        if (isRecovering.value) {
            return;
        }
        
        // Initialiser le compteur de tentatives si nécessaire
        if (!retryAttempts.value[key]) {
            retryAttempts.value[key] = 0;
        }
        
        // Vérifier si le nombre maximum de tentatives est atteint
        if (retryAttempts.value[key] >= maxRetries.value) {
            console.warn(`⚠️ Nombre maximum de tentatives atteint pour l'erreur [${key}]`);
            return;
        }
        
        isRecovering.value = true;
        retryAttempts.value[key]++;
        
        try {
            // Stratégies de récupération selon le type d'erreur
            if (key.startsWith('auth')) {
                await handleAuthError(key, message, details);
            } else if (key.startsWith('websocket')) {
                await handleWebSocketError(key, message, details);
            } else if (key.startsWith('api')) {
                await handleApiError(key, message, details);
            }
            
            // Si la récupération réussit, supprimer l'erreur
            clearError(key);
            console.log(`✅ Récupération réussie pour l'erreur [${key}]`);
        } catch (error) {
            console.error(`❌ Échec de la récupération pour l'erreur [${key}]:`, error);
            
            // Planifier une nouvelle tentative avec délai exponentiel
            const delay = Math.min(1000 * Math.pow(2, retryAttempts.value[key]), 30000);
            
            setTimeout(() => {
                handleErrorRecovery(key, message, details);
            }, delay);
        } finally {
            isRecovering.value = false;
        }
    }

    /**
     * Gère les erreurs d'authentification
     */
    async function handleAuthError(key, message, details) {
        console.log(`🔄 Tentative de récupération d'erreur d'authentification [${key}]...`);
        
        if (message.includes('CSRF') || details.status === 419) {
            // Rafraîchir le token CSRF
            await authService.refreshCSRFToken();
        } else if (details.status === 401) {
            // Session expirée, rediriger vers la page de connexion
            authService.handleAuthenticationFailure();
            throw new Error('Session expirée, redirection nécessaire');
        }
    }

    /**
     * Gère les erreurs WebSocket
     */
    async function handleWebSocketError(key, message, details) {
        console.log(`🔄 Tentative de récupération d'erreur WebSocket [${key}]...`);
        
        // Réinitialiser la connexion WebSocket
        webSocketManager.cleanup();
        await new Promise(resolve => setTimeout(resolve, 1000));
        await webSocketManager.initialize();
        
        // Vérifier si la connexion est rétablie
        if (!webSocketManager.isConnected()) {
            throw new Error('Échec de la reconnexion WebSocket');
        }
    }

    /**
     * Gère les erreurs d'API
     */
    async function handleApiError(key, message, details) {
        console.log(`🔄 Tentative de récupération d'erreur API [${key}]...`);
        
        if (details.status === 429) {
            // Rate limiting, attendre avant de réessayer
            const retryAfter = details.headers?.['retry-after'] || 5;
            console.log(`⏳ Rate limit atteint, attente de ${retryAfter} secondes...`);
            await new Promise(resolve => setTimeout(resolve, retryAfter * 1000));
        } else if (details.status >= 500) {
            // Erreur serveur, attendre avant de réessayer
            await new Promise(resolve => setTimeout(resolve, 5000));
        }
    }

    /**
     * Affiche une notification d'erreur à l'utilisateur
     */
    function showErrorNotification(message, type = 'error', duration = 5000) {
        // Vérifier si une notification existe déjà pour éviter les doublons
        if (document.querySelector(`.notification-${type}`)) {
            return;
        }
        
        const notification = document.createElement('div');
        notification.className = `notification-${type} fixed bottom-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
            type === 'error' ? 'bg-red-500 text-white' : 
            type === 'warning' ? 'bg-yellow-500 text-white' : 
            'bg-blue-500 text-white'
        }`;
        
        notification.innerHTML = `
            <div class="flex items-center">
                <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <div>
                    <p>${message}</p>
                </div>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Supprimer la notification après la durée spécifiée
        setTimeout(() => {
            notification.remove();
        }, duration);
    }

    /**
     * Gère les erreurs Axios
     */
    function handleAxiosError(error, context = '') {
        const status = error.response?.status;
        const data = error.response?.data;
        
        let key = `api.${context || 'unknown'}`;
        let message = error.message || 'Erreur de requête API';
        
        if (status === 419) {
            key = 'auth.csrf';
            message = 'Erreur CSRF, session expirée';
        } else if (status === 401) {
            key = 'auth.unauthorized';
            message = 'Non autorisé, authentification requise';
        } else if (status === 403) {
            key = 'auth.forbidden';
            message = 'Accès interdit';
        } else if (status === 404) {
            key = `api.notFound.${context}`;
            message = 'Ressource non trouvée';
        } else if (status === 422) {
            key = `api.validation.${context}`;
            message = 'Erreur de validation';
        } else if (status === 429) {
            key = 'api.rateLimit';
            message = 'Trop de requêtes, veuillez réessayer plus tard';
        } else if (status >= 500) {
            key = 'api.server';
            message = 'Erreur serveur';
        }
        
        addError(key, message, {
            status,
            data,
            url: error.config?.url,
            method: error.config?.method,
            headers: error.response?.headers
        });
        
        // Afficher une notification pour certains types d'erreurs
        if (status === 401 || status === 403) {
            showErrorNotification('Session expirée, reconnexion nécessaire', 'error');
        } else if (status >= 500) {
            showErrorNotification('Une erreur serveur est survenue, veuillez réessayer plus tard', 'error');
        } else if (status === 429) {
            showErrorNotification('Trop de requêtes, veuillez patienter quelques instants', 'warning');
        }
        
        return { key, message };
    }

    /**
     * Gère les erreurs WebSocket
     */
    function handleWebSocketError(error, channelName = '') {
        const key = `websocket.${channelName || 'connection'}`;
        const message = error.message || 'Erreur de connexion WebSocket';
        
        addError(key, message, {
            channelName,
            error
        });
        
        // Afficher une notification
        showErrorNotification('Problème de connexion en temps réel, tentative de reconnexion...', 'warning');
        
        return { key, message };
    }

    return {
        errors,
        errorsByCategory,
        hasCriticalErrors,
        addError,
        clearError,
        clearAllErrors,
        markErrorAsHandled,
        showErrorNotification,
        handleAxiosError,
        handleWebSocketError
    };
}