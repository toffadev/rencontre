/**
 * Bootstrap pour l'initialisation des services WebSocket
 * Ce fichier est importé dans bootstrap.js
 */
import webSocketManager from './services/WebSocketManager';
import { useModeratorStore } from './stores/moderatorStore';
import { useClientStore } from './stores/clientStore';
import authService from './services/AuthenticationService';
import { createPinia } from 'pinia';

// Create a Pinia instance if not already in window
if (!window.pinia) {
    window.pinia = createPinia();
}

/**
 * Initialisation des services WebSocket
 * Cette fonction est exportée et appelée depuis bootstrap.js
 */
export async function initializeWebSocketServices() {
    console.log('🚀 Initialisation des services WebSocket...');
    // Vérifier d'abord le token CSRF
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!csrfToken) {
        console.warn('⚠️ Token CSRF manquant, tentative de rafraîchissement...');
        try {
            await axios.get('/sanctum/csrf-cookie');
            console.log('✅ Token CSRF rafraîchi');
        } catch (error) {
            console.error('❌ Échec du rafraîchissement du token CSRF:', error);
        }
    }
    
    return new Promise((resolve, reject) => {
        // Vérifier si Echo est déjà initialisé
        if (window.Echo && window.Echo.connector) {
            webSocketManager.initialize().then(() => {
                console.log('✅ Services WebSocket initialisés avec succès');
                resolve(true);
            }).catch(error => {
                console.error('❌ Erreur lors de l\'initialisation des services WebSocket:', error);
                reject(error);
            });
            return;
        }
        
        // Vérifier si nous avons les données utilisateur dans les props Inertia
        if (window.page && window.page.props && window.page.props.auth && window.page.props.auth.user) {
            // Forcer la synchronisation avec window.Laravel
            if (!window.Laravel) window.Laravel = {};
            window.Laravel.user = window.page.props.auth.user;
            console.log('🔄 Données utilisateur synchronisées depuis Inertia vers window.Laravel');
        }
        
        // Attendre que Echo soit initialisé
        const echoInitHandler = () => {
            document.removeEventListener('echo:initialized', echoInitHandler);
            
            webSocketManager.initialize().then(() => {
                console.log('✅ Services WebSocket initialisés avec succès');
                resolve(true);
            }).catch(error => {
                console.error('❌ Erreur lors de l\'initialisation des services WebSocket:', error);
                reject(error);
            });
        };
        
        document.addEventListener('echo:initialized', echoInitHandler);
        
        // Timeout réduit à 5 secondes pour une meilleure UX
        setTimeout(() => {
            document.removeEventListener('echo:initialized', echoInitHandler);
            console.warn('⚠️ Timeout lors de l\'attente de l\'initialisation d\'Echo (5s)');
            
            // Au lieu de rejeter immédiatement, essayer de continuer avec les données disponibles
            if (window.page && window.page.props && window.page.props.auth && window.page.props.auth.user) {
                console.log('🔄 Tentative de récupération avec les données Inertia malgré le timeout...');
                webSocketManager.initialize().then(() => {
                    console.log('✅ Services WebSocket initialisés avec succès (mode dégradé)');
                    resolve(true);
                }).catch(error => {
                    reject(error);
                });
            } else {
                reject(new Error('Timeout lors de l\'attente de l\'initialisation d\'Echo'));
            }
        }, 5000);
    });
}

/**
 * Rafraîchit l'authentification WebSocket
 */
export async function refreshWebSocketAuth() {
    try {
        const response = await axios.post('/refresh-websocket-auth', {
            connection_id: window.Echo?.socketId() || 'unknown'
        });
        console.log('✅ Authentification WebSocket rafraîchie:', response.data);
        return true;
    } catch (error) {
        console.error('❌ Erreur lors du rafraîchissement de l\'authentification WebSocket:', error);
        return false;
    }
}

/**
 * Nettoyage des services WebSocket
 */
export function cleanupWebSocketServices() {
    console.log('🧹 Nettoyage des services WebSocket...');
    
    try {
        // Nettoyer le gestionnaire de WebSocket
        webSocketManager.cleanup();
        
        // Nettoyer le store approprié en fonction du type d'utilisateur
        const userType = document.querySelector('meta[name="user-type"]')?.getAttribute('content');
        
        if (userType === 'moderateur') {
            // Nettoyer le store du modérateur
            const moderatorStore = useModeratorStore(window.pinia);
            moderatorStore.cleanup();
        } else if (userType === 'client') {
            // Nettoyer le store du client
            const clientStore = useClientStore(window.pinia);
            clientStore.cleanup();
        }
        
        console.log('✅ Services WebSocket nettoyés avec succès');
        return true;
    } catch (error) {
        console.error('❌ Erreur lors du nettoyage des services WebSocket:', error);
        return false;
    }
}