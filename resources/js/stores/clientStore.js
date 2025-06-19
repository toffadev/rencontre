/**
 * Store centralisé pour la gestion de l'état client
 * Gère les messages, les points, les profils et les états de conversation
 */
import { defineStore } from 'pinia';
import axios from 'axios';
import webSocketManager from '@/services/WebSocketManager';

export const useClientStore = defineStore('client', {
    state: () => ({
        loading: false,
        initialized: false,
        clientId: null,
        clientName: '',
        messagesMap: {},
        conversationStates: {},
        points: {
            balance: 0,
            transactions: []
        },
        blockedProfileIds: [],
        reportedProfiles: [],
        channelSubscribed: false,
        errors: {},
        lastActivity: Date.now(),
        activityInterval: null,
        heartbeatInterval: null,
    }),

    getters: {
        /**
         * Récupère les messages pour un profil spécifique
         */
        getMessagesForProfile: (state) => (profileId) => {
            return state.messagesMap[profileId] || [];
        },

        /**
         * Vérifie si une réponse est attendue pour un profil
         */
        isAwaitingReply: (state) => (profileId) => {
            const state_ = state.conversationStates[profileId];
            return state_ ? state_.awaitingReply : false;
        },

        /**
         * Récupère le nombre de messages non lus pour un profil
         */
        getUnreadCount: (state) => (profileId) => {
            const state_ = state.conversationStates[profileId];
            return state_ ? state_.unreadCount : 0;
        }
    },

    actions: {
        /**
         * Initialise le store client
         */
        async initialize() {
            try {
                // Si déjà initialisé, ne rien faire
                if (this.initialized) return this;
                
                this.loading = true;
                
                // Récupérer l'ID du client depuis l'objet window ou des métadonnées
                const clientData = document.querySelector('meta[name="user-id"]')?.getAttribute('content');
                if (clientData) {
                    this.clientId = parseInt(clientData);
                }
                
                // S'assurer que le WebSocketManager est initialisé
                if (!webSocketManager.isInitialized) {
                    await webSocketManager.initialize();
                }
                
                // Charger les données du client
                await this.loadClientData();
                
                // Charger les points de l'utilisateur
                await this.loadPoints();
                
                // Charger les profils bloqués
                await this.loadBlockedProfiles();
                
                // Charger les conversations actives
                await this.loadAllConversations();
                
                // Configurer les écouteurs WebSocket
                this.setupClientListeners();
                
                // Configurer le tracking d'activité
                this.setupActivityTracking();
                
                // Configurer le tracking de lecture des messages
                this.setupMessageReadTracking();
                
                this.loading = false;
                this.initialized = true;
                
                console.log('✅ ClientStore initialisé avec succès');
                
                return this;
            } catch (error) {
                console.error('❌ Erreur lors de l\'initialisation du ClientStore:', error);
                this.errors.initialization = error.message;
                this.loading = false;
                
                // Planifier une réinitialisation après un délai
                setTimeout(() => {
                    this.initialized = false;
                    this.initialize();
                }, 5000);
                
                throw error;
            }
        },
        
        /**
         * Charge les données du client connecté
         */
        async loadClientData() {
            try {
                const response = await axios.get('/auth/check');
                if (response.data.authenticated && response.data.user) {
                    this.clientId = response.data.user.id;
                    this.clientName = response.data.user.name;
                }
                console.log(`👤 Client chargé: ${this.clientName} (ID: ${this.clientId})`);
            } catch (error) {
                console.error('❌ Erreur lors du chargement des données du client:', error);
                throw error;
            }
        },
        
        /**
         * Charge les points du client
         */
        async loadPoints() {
            try {
                console.log('🔍 Chargement des points...');
                const response = await axios.get("/points/data");
                
                if (response.data && response.data.points !== undefined) {
                    this.points.balance = response.data.points;
                    this.points.transactions = response.data.transactions || [];
                    console.log(`✅ Solde de points chargé: ${this.points.balance} points`);
                } else {
                    this.points.balance = 0;
                    this.points.transactions = [];
                    console.warn('⚠️ Aucune donnée de points retournée par l\'API');
                }
            } catch (error) {
                console.error('❌ Erreur lors du chargement du solde de points:', error);
                this.errors.points = 'Erreur lors du chargement du solde de points';
                this.points.balance = 0;
                this.points.transactions = [];
            }
        },
        
        /**
         * Charge les profils bloqués par le client
         */
        async loadBlockedProfiles() {
            try {
                console.log('🔍 Chargement des profils bloqués...');
                const response = await axios.get("/blocked-profiles");
                
                if (response.data) {
                    this.blockedProfileIds = response.data.blocked_profiles || [];
                    this.reportedProfiles = response.data.reported_profiles || [];
                    console.log(`✅ ${this.blockedProfileIds.length} profils bloqués chargés`);
                    console.log(`✅ ${this.reportedProfiles.length} profils signalés chargés`);
                }
            } catch (error) {
                console.error('❌ Erreur lors du chargement des profils bloqués:', error);
                this.errors.blockedProfiles = 'Erreur lors du chargement des profils bloqués';
                this.blockedProfileIds = [];
                this.reportedProfiles = [];
            }
        },
        
        /**
         * Charge toutes les conversations actives
         */
        async loadAllConversations() {
            try {
                console.log('🔍 Chargement des conversations actives...');
                const response = await axios.get("/active-conversations");
                
                if (response.data && response.data.conversations) {
                    for (const conv of response.data.conversations) {
                        // Initialiser l'état de la conversation
                        this.initConversationState(conv.profile_id, {
                            unreadCount: conv.unread_count || 0,
                            lastReadMessageId: conv.last_read_message_id,
                            isOpen: false,
                            hasBeenOpened: conv.has_been_opened || false,
                            awaitingReply: conv.awaiting_reply || false
                        });
                        
                        // Charger les messages
                        await this.loadMessages(conv.profile_id);
                    }
                    console.log(`✅ ${response.data.conversations.length} conversations chargées`);
                }
            } catch (error) {
                console.error('❌ Erreur lors du chargement des conversations:', error);
                this.errors.conversations = 'Erreur lors du chargement des conversations';
            }
        },
        
        /**
         * Charge les messages d'un profil spécifique
         */
        async loadMessages(profileId) {
            try {
                console.log(`🔍 Chargement des messages pour le profil ${profileId}...`);
                const response = await axios.get("/messages", {
                    params: { profile_id: profileId }
                });
                
                if (response.data && response.data.messages) {
                    this.messagesMap[profileId] = response.data.messages;
                    
                    // Mettre à jour l'état de la conversation si fourni
                    if (response.data.conversation_state) {
                        const state = response.data.conversation_state;
                        this.initConversationState(profileId, {
                            unreadCount: state.unread_count || 0,
                            lastReadMessageId: state.last_read_message_id,
                            isOpen: false,
                            hasBeenOpened: state.has_been_opened || false,
                            awaitingReply: state.awaiting_reply || false
                        });
                    }
                    
                    console.log(`✅ ${this.messagesMap[profileId].length} messages chargés pour le profil ${profileId}`);
                }
            } catch (error) {
                console.error(`❌ Erreur lors du chargement des messages pour le profil ${profileId}:`, error);
                this.errors.messages = `Erreur lors du chargement des messages pour le profil ${profileId}`;
            }
        },
        
        /**
         * Envoie un message à un profil
         */
        async sendMessage({ profileId, content, file }) {
    if ((!content || !content.trim()) && !file) {
        console.warn('⚠️ Tentative d\'envoi de message vide');
        return;
    }
    
    if (!profileId) {
        console.error('❌ ID de profil manquant pour l\'envoi du message');
        return;
    }
    
    const formData = new FormData();
    formData.append('profile_id', profileId);
    
    if (content && content.trim()) {
        formData.append('content', content);
    }
    
    if (file) {
        formData.append('attachment', file);
    }
    
    // Créer un message local temporaire
    const now = new Date();
    const timeString = now.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
    
    const localMessage = {
        id: "temp-" + Date.now(),
        content: content,
        isOutgoing: true,
        time: timeString,
        date: now.toISOString().split("T")[0],
        pending: true,
    };
    
    if (file) {
        localMessage.attachment = {
            url: URL.createObjectURL(file),
            file_name: file.name,
            mime_type: file.type
        };
    }
    
    // Ajouter le message local à la liste
    if (!this.messagesMap[profileId]) {
        this.messagesMap[profileId] = [];
    }
    this.messagesMap[profileId].push(localMessage);
    
    // Récupérer le token CSRF actuel
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    try {
        console.log(`🚀 Envoi d'un message au profil ${profileId}...`);
        const response = await axios.post("/send-message", formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            }
        });
        
        // Mettre à jour le solde de points
        if (response.data.remaining_points !== undefined) {
            this.points.balance = response.data.remaining_points;
        }
        
        // Remplacer le message temporaire par le message réel
        if (response.data.success && response.data.messageData) {
            const index = this.messagesMap[profileId].findIndex(
                (msg) => msg.id === localMessage.id
            );
            if (index !== -1) {
                this.messagesMap[profileId][index] = response.data.messageData;
            }
            console.log(`✅ Message envoyé avec succès au profil ${profileId}`);
        }
        
        return response.data;
    } catch (error) {
        // Gestion spécifique des erreurs CSRF (419)
        if (error.response?.status === 419) {
            console.warn(`⚠️ Erreur CSRF lors de l'envoi du message au profil ${profileId}, tentative de rafraîchissement du token...`);
            try {
                // Rafraîchir le token CSRF
                await axios.get('/sanctum/csrf-cookie');
                
                // Réessayer avec le nouveau token
                const newToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                
                if (newToken) {
                    formData.append('_token', newToken); // Ajouter le token au FormData également
                    
                    const retryResponse = await axios.post("/send-message", formData, {
                        headers: {
                            'Content-Type': 'multipart/form-data',
                            'X-CSRF-TOKEN': newToken,
                            'Accept': 'application/json'
                        }
                    });
                    
                    // Traiter la réponse comme avant
                    if (retryResponse.data.remaining_points !== undefined) {
                        this.points.balance = retryResponse.data.remaining_points;
                    }
                    
                    if (retryResponse.data.success && retryResponse.data.messageData) {
                        const index = this.messagesMap[profileId].findIndex(
                            (msg) => msg.id === localMessage.id
                        );
                        if (index !== -1) {
                            this.messagesMap[profileId][index] = retryResponse.data.messageData;
                        }
                        console.log(`✅ Message envoyé avec succès au profil ${profileId} après rafraîchissement du token`);
                    }
                    
                    return retryResponse.data;
                }
            } catch (retryError) {
                console.error(`❌ Échec après tentative de rafraîchissement du token:`, retryError);
                
                // Marquer le message comme échoué
                const index = this.messagesMap[profileId].findIndex(
                    (msg) => msg.id === localMessage.id
                );
                if (index !== -1) {
                    this.messagesMap[profileId][index].failed = true;
                    this.messagesMap[profileId][index].pending = false;
                }
                
                throw retryError;
            }
        } else {
            console.error(`❌ Erreur lors de l'envoi du message au profil ${profileId}:`, error);
            
            // Marquer le message comme échoué
            const index = this.messagesMap[profileId].findIndex(
                (msg) => msg.id === localMessage.id
            );
            if (index !== -1) {
                this.messagesMap[profileId][index].failed = true;
                this.messagesMap[profileId][index].pending = false;
            }
            
            throw error;
        }
    }
},
        
        /**
         * Marque une conversation comme lue
         */
        async markConversationAsRead(profileId) {
            if (!profileId || !this.conversationStates[profileId]) {
                return;
            }
            
            const state = this.conversationStates[profileId];
            const messages = this.messagesMap[profileId] || [];
            const lastMessage = messages[messages.length - 1];
            
            if (lastMessage) {
                try {
                    console.log(`🔍 Marquage de la conversation avec le profil ${profileId} comme lue...`);
                    
                    // Mettre à jour l'état local immédiatement pour une meilleure UX
                    state.lastReadMessageId = lastMessage.id;
                    state.hasBeenOpened = true;
                    state.isOpen = true;
                    state.unreadCount = 0;
                    
                    // Récupérer le token CSRF actuel
                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    
                    // Appeler l'API pour persister l'état
                    await axios.post('/messages/mark-as-read', {
                        profile_id: profileId,
                        last_message_id: lastMessage.id
                    }, {
                        headers: {
                            'X-CSRF-TOKEN': token,
                            'Accept': 'application/json'
                        }
                    });
                    
                    console.log(`✅ Conversation avec le profil ${profileId} marquée comme lue`);
                } catch (error) {
                    if (error.response?.status === 419) {
                        console.warn(`⚠️ Erreur CSRF lors du marquage de la conversation ${profileId}, tentative de rafraîchissement du token...`);
                        try {
                            // Rafraîchir le token CSRF
                            await axios.get('/sanctum/csrf-cookie');
                            
                            // Réessayer avec le nouveau token
                            const newToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                            
                            if (newToken) {
                                await axios.post('/messages/mark-as-read', {
                                    profile_id: profileId,
                                    last_message_id: lastMessage.id
                                }, {
                                    headers: {
                                        'X-CSRF-TOKEN': newToken,
                                        'Accept': 'application/json'
                                    }
                                });
                                console.log(`✅ Conversation avec le profil ${profileId} marquée comme lue après rafraîchissement du token`);
                            }
                        } catch (retryError) {
                            console.error(`❌ Échec après tentative de rafraîchissement du token:`, retryError);
                        }
                    } else {
                        console.error(`❌ Erreur lors du marquage de la conversation avec le profil ${profileId} comme lue:`, error);
                    }
                }
            }
        },
        
        /**
         * Initialise l'état d'une conversation
         */
        initConversationState(profileId, initialState = null) {
            if (!this.conversationStates[profileId]) {
                this.conversationStates[profileId] = initialState || {
                    unreadCount: 0,
                    lastReadMessageId: null,
                    isOpen: false,
                    hasBeenOpened: false,
                    awaitingReply: false
                };
            }
        },
        
        /**
         * Vérifie si un profil est en discussion active
         */
        async checkActiveDiscussion(profileId) {
            try {
                console.log(`🔍 Vérification de la discussion active pour le profil ${profileId}...`);
                const response = await axios.get(`/check-active-discussion/${profileId}`);
                console.log(`✅ Discussion active vérifiée pour le profil ${profileId}`);
                return response.data.moderator_id;
            } catch (error) {
                console.error(`❌ Erreur lors de la vérification de la discussion active pour le profil ${profileId}:`, error);
                return null;
            }
        },
        
        /**
         * Ajoute un profil à la liste des profils signalés
         */
        addReportedProfile(profileId) {
            if (!this.reportedProfiles.find(rp => rp.profile_id === profileId)) {
                this.reportedProfiles.push({
                    profile_id: profileId,
                    status: 'pending'
                });
                console.log(`✅ Profil ${profileId} ajouté à la liste des profils signalés`);
            }
        },
        
        /**
         * Configure les écouteurs WebSocket pour le client
         */
        setupClientListeners() {
            if (!this.clientId) {
                console.error('❌ ID client non disponible pour configurer les écouteurs WebSocket');
                return;
            }
            
            // Éviter les abonnements multiples
            if (this.channelSubscribed) {
                console.log(`⚠️ Client ${this.clientId} déjà abonné aux canaux WebSocket`);
                return;
            }
            
            console.log(`🔌 Configuration des écouteurs WebSocket pour le client ${this.clientId}...`);
            
            try {
                // S'abonner au canal privé du client avec plusieurs tentatives
                const maxAttempts = 3;
                let attempt = 0;
                
                const subscribeWithRetry = () => {
                    attempt++;
                    console.log(`🔄 Tentative d'abonnement WebSocket ${attempt}/${maxAttempts}...`);
                    
                    const channel = webSocketManager.subscribeToPrivateChannel(`client.${this.clientId}`, {
                        '.message.sent': async (data) => {
                            console.log(`📨 Nouveau message reçu du profil ${data.profile_id}`);
                            const profileId = data.profile_id;
                            
                            // Initialiser l'état de la conversation si nécessaire
                            this.initConversationState(profileId);
                            
                            // Recharger les messages
                            await this.loadMessages(profileId);
                            
                            // Mettre à jour les points
                            await this.loadPoints();
                            
                            // Mettre à jour le compteur si ce n'est pas la conversation active
                            const state = this.conversationStates[profileId];
                            if (state && state.isOpen === false) {
                                state.unreadCount = (state.unreadCount || 0) + 1;
                                state.awaitingReply = true;
                            }
                        },
                        '.points.updated': (data) => {
                            console.log(`💰 Points mis à jour: ${data.points}`);
                            this.points.balance = data.points;
                        }
                    });
                    
                    if (channel) {
                        this.channelSubscribed = true;
                        console.log(`✅ Abonnement WebSocket réussi pour le client ${this.clientId}`);
                    } else if (attempt < maxAttempts) {
                        console.warn(`⚠️ Échec de l'abonnement WebSocket, nouvelle tentative dans 2s...`);
                        setTimeout(subscribeWithRetry, 2000);
                    } else {
                        console.error(`❌ Impossible d'établir l'abonnement WebSocket après ${maxAttempts} tentatives`);
                    }
                };
                
                // Démarrer les tentatives d'abonnement
                if (webSocketManager.isConnected()) {
                    subscribeWithRetry();
                } else {
                    console.log('⏳ WebSocket non connecté, attente de la connexion...');
                    
                    // Attendre que la connexion soit établie
                    const checkConnectionInterval = setInterval(() => {
                        if (webSocketManager.isConnected()) {
                            clearInterval(checkConnectionInterval);
                            subscribeWithRetry();
                        }
                    }, 1000);
                    
                    // Définir un timeout pour éviter d'attendre indéfiniment
                    setTimeout(() => {
                        clearInterval(checkConnectionInterval);
                        if (!this.channelSubscribed) {
                            console.error('❌ Timeout lors de l\'attente de la connexion WebSocket');
                        }
                    }, 10000);
                }
                
            } catch (error) {
                console.error('❌ Erreur lors de la configuration des écouteurs WebSocket:', error);
            }
        },

        // Ajouter dans actions
        /**
         * Initialise le tracking d'activité utilisateur
         */
        setupActivityTracking() {
            // Événements à surveiller pour l'activité
            const activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
            
            // Fonction pour mettre à jour le timestamp de dernière activité
            const updateActivity = () => {
                this.lastActivity = Date.now();
            };
            
            // Ajouter les écouteurs d'événements
            activityEvents.forEach(event => {
                window.addEventListener(event, updateActivity, { passive: true });
            });
            
            // Vérifier l'activité toutes les 30 secondes
            this.activityInterval = setInterval(() => {
                const now = Date.now();
                const inactiveTime = now - this.lastActivity;
                
                // Si l'utilisateur est actif dans les 5 dernières minutes
                if (inactiveTime < 5 * 60 * 1000) {
                    console.log('👤 Utilisateur actif, dernier mouvement il y a', Math.round(inactiveTime / 1000), 'secondes');
                } else {
                    console.log('💤 Utilisateur inactif depuis', Math.round(inactiveTime / 60000), 'minutes');
                }
            }, 30000);
            
            // Envoyer un heartbeat toutes les 2 minutes si l'utilisateur est actif
            this.heartbeatInterval = setInterval(async () => {
                const now = Date.now();
                const inactiveTime = now - this.lastActivity;
                
                // Si l'utilisateur est actif dans les 5 dernières minutes, envoyer un heartbeat
                if (inactiveTime < 5 * 60 * 1000) {
                    try {
                        await axios.post('/user/heartbeat');
                        console.log('💓 Heartbeat envoyé au serveur');
                    } catch (error) {
                        console.error('❌ Erreur lors de l\'envoi du heartbeat:', error);
                    }
                }
            }, 2 * 60 * 1000); // 2 minutes
        },

        /**
         * Marque automatiquement les messages comme lus lorsqu'ils sont visibles
         */
        setupMessageReadTracking() {
            // Utiliser IntersectionObserver pour détecter les messages visibles
            if ('IntersectionObserver' in window) {
                // Configuration de l'observateur
                const options = {
                    root: document.querySelector('.chat-container'),
                    rootMargin: '0px',
                    threshold: 0.5 // 50% visible
                };
                
                // Créer l'observateur
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const messageId = entry.target.dataset.messageId;
                            const profileId = entry.target.dataset.profileId;
                            const isFromClient = entry.target.dataset.isFromClient === 'true';
                            
                            // Ne marquer comme lu que les messages entrants
                            if (messageId && profileId && !isFromClient) {
                                this.markMessageAsRead(messageId, profileId);
                            }
                        }
                    });
                }, options);
                
                // Observer les messages (à appeler après le rendu des messages)
                this.messageObserver = observer;
            }
        },

        /**
         * Marque un message spécifique comme lu
         */
        async markMessageAsRead(messageId, profileId) {
    try {
        // Récupérer le token CSRF actuel
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        // Appeler l'API pour marquer le message comme lu
        await axios.post('/messages/mark-as-read', { // Changez cette ligne
            message_id: messageId,
            profile_id: profileId,
            is_single: true // Ajoutez ce paramètre pour différencier
        }, {
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            }
        });
        
        console.log(`✅ Message ${messageId} marqué comme lu`);
    } catch (error) {
        console.error(`❌ Erreur lors du marquage du message ${messageId} comme lu:`, error);
    }
},

        /**
         * Observe les nouveaux messages pour les marquer comme lus
         */
        observeMessages(profileId) {
            if (this.messageObserver) {
                // Sélectionner tous les messages non-lus du profil actuel
                const messages = document.querySelectorAll(`.message-in[data-profile-id="${profileId}"][data-is-read="false"]`);
                
                messages.forEach(message => {
                    this.messageObserver.observe(message);
                });
            }
        },


        
        /**
         * Nettoie les ressources lors de la déconnexion
         */
        cleanup() {
            console.log('🧹 Nettoyage des ressources du ClientStore...');
            
            // Nettoyer les intervalles
            if (this.activityInterval) {
                clearInterval(this.activityInterval);
            }
            
            if (this.heartbeatInterval) {
                clearInterval(this.heartbeatInterval);
            }
            
            // Nettoyer l'observateur de messages
            if (this.messageObserver) {
                this.messageObserver.disconnect();
            }
            
            // Quitter les canaux spécifiques au client
            if (this.clientId && this.channelSubscribed) {
                webSocketManager.unsubscribeFromChannel(`client.${this.clientId}`);
                this.channelSubscribed = false;
            }
            
            console.log('✅ Ressources du ClientStore nettoyées');
        }
    }
});