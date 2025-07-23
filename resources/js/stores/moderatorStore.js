/**
 * Store Pinia pour la gestion de l'état des modérateurs
 * Centralise la gestion des profils, clients et messages
 */
import { defineStore } from 'pinia';
import axios from 'axios';
import webSocketManager from '../services/WebSocketManager';

export const useModeratorStore = defineStore('moderator', {
    state: () => ({
        // État du modérateur
        moderatorId: null,
        moderatorName: null,
        
        // Profils attribués
        assignedProfiles: [],
        currentAssignedProfile: null,
        
        // Clients attribués
        assignedClients: [],
        availableClients: [],
        
        // Conversation actuelle
        selectedClient: null,
        
        // Messages
        messages: {}, // Structure: { clientId: { messages: [], pagination: { page: 1, hasMore: true } } }
        
        // Notifications
        notifications: [],
        
        // État de chargement
        loading: false,
        loadingClients: false,
        isLoadingMore: false,
        
        // État de connexion WebSocket
        webSocketStatus: 'disconnected',
        
        // Erreurs
        errors: {
            profiles: null,
            clients: null,
            messages: null,
            websocket: null
        },

        initialized: false,
        heartbeatInterval: null,
        
        // États de transition de profil
        profileTransition: {
            inProgress: false,
            countdown: 0,
            newProfile: null,
            countdownTimer: null,
            loadingData: false
        },
        // Nouvelles propriétés pour la gestion des profils partagés
        sharedProfiles: [],
        activeModeratorsByProfile: {},
        typingStatus: {},
        currentConversationActivity: null,
        canRequestDelay: true,
        delayRequested: false,
         // Nouvelles propriétés pour le système de file d'attente et verrouillage
        queueInfo: {
            inQueue: false,
            position: null,
            estimatedWaitTime: null,
            queuedAt: null
        },
        lockedProfiles: {}, // Structure: { profileId: { lockedAt, expiresAt, moderatorId } }
        lockedClients: {}, // Structure: { clientId: { lockedAt, expiresAt, profileId } }
        assignmentConflicts: [], // Stocke les conflits d'attribution en cours
    }),
    
    actions: {
        /**
         * Initialise le store avec les informations du modérateur
         */
        async initialize() {
            try {
                console.log('🚀 Initialisation du ModeratorStore...');
                
                // Indiquer que le chargement est en cours
                this.loading = true;
                
                // Charger d'abord les données essentielles du modérateur
                await this.loadModeratorData();
                
                // Initialiser le WebSocket en parallèle avec le chargement des profils
                const webSocketPromise = new Promise(async (resolve) => {
                    this.webSocketStatus = webSocketManager.getConnectionStatus();
                    if (this.webSocketStatus !== 'connected') {
                        console.log('⏳ Initialisation du WebSocketManager...');
                        webSocketManager.initialize().then(() => {
                            this.webSocketStatus = webSocketManager.getConnectionStatus();
                            resolve();
                        }).catch(() => {
                            // En cas d'échec, continuer quand même
                            this.webSocketStatus = 'disconnected';
                            this.errors.websocket = 'Problème de connexion WebSocket';
                            resolve();
                        });
                    } else {
                        resolve();
                    }
                });
                
                // Charger les profils attribués
                const profilesPromise = this.loadAssignedProfiles();
                
                // Attendre que les deux opérations soient terminées
                await Promise.all([webSocketPromise, profilesPromise]);
                
                // Vérifier si le modérateur est en file d'attente
                await this.checkQueueStatus();
                
                // Si un profil principal est attribué, charger les clients
                if (this.currentAssignedProfile) {
                    this.loadingClients = true;
                    this.loadAssignedClients().finally(() => {
                        this.loadingClients = false;
                    });
                    
                    // Configurer les écouteurs WebSocket pour le profil principal
                    this.setupWebSocketListeners();
                } else if (this.queueInfo.inQueue) {
                    console.log('🔍 Modérateur en file d\'attente, position: ' + this.queueInfo.position);
                }
                
                // Configurer les écouteurs WebSocket pour le modérateur
                this.setupModeratorWebSocketListeners();
                
                console.log('✅ ModeratorStore initialisé avec succès');
                this.initialized = true;
                this.startHeartbeat();
                
                // Indiquer que le chargement est terminé
                this.loading = false;
                
                return true;
            } catch (error) {
                console.error('❌ Erreur lors de l\'initialisation du ModeratorStore:', error);
                this.errors.profiles = 'Erreur lors du chargement des données';
                
                if (error.message && error.message.includes('WebSocket')) {
                    this.errors.websocket = 'Problème de connexion WebSocket';
                }
                
                // Réessayer l'initialisation après un délai
                setTimeout(() => this.initialize(), 5000);
                this.loading = false;
                return false;
            }
        },

        /**
         * Vérifier le statut de file d'attente du modérateur
         */
        async checkQueueStatus() {
            try {
                const response = await axios.get('/moderateur/queue/status');
                
                if (response.data.in_queue) {
                    this.queueInfo = {
                        inQueue: true,
                        position: response.data.position,
                        estimatedWaitTime: response.data.estimated_wait_time,
                        queuedAt: response.data.queued_at
                    };
                    console.log('🔍 Modérateur en file d\'attente, position: ' + this.queueInfo.position);
                } else {
                    this.queueInfo.inQueue = false;
                }
                
                return this.queueInfo;
            } catch (error) {
                console.error('❌ Erreur lors de la vérification du statut de file d\'attente:', error);
                return null;
            }
        },

        /**
         * Gérer le changement de position dans la file d'attente
         */
        handleQueuePosition(event) {
            console.log('📩 Événement queue.position.changed reçu:', event);
            
            this.queueInfo = {
                inQueue: true,
                position: event.position,
                estimatedWaitTime: event.estimated_wait_time,
                queuedAt: event.timestamp
            };
            
            // Mettre à jour l'interface pour refléter la position dans la file d'attente
            this.showQueueStatus();
        },
        
        /**
         * Gérer le statut de verrouillage d'un profil
         */
        handleProfileLockStatus(event) {
            console.log('📩 Événement profile.lock.status reçu:', event);
            
            if (event.status === 'locked') {
                // Ajouter ou mettre à jour le verrouillage
                this.lockedProfiles[event.profile_id] = {
                    lockedAt: event.timestamp,
                    expiresAt: event.expires_at,
                    moderatorId: event.moderator_id
                };
            } else if (event.status === 'unlocked') {
                // Supprimer le verrouillage
                if (this.lockedProfiles[event.profile_id]) {
                    delete this.lockedProfiles[event.profile_id];
                }
            }
        },
        
        /**
         * Demander le déverrouillage d'un profil
         */
        async requestProfileUnlock(profileId) {
            try {
                const response = await axios.post('/moderateur/locks/request-unlock', {
                    profile_id: profileId
                });
                
                if (response.data.status === 'success') {
                    console.log('✅ Demande de déverrouillage envoyée avec succès');
                    
                    // Supprimer le verrouillage localement
                    if (this.lockedProfiles[profileId]) {
                        delete this.lockedProfiles[profileId];
                    }
                    
                    return true;
                }
                
                return false;
            } catch (error) {
                console.error('❌ Erreur lors de la demande de déverrouillage:', error);
                return false;
            }
        },
        
        /**
         * Afficher le statut de la file d'attente
         */
        showQueueStatus() {
            if (!this.queueInfo.inQueue) {
                return false;
            }
            
            const remainingTime = this.queueInfo.estimatedWaitTime;
            console.log(`🕒 Position dans la file d'attente: ${this.queueInfo.position}, temps estimé: ${remainingTime} minutes`);
            
            // Cette méthode peut être utilisée pour mettre à jour l'interface utilisateur
            // avec les informations de file d'attente
            
            return true;
        },
        
        /**
         * Gérer la résolution des conflits
         */
        handleConflictResolution(event) {
            console.log('📩 Événement conflict.resolution reçu:', event);
            
            if (event.conflict_type === 'assignment') {
                // Stocker le conflit pour affichage
                this.assignmentConflicts.push({
                    id: Date.now(),
                    type: event.conflict_type,
                    message: event.message,
                    timestamp: event.timestamp,
                    details: event.details
                });
                
                // Si le conflit concerne le profil actuel, recharger les données
                if (event.details.profile_id === this.currentAssignedProfile?.id) {
                    this.loadAssignedProfiles();
                    this.loadAssignedClients();
                }
            }
        },
        
        /**
         * Charge les données du modérateur connecté
         */
        async loadModeratorData() {
            try {
                const response = await axios.get('/moderateur/user-data');
                this.moderatorId = response.data.id;
                this.moderatorName = response.data.name;
                console.log(`👤 Modérateur chargé: ${this.moderatorName} (ID: ${this.moderatorId})`);
            } catch (error) {
                console.error('❌ Erreur lors du chargement des données du modérateur:', error);
                throw error;
            }
        },
        
        /**
         * Charge les profils attribués au modérateur
         */
        async loadAssignedProfiles() {
            this.loading = true;
            this.errors.profiles = null;
            
            try {
                console.log('🔍 Chargement des profils attribués...');
                const response = await axios.get('/moderateur/profile');
                
                if (response.data.profiles) {
                    this.assignedProfiles = response.data.profiles;
                    
                    // Définir le profil principal
                    if (response.data.primaryProfile) {
                        this.currentAssignedProfile = response.data.primaryProfile;
                        console.log(`✅ Profil principal chargé: ${this.currentAssignedProfile.name} (ID: ${this.currentAssignedProfile.id})`);
                    } else if (this.assignedProfiles.length > 0) {
                        // Si aucun profil principal n'est défini mais des profils sont attribués
                        this.currentAssignedProfile = this.assignedProfiles.find(p => p.isPrimary) || this.assignedProfiles[0];
                        console.log(`⚠️ Profil principal non défini, utilisation du premier profil: ${this.currentAssignedProfile.name}`);
                    } else {
                        this.currentAssignedProfile = null;
                        console.warn('⚠️ Aucun profil attribué');
                    }
                } else {
                    this.assignedProfiles = [];
                    this.currentAssignedProfile = null;
                    console.warn('⚠️ Aucun profil retourné par l\'API');
                }
            } catch (error) {
                console.error('❌ Erreur lors du chargement des profils:', error);
                this.errors.profiles = 'Erreur lors du chargement des profils';
                this.assignedProfiles = [];
                this.currentAssignedProfile = null;
            } finally {
                this.loading = false;
            }
        },
        
        /**
         * Charge les clients assignés au modérateur
         */
        async loadAssignedClients() {
            try {
                console.log('🔍 Chargement des clients assignés...');
                
                // Activer l'état de chargement spécifique pour les clients
                this.loadingClients = true;
                
                const response = await axios.get('/moderateur/clients');
                
                if (response.data && Array.isArray(response.data.clients)) {
                    this.assignedClients = response.data.clients;
                    console.log(`✅ ${this.assignedClients.length} clients assignés chargés`);
                    
                    // Si aucun client n'est sélectionné mais qu'il y a des clients assignés, sélectionner le premier
                    if (!this.selectedClient && this.assignedClients.length > 0) {
                        await this.selectClient(this.assignedClients[0]);
                    }
                } else {
                    console.warn('⚠️ Format de réponse inattendu pour les clients assignés');
                    this.assignedClients = [];
                }
                
                return this.assignedClients;
            } catch (error) {
                console.error('❌ Erreur lors du chargement des clients assignés:', error);
                this.errors.clients = 'Erreur lors du chargement des clients';
                return [];
            } finally {
                // Désactiver l'état de chargement des clients
                this.loadingClients = false;
            }
        },
        
        /**
         * Charge les clients disponibles
         */
        async loadAvailableClients() {
            if (!this.currentAssignedProfile) {
                console.warn('⚠️ Impossible de charger les clients disponibles: aucun profil principal attribué');
                return;
            }
            
            this.loading = true;
            
            try {
                console.log('🔍 Chargement des clients disponibles...');
                const response = await axios.get('/moderateur/available-clients');
                
                if (response.data.availableClients) {
                    this.availableClients = response.data.availableClients;
                    console.log(`✅ ${this.availableClients.length} clients disponibles chargés`);
                } else {
                    this.availableClients = [];
                    console.warn('⚠️ Aucun client disponible retourné par l\'API');
                }
            } catch (error) {
                console.error('❌ Erreur lors du chargement des clients disponibles:', error);
                this.availableClients = [];
            } finally {
                this.loading = false;
            }
        },
        
        /**
         * Sélectionne un client et charge ses messages
         */
        async selectClient(client) {
            this.selectedClient = client;
            
            // Réinitialiser la pagination
            if (!this.messages[client.id]) {
                this.messages[client.id] = {
                    messages: [],
                    pagination: { page: 1, hasMore: true }
                };
            } else {
                this.messages[client.id].pagination.page = 1;
                this.messages[client.id].pagination.hasMore = true;
            }
            
            // Charger les messages
            await this.loadMessages(client.id);
            
            // Marquer les notifications comme lues
            this.markClientNotificationsAsRead(client.id);
            
            // Mettre à jour le compteur de messages non lus dans la liste des clients
            const clientIndex = this.assignedClients.findIndex(c => c.id === client.id);
            if (clientIndex !== -1) {
                this.assignedClients[clientIndex].unreadCount = 0;
            }
        },
        
        /**
         * Charge les messages d'un client
         */
        async loadMessages(clientId, page = 1, append = false) {
            if (!this.currentAssignedProfile) {
                console.warn('⚠️ Impossible de charger les messages: aucun profil principal attribué');
                return;
            }
            
            if (append) {
                this.isLoadingMore = true;
            } else {
                this.loading = true;
            }
            
            this.errors.messages = null;
            
            try {
                console.log(`🔍 Chargement des messages pour le client ${clientId}, page ${page}...`);
                
                const response = await axios.get('/moderateur/messages', {
                    params: {
                        client_id: clientId,
                        profile_id: this.currentAssignedProfile.id,
                        page: page,
                        per_page: 20 // Nombre de messages par page
                    }
                });
                
                // Initialiser l'entrée si nécessaire
                if (!this.messages[clientId]) {
                    this.messages[clientId] = {
                        messages: [],
                        pagination: { page: 1, hasMore: true }
                    };
                }
                
                if (response.data.messages) {
                    const loadedMessages = response.data.messages;
                    console.log(`✅ ${loadedMessages.length} messages chargés`);
                    
                    // Mettre à jour les messages
                    if (append) {
                        // Ajouter au début pour les messages plus anciens
                        this.messages[clientId].messages = [...loadedMessages, ...this.messages[clientId].messages];
                    } else {
                        this.messages[clientId].messages = loadedMessages;
                    }
                    
                    // Mettre à jour la pagination
                    this.messages[clientId].pagination = {
                        page: page,
                        hasMore: loadedMessages.length >= 20 // S'il y a au moins 20 messages, il y en a probablement plus
                    };
                } else {
                    if (!append) {
                        this.messages[clientId].messages = [];
                    }
                    this.messages[clientId].pagination.hasMore = false;
                    console.warn('⚠️ Aucun message retourné par l\'API');
                }
            } catch (error) {
                console.error('❌ Erreur lors du chargement des messages:', error);
                this.errors.messages = 'Erreur lors du chargement des messages';
                
                if (!append) {
                    this.messages[clientId].messages = [];
                }
            } finally {
                if (append) {
                    this.isLoadingMore = false;
                } else {
                    this.loading = false;
                }
            }
        },
        
        /**
         * Charge plus de messages (messages plus anciens)
         */
        async loadMoreMessages(clientId) {
            if (!this.messages[clientId] || !this.messages[clientId].pagination.hasMore || this.isLoadingMore) {
                return;
            }
            
            const nextPage = this.messages[clientId].pagination.page + 1;
            await this.loadMessages(clientId, nextPage, true);
        },
        
        /**
         * Envoie un message à un client
         */
        async sendMessage({ clientId, profileId, content, file }) {
            try {
                // Génération d'un ID temporaire pour le message
                const tempId = Date.now().toString();
                
                // Ajouter un message temporaire à l'interface utilisateur immédiatement
                const tempMessage = {
                id: tempId,
                content: content || '',
                sender_id: profileId,
                sender_type: 'profile',
                isFromClient: false,
                time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                isSending: true
                };

                // Initialiser l'entrée si nécessaire
                if (!this.messages[clientId]) {
                this.messages[clientId] = {
                    messages: [],
                    pagination: { page: 1, hasMore: false }
                };
                }
                
                // Ajouter à notre liste de messages (notez l'accès à .messages)
                this.messages[clientId].messages.push(tempMessage);

                // Construire les données pour la requête
                const formData = new FormData();
                formData.append('client_id', clientId);
                formData.append('profile_id', profileId);
                if (content) formData.append('content', content);
                if (file) formData.append('attachment', file);

                // Envoyer le message au serveur
                const response = await axios.post('/moderateur/send-message', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
                });

                // Mettre à jour le message temporaire avec les données réelles
                if (response.data.success) {
                // Récupérer le message de la réponse en vérifiant sa structure
                const actualMessage = response.data.message || response.data.messageData;
                
                if (actualMessage) {
                    // Remplacer le message temporaire par le message réel
                    const tempIndex = this.messages[clientId].messages.findIndex(m => m.id === tempId);
                    if (tempIndex !== -1) {
                    // Créer un nouvel objet message avec les bons champs
                    const formattedMessage = {
                        id: actualMessage.id || tempId,
                        content: actualMessage.content || content || '',
                        isFromClient: false,
                        time: actualMessage.created_at 
                        ? new Date(actualMessage.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                        : tempMessage.time
                    };
                    
                    // Ajouter l'attachment si présent
                    if (actualMessage.attachment) {
                        formattedMessage.attachment = actualMessage.attachment;
                    }
                    
                    this.messages[clientId].messages[tempIndex] = formattedMessage;
                    }
                } else {
                    console.warn("Message envoyé mais structure de réponse API inattendue");
                    // Marquer le message comme envoyé en supprimant l'indicateur de chargement
                    const tempIndex = this.messages[clientId].messages.findIndex(m => m.id === tempId);
                    if (tempIndex !== -1) {
                    this.messages[clientId].messages[tempIndex].isSending = false;
                    }
                }
                }

                return response.data;
            } catch (error) {
                console.error("❌ Erreur lors de l'envoi du message:", error);
                
                // En cas d'erreur, marquer le message comme échoué
                if (this.messages[clientId] && this.messages[clientId].messages) {
                const tempIndex = this.messages[clientId].messages.findIndex(m => m.id === tempId);
                if (tempIndex !== -1) {
                    this.messages[clientId].messages[tempIndex].failed = true;
                    this.messages[clientId].messages[tempIndex].isSending = false;
                }
                }
                
                throw error;
            }
        },
        
        /**
         * Envoie une photo de profil à un client
         */
        async sendProfilePhoto({ profileId, clientId, photoId, photoUrl }) {
            try {
                const response = await axios.post('/moderateur/send-profile-photo', {
                    profile_id: profileId,
                    client_id: clientId,
                    photo_id: photoId
                });
                
                if (response.data.success) {
                    // Créer un message local pour la photo
                    const now = new Date();
                    const photoMessage = {
                        id: response.data.messageId || `photo-${Date.now()}`,
                        content: '',
                        isFromClient: false,
                        time: now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                        date: now.toISOString().split('T')[0],
                        created_at: now.toISOString(),
                        attachment: {
                            url: photoUrl,
                            file_name: 'profile-photo.jpg',
                            mime_type: 'image/jpeg'
                        }
                    };
                    
                    // Initialiser l'entrée si nécessaire
                    if (!this.messages[clientId]) {
                        this.messages[clientId] = {
                            messages: [],
                            pagination: { page: 1, hasMore: false }
                        };
                    }
                    
                    // Ajouter le message
                    this.messages[clientId].messages.push(photoMessage);
                    
                    console.log('✅ Photo de profil envoyée avec succès');
                    return photoMessage;
                } else {
                    throw new Error('Erreur lors de l\'envoi de la photo de profil');
                }
            } catch (error) {
                console.error('❌ Erreur lors de l\'envoi de la photo de profil:', error);
                return null;
            }
        },
        
        /**
         * Démarre une conversation avec un client disponible
         */
        async startConversation(clientId) {
            if (!this.currentAssignedProfile) {
                console.error('❌ Impossible de démarrer une conversation: aucun profil principal attribué');
                return false;
            }
            
            this.loading = true;
            
            try {
                console.log(`🔄 Démarrage d'une conversation avec le client ${clientId}...`);
                
                const response = await axios.post('/moderateur/start-conversation', {
                    client_id: clientId,
                    profile_id: this.currentAssignedProfile.id
                });
                
                if (response.data.success) {
                    // Stocker les messages
                    this.messages[clientId] = {
                        messages: response.data.messages || [],
                        pagination: { page: 1, hasMore: false }
                    };
                    
                    // Ajouter le client à la liste des clients attribués
                    const newClient = {
                        ...response.data.client,
                        profileId: this.currentAssignedProfile.id,
                        profileName: this.currentAssignedProfile.name,
                        profilePhoto: this.currentAssignedProfile.main_photo_path,
                        unreadCount: 0
                    };
                    
                    this.assignedClients.unshift(newClient);
                    
                    // Sélectionner ce client
                    this.selectedClient = newClient;
                    
                    console.log('✅ Conversation démarrée avec succès');
                    return true;
                } else {
                    throw new Error('Erreur lors du démarrage de la conversation');
                }
            } catch (error) {
                console.error('❌ Erreur lors du démarrage de la conversation:', error);
                return false;
            } finally {
                this.loading = false;
            }
        },
        
        /**
         * Ajoute une notification
         */
        addNotification(message, clientId, clientName) {
            const notification = {
                id: Date.now(),
                message,
                clientId,
                clientName,
                timestamp: new Date(),
                read: false
            };
            
            this.notifications.unshift(notification);
            
            // Limiter à 50 notifications maximum
            if (this.notifications.length > 50) {
                this.notifications = this.notifications.slice(0, 50);
            }
            
            // Mettre à jour le compteur de messages non lus dans la liste des clients
            const clientIndex = this.assignedClients.findIndex(c => c.id === clientId);
            if (clientIndex !== -1) {
                this.assignedClients[clientIndex].unreadCount = (this.assignedClients[clientIndex].unreadCount || 0) + 1;
                
                // Mettre à jour la date pour le tri
                this.assignedClients[clientIndex].lastMessageAt = new Date().toISOString();
            }
        },
        
        /**
         * Marque une notification comme lue
         */
        markNotificationAsRead(notificationId) {
            const index = this.notifications.findIndex(n => n.id === notificationId);
            if (index !== -1) {
                this.notifications[index].read = true;
            }
        },
        
        /**
         * Marque toutes les notifications d'un client comme lues
         */
        markClientNotificationsAsRead(clientId) {
            this.notifications.forEach(notification => {
                if (notification.clientId === clientId) {
                    notification.read = true;
                }
            });
        },
        
        /**
         * Configure les écouteurs WebSocket pour le profil principal
         */
        setupWebSocketListeners() {
            if (!this.currentAssignedProfile) {
                console.warn('⚠️ Impossible de configurer les écouteurs WebSocket: aucun profil principal attribué');
                return;
            }
            
            try {
                const profileId = this.currentAssignedProfile.id;
                console.log(`🔄 Configuration des écouteurs WebSocket pour le profil ${profileId}...`);
                
                // S'abonner au canal du profil
                webSocketManager.subscribeToPrivateChannel(`profile.${profileId}`, {
                    '.message.sent': (data) => {
                        console.log('📩 Nouveau message reçu sur le canal profile:', data);
                        
                        // Traiter uniquement les messages des clients
                        if (data.is_from_client) {
                            const clientId = data.client_id;
                            
                            // Vérifier si le message n'existe pas déjà
                            if (this.messages[clientId]?.messages.some(msg => msg.id === data.id)) {
                                console.log('⚠️ Message déjà existant, ignoré');
                                return;
                            }
                            
                            // Ajouter une notification
                            const clientName = this.assignedClients.find(c => c.id === clientId)?.name || 'Client';
                            this.addNotification(data.content, clientId, clientName);
                            
                            // Formater le message
                            const message = {
                                id: data.id,
                                content: data.content,
                                isFromClient: true,
                                time: new Date(data.created_at).toLocaleTimeString([], {
                                    hour: '2-digit',
                                    minute: '2-digit'
                                }),
                                date: new Date(data.created_at).toISOString().split('T')[0],
                                created_at: data.created_at
                            };
                            
                            // Ajouter l'attachement si présent
                            if (data.attachment) {
                                message.attachment = {
                                    url: data.attachment.url,
                                    file_name: data.attachment.file_name,
                                    mime_type: data.attachment.mime_type
                                };
                            }
                            
                            // Initialiser le tableau de messages si nécessaire
                            if (!this.messages[clientId]) {
                                this.messages[clientId] = {
                                    messages: [],
                                    pagination: { page: 1, hasMore: false }
                                };
                            }
                            
                            // Ajouter le nouveau message
                            this.messages[clientId].messages.push(message);
                            
                            // Vérifier si le client existe dans la liste des clients attribués
                            const clientExists = this.assignedClients.some(c => c.id === clientId);
                            
                            if (!clientExists) {
                                // Recharger la liste des clients en arrière-plan
                                this.loadAssignedClients();
                            }
                        }
                    }
                });
                
                // Surveiller l'état de la connexion WebSocket
                window.addEventListener('websocket:disconnected', this.handleWebSocketDisconnected);
                window.addEventListener('websocket:connected', this.handleWebSocketConnected);
                
                // NOUVEAU: Ajouter l'écoute des événements de profil partagé
                this.listenToSharedProfileEvents(profileId);
                
                // NOUVEAU: Vérifier si ce profil est partagé
                if (!this.sharedProfiles.includes(profileId)) {
                    axios.get(`/moderateur/profile/${profileId}/is-shared`)
                        .then(response => {
                            if (response.data.isShared) {
                                this.sharedProfiles.push(profileId);
                            }
                        })
                        .catch(error => console.error('Erreur lors de la vérification du partage de profil:', error));
                }
                
            } catch (error) {
                console.error('❌ Erreur lors de la configuration des écouteurs WebSocket:', error);
                this.errors.websocket = 'Erreur de configuration WebSocket';
            }
        },
        
        /**
         * Gère la déconnexion WebSocket
         */
        handleWebSocketDisconnected() {
            console.warn('🔴 WebSocket déconnecté dans le ModeratorStore');
            this.webSocketStatus = 'disconnected';
            this.errors.websocket = 'Connexion WebSocket perdue. Tentative de reconnexion...';
        },
        
        /**
         * Gère la reconnexion WebSocket
         */
        handleWebSocketConnected() {
            console.log('🟢 WebSocket reconnecté dans le ModeratorStore');
            this.webSocketStatus = 'connected';
            this.errors.websocket = null;
            
            // Reconfigurer les écouteurs après reconnexion
            if (this.currentAssignedProfile) {
                this.setupWebSocketListeners();
                this.setupModeratorWebSocketListeners();
            }
        },

        // Méthode pour gérer les conflits d'attribution
        handleAssignmentConflict(event) {
            console.log('📩 Événement assignment.conflict reçu:', event);
            
            if (event.resolution === 'reassign') {
                // Afficher une notification à l'utilisateur
                this.addNotification(
                    `Un conflit d'attribution a été détecté. Votre profil ${event.profile_name} a été réattribué.`,
                    null,
                    'Système'
                );
                
                // Recharger les profils attribués
                this.loadAssignedProfiles();
            }
        },
        
        /**
         * Configure les écouteurs WebSocket pour le modérateur
         */
        setupModeratorWebSocketListeners() {
            if (!this.moderatorId) {
                console.warn('⚠️ Impossible de configurer les écouteurs WebSocket: ID du modérateur non disponible');
                return;
            }
            
            console.log(`🔄 Configuration des écouteurs WebSocket pour le modérateur ${this.moderatorId}...`);
            
            // S'abonner au canal du modérateur
            webSocketManager.subscribeToPrivateChannel(`moderator.${this.moderatorId}`, {
                '.profile.assigned': async (data) => {
                    console.log('📩 Événement profile.assigned reçu:', data);
                    console.log('📊 État actuel du store avant traitement:', {
                        currentProfile: this.currentAssignedProfile ? this.currentAssignedProfile.id : null,
                        assignedProfiles: this.assignedProfiles.map(p => p.id),
                        isReassignment: data.reason === 'inactivity' || data.old_moderator_id,
                        isForced: data.forced === true,
                        reason: data.reason,
                        oldModeratorId: data.old_moderator_id
                    });

                    const isReassignment = data.reason === 'inactivity' || data.old_moderator_id;
                    const isForced = data.forced === true;

                    if (data.is_primary) {
                        console.log('🔄 Démarrage de la transition vers le nouveau profil principal');

                        // Réinitialiser l'état
                        this.selectedClient = null;
                        this.assignedClients = [];
                        this.messages = {};
                        this.loading = true;

                        // Lancer la transition de profil (affichage loader / animation)
                        this.startProfileTransition(data.profile);

                        setTimeout(async () => {
                            this.profileTransition.loadingData = true;
                            this.loadingClients = true;

                            try {
                                // Recharger les profils attribués
                                await this.loadAssignedProfiles();

                                // Mettre à jour le profil principal
                                this.currentAssignedProfile = data.profile;

                                // Charger les clients du profil principal
                                await this.loadAssignedClients();

                                // Reconfigurer les WebSocket pour le nouveau profil
                                this.setupWebSocketListeners();

                                // Sélectionner un client spécifique si fourni
                                if (data.client_id) {
                                    await this.loadMessages(data.client_id);
                                    const clientInfo = this.assignedClients.find(c => c.id === data.client_id);
                                    if (clientInfo) {
                                        this.selectedClient = clientInfo;
                                    }
                                } else if (this.assignedClients.length > 0) {
                                    // Sélectionner le premier client disponible
                                    const firstClient = this.assignedClients[0];
                                    this.selectedClient = firstClient;
                                    await this.loadMessages(firstClient.id);
                                }

                                console.log('✅ Transition de profil terminée avec succès');
                            } catch (error) {
                                console.error('❌ Erreur lors de la transition de profil:', error);
                            } finally {
                                this.profileTransition.loadingData = false;
                                this.loadingClients = false;
                                this.loading = false;
                                this.endProfileTransition();
                            }
                        }, 3000); // ⏳ Délai de transition visuelle
                    } else {
                        console.log('ℹ️ Mise à jour des données sans changement de profil principal');

                        // Cas d’assignation non principale : juste recharge les profils si besoin
                        await this.loadAssignedProfiles();
                        this.loading = false;
                    }
                },
                
                '.client.assigned': async (data) => {
                    console.log('📩 Événement client.assigned reçu:', data);
                    
                    // Recharger les données après l'attribution d'un client
                    await this.loadAssignedClients();
                    
                    // Si c'est un nouveau client et qu'il n'y a pas de client sélectionné,
                    // on le sélectionne automatiquement
                    if (!this.selectedClient && data.client) {
                        const clientInfo = this.assignedClients.find(c => c.id === data.client.id);
                        if (clientInfo) {
                            await this.selectClient(clientInfo);
                        }
                    }
                },
                // Nouvel écouteur pour les changements de position dans la file d'attente
                '.queue.position.changed': (data) => {
                    this.handleQueuePosition(data);
                },
                
                // Nouvel écouteur pour les changements de statut de verrouillage des profils
                '.profile.lock.status': (data) => {
                    this.handleProfileLockStatus(data);
                },
                
                // Nouvel écouteur pour la résolution des conflits
                '.conflict.resolution': (data) => {
                    this.handleConflictResolution(data);
                },
                'assignment.conflict': (event) => {
                    this.handleAssignmentConflict(event);
                },
                // Nouvel écouteur pour les alertes d'inactivité
                '.inactivity.warning': (data) => {
                    console.log('📩 Alerte d\'inactivité reçue:', data);
                    
                    // Déclencher l'alerte dans le composant avec le délai restant
                    window.dispatchEvent(new CustomEvent('moderator-inactivity', {
                        detail: {
                            remainingSeconds: data.remaining_seconds || 20,
                            profileId: data.profile_id
                        }
                    }));
                }
            });
        },
        
        /**
         * Démarre la transition vers un nouveau profil avec un compte à rebours
         */
        startProfileTransition(newProfile) {
            // Annuler tout compte à rebours en cours
            if (this.profileTransition.countdownTimer) {
                clearInterval(this.profileTransition.countdownTimer);
            }
            
            // Initialiser l'état de transition
            this.profileTransition.inProgress = true;
            this.profileTransition.countdown = 3; // 3 secondes de compte à rebours
            this.profileTransition.newProfile = newProfile;
            
            // Activer le loader global
            this.loading = true; // Ajout: activer le loader global
            
            // Démarrer le compte à rebours
            this.profileTransition.countdownTimer = setInterval(() => {
                this.profileTransition.countdown -= 1;
                
                // Si le compte à rebours est terminé, arrêter le timer
                if (this.profileTransition.countdown <= 0) {
                    clearInterval(this.profileTransition.countdownTimer);
                    this.profileTransition.countdownTimer = null;
                }
            }, 1000);
        },
        
        /**
         * Termine la transition de profil
         */
        endProfileTransition() {
            // Réinitialiser l'état de transition
            this.profileTransition.inProgress = false;
            this.profileTransition.countdown = 0;
            this.profileTransition.newProfile = null;
            this.profileTransition.loadingData = false;
            
            // Annuler le compte à rebours si nécessaire
            if (this.profileTransition.countdownTimer) {
                clearInterval(this.profileTransition.countdownTimer);
                this.profileTransition.countdownTimer = null;
            }
        },
        
        /**
         * Retourne les messages pour un client spécifique
         */
        getMessagesForClient(clientId) {
            return this.messages[clientId]?.messages || [];
        },
        
        /**
         * Vérifie s'il y a plus de messages à charger pour un client
         */
        hasMoreMessages(clientId) {
            return this.messages[clientId]?.pagination.hasMore || false;
        },
        
        /**
         * Retourne un client par son ID
         */
        getClientById(clientId) {
            return this.assignedClients.find(c => c.id === clientId);
        },
        
        /**
         * Retourne les clients attribués triés par date du dernier message
         */
        getSortedAssignedClients() {
            return [...this.assignedClients].sort((a, b) => {
                // Trier par date de dernier message (du plus récent au plus ancien)
                const dateA = a.lastMessageAt ? new Date(a.lastMessageAt) : new Date(0);
                const dateB = b.lastMessageAt ? new Date(b.lastMessageAt) : new Date(0);
                return dateB - dateA;
            });
        },

        /**
         * Configure les écouteurs WebSocket pour un profil spécifique
         * @param {number} profileId - ID du profil
         */
        setupProfileListeners(profileId) {
            if (!profileId) {
                console.warn('⚠️ Impossible de configurer les écouteurs: ID de profil non fourni');
                return;
            }
            
            try {
                console.log(`🔊 Configuration des écouteurs pour le profil ${profileId}`);
                
                // S'abonner au canal du profil
                const channelName = `profile.${profileId}`;
                webSocketManager.subscribeToPrivateChannel(channelName, {
                    // Événement de nouveau message
                    'message.received': (data) => {
                        console.log(`📨 Nouveau message reçu sur le canal ${channelName}:`, data);
                        this.handleNewMessage(data);
                    },
                    
                    // Événement de client en ligne
                    'client.online': (data) => {
                        console.log(`🟢 Client en ligne sur le canal ${channelName}:`, data);
                        this.updateClientStatus(data.clientId, true);
                    },
                    
                    // Événement de client hors ligne
                    'client.offline': (data) => {
                        console.log(`🔴 Client hors ligne sur le canal ${channelName}:`, data);
                        this.updateClientStatus(data.clientId, false);
                    }
                });
            } catch (error) {
                console.error(`❌ Erreur lors de la configuration des écouteurs pour le profil ${profileId}:`, error);
            }
        },
        
        /**
         * Nettoie les ressources du store
         */
        cleanup() {
            // Se désabonner des canaux WebSocket
            if (this.currentAssignedProfile) {
                webSocketManager.unsubscribeFromChannel(`profile.${this.currentAssignedProfile.id}`);
            }
            
            if (this.moderatorId) {
                webSocketManager.unsubscribeFromChannel(`moderator.${this.moderatorId}`);
            }
            
            // Supprimer les écouteurs d'événements
            window.removeEventListener('websocket:disconnected', this.handleWebSocketDisconnected);
            window.removeEventListener('websocket:connected', this.handleWebSocketConnected);
            
            console.log('🧹 ModeratorStore nettoyé');
        },

        /**
         * Envoie un signal heartbeat pour indiquer que le modérateur est actif
         * Cette fonction est appelée périodiquement pour maintenir le statut en ligne
         */
        async sendHeartbeat() {
            try {
                // Collecter les informations d'activité utilisateur
                const activityData = {
                    profile_id: this.currentAssignedProfile?.id,
                    client_id: this.selectedClient?.id,
                    has_user_activity: document.hasFocus() // Vérifie si la fenêtre est active
                };
                
                const response = await axios.post('/moderateur/heartbeat', activityData);
                
                if (response.data.success) {
                    console.log('✅ Heartbeat envoyé avec succès');
                    return true;
                }
            } catch (error) {
                console.error('❌ Erreur lors de l\'envoi du heartbeat:', error);
                return false;
            }
        },

        startHeartbeat() {
            if (!this.initialized) return; // Guard
            if (this.heartbeatInterval) clearInterval(this.heartbeatInterval);
            this.heartbeatInterval = setInterval(() => {
                if (this.initialized) this.sendHeartbeat();
            }, 30000);
        },

        /**
         * Enregistre l'activité de frappe
         */
        async recordTypingActivity(profileId, clientId) {
    // Vérifier si une requête est déjà en cours pour éviter les requêtes multiples
    const typingKey = `${profileId}-${clientId}`;
    
    // Debounce: Si le statut existe déjà et qu'il est récent (moins de 3 secondes), ne rien faire
    if (this.typingStatus[typingKey] && 
        this.typingStatus[typingKey].timestamp && 
        (Date.now() - new Date(this.typingStatus[typingKey].timestamp).getTime() < 3000)) {
        return Promise.resolve(); // ✅ Cohérent avec async
    }
    
    try {
        // Mettre à jour l'état local avant d'envoyer la requête
        this.typingStatus[typingKey] = {
            isTyping: true,
            timestamp: new Date(),
        };
        
        // Envoyer la requête au serveur avec le type d'activité 'typing'
        // MODIFICATION: Utiliser le nouvel endpoint optimisé pour la réactivité
        await axios.post('/moderateur/update-activity', {
            profile_id: profileId,
            client_id: clientId,
            activity_type: 'typing',
            reset_timeout: true // Nouveau paramètre pour signaler explicitement de réinitialiser le timer
        });
        
        // Effacer le statut après 5 secondes
        setTimeout(() => {
            if (this.typingStatus[typingKey]) {
                this.typingStatus[typingKey].isTyping = false;
            }
        }, 5000);
    } catch (error) {
        console.error('Erreur lors de l\'enregistrement de l\'activité:', error);
    }
},
        

        /**
         * Met à jour l'activité de dernière réponse pour un profil et un client
         */
       updateLastMessageActivity(profileId, clientId) {
    try {
        // Mettre à jour l'état local pour indiquer que le modérateur a répondu
        const key = `${profileId}-${clientId}`;
        this.currentConversationActivity = {
            profileId,
            clientId,
            lastResponse: new Date(),
        };
        
        // MODIFICATION: Utiliser le nouvel endpoint avec le paramètre reset_timeout
        axios.post('/moderateur/update-activity', {
            profile_id: profileId,
            client_id: clientId,
            activity_type: 'message_sent',
            reset_timeout: true // Signaler explicitement de réinitialiser le timer
        }).catch(error => {
            console.warn('Erreur lors de la mise à jour de l\'activité:', error);
        });
        
        return true;
    } catch (error) {
        console.error('Erreur lors de la mise à jour de l\'activité de message:', error);
        return false;
    }
},

setupInactivityListeners() {
    // Écouter les événements d'alerte d'inactivité
    window.addEventListener('moderator-inactivity', (event) => {
        const { remainingSeconds, profileId } = event.detail;
        
        // Afficher une alerte à l'utilisateur
        this.showInactivityWarning(remainingSeconds, profileId);
    });
},

showInactivityWarning(remainingSeconds, profileId) {
    // Cette méthode pourrait déclencher une alerte UI
    // ou une notification pour informer le modérateur
    console.warn(`⚠️ Alerte d'inactivité: ${remainingSeconds} secondes restantes avant réattribution du profil ${profileId}`);
    
    // Déclencher un événement que les composants peuvent écouter pour afficher une UI
    window.dispatchEvent(new CustomEvent('show-inactivity-warning', {
        detail: {
            remainingSeconds,
            profileId
        }
    }));
},

subscribeToInactivityEvents() {
    if (!this.moderatorId) return;
    
    // S'abonner au canal spécifique pour les alertes d'inactivité
    webSocketManager.subscribeToPrivateChannel(`moderator.${this.moderatorId}.inactivity`, {
        '.warning': (data) => {
            this.showInactivityWarning(data.remaining_seconds, data.profile_id);
        },
        '.timeout': (data) => {
            // Gérer l'expiration du délai d'inactivité
            console.warn(`⚠️ Timeout d'inactivité pour le profil ${data.profile_id}`);
            
            // Déclencher un événement pour informer l'UI
            window.dispatchEvent(new CustomEvent('inactivity-timeout', {
                detail: {
                    profileId: data.profile_id,
                    reason: data.reason
                }
            }));
        }
    });
},

        /**
         * Demander un délai avant changement de profil
         */
        async requestProfileChangeDelay(profileId, minutes = 5) {
            if (!this.canRequestDelay) return false;
            
            try {
                const response = await axios.post('/moderateur/request-delay', {
                    profile_id: profileId,
                    minutes: minutes,
                });
                
                if (response.data.status === 'success') {
                    this.delayRequested = true;
                    this.canRequestDelay = false;
                    
                    // Réinitialiser après un certain temps
                    setTimeout(() => {
                        this.canRequestDelay = true;
                    }, 15 * 60 * 1000); // 15 minutes
                    
                    return true;
                }
                return false;
            } catch (error) {
                console.error('Erreur lors de la demande de délai:', error);
                return false;
            }
        },

         /**
         * Écouter les événements de profil partagé
         */
        listenToSharedProfileEvents(profileId) {
            if (!window.Echo) return;
            
            window.Echo.private(`profile.${profileId}`)
                .listen('ModeratorActivityEvent', (event) => {
                    // Mettre à jour l'état des activités des autres modérateurs
                    if (event.moderatorId !== this.moderatorId) {
                        this.activeModeratorsByProfile[profileId] = this.activeModeratorsByProfile[profileId] || [];
                        
                        // Ajouter ou mettre à jour l'activité du modérateur
                        const existingIndex = this.activeModeratorsByProfile[profileId].findIndex(
                            m => m.moderatorId === event.moderatorId
                        );
                        
                        const activityData = {
                            moderatorId: event.moderatorId,
                            clientId: event.clientId,
                            activityType: event.activityType,
                            timestamp: event.timestamp,
                        };
                        
                        if (existingIndex >= 0) {
                            this.activeModeratorsByProfile[profileId][existingIndex] = activityData;
                        } else {
                            this.activeModeratorsByProfile[profileId].push(activityData);
                        }
                        
                        // Nettoyer les activités anciennes
                        this.cleanupOldActivities();
                    }
                });
        },

        /**
         * Nettoyer les activités anciennes (plus de 5 minutes)
         */
        cleanupOldActivities() {
            const now = new Date();
            
            Object.keys(this.activeModeratorsByProfile).forEach(profileId => {
                this.activeModeratorsByProfile[profileId] = this.activeModeratorsByProfile[profileId].filter(activity => {
                    const activityTime = new Date(activity.timestamp);
                    return now.getTime() - activityTime.getTime() < 5 * 60 * 1000;
                });
            });
        },

        /**
         * Force le rechargement des données du profil courant
         * Utile quand les données ne sont pas automatiquement mises à jour
         */
        async forceProfileRefresh() {
            console.log('🔄 Forçage du rechargement des données du profil...');
            
            try {
                // Recharger les profils assignés
                await this.loadAssignedProfiles();
                
                // Si un profil est actuellement assigné, recharger ses clients
                if (this.currentAssignedProfile) {
                    await this.loadAssignedClients();
                    
                    // Si un client est sélectionné, recharger ses messages
                    if (this.selectedClient) {
                        await this.loadMessages(this.selectedClient.id);
                    }
                }
                
                console.log('✅ Rechargement forcé des données terminé avec succès');
            } catch (error) {
                console.error('❌ Erreur lors du rechargement forcé des données:', error);
            }
        },

        setupInactivityMonitoring() {
    // Réinitialiser tout timer existant
    if (this.inactivityMonitoringInterval) {
        clearInterval(this.inactivityMonitoringInterval);
    }
    
    // Pas de détection d'inactivité côté client
    // Envoyer simplement des heartbeats réguliers pour signaler l'activité
    
    // Fonction pour envoyer un heartbeat
    const sendHeartbeat = () => {
        this.sendHeartbeat();
    };
    
    // Envoyer un heartbeat toutes les 15 secondes
    this.inactivityMonitoringInterval = setInterval(sendHeartbeat, 15000);
    
    // Écouter les événements WebSocket pour l'alerte d'inactivité
    webSocketManager.subscribeToPrivateChannel(`moderator.${this.moderatorId}`, {
        '.inactivity.warning': (data) => {
            console.log('📩 Alerte d\'inactivité reçue:', data);
            
            // Déclencher l'alerte dans le composant avec le délai restant
            window.dispatchEvent(new CustomEvent('moderator-inactivity', {
                detail: {
                    remainingSeconds: data.remaining_seconds || 20,
                    profileId: data.profile_id
                }
            }));
        }
    });
    
    return () => {
        // Fonction de nettoyage
        clearInterval(this.inactivityMonitoringInterval);
    };
}

    }
});