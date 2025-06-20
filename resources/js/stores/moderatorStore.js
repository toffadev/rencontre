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
        heartbeatInterval: null
    }),
    
    actions: {
        /**
         * Initialise le store avec les informations du modérateur
         */
        async initialize() {
            try {
                console.log('🚀 Initialisation du ModeratorStore...');
                
                // Charger les données du modérateur
                await this.loadModeratorData();
                
                // S'assurer que le WebSocketManager est initialisé
                this.webSocketStatus = webSocketManager.getConnectionStatus();
                if (this.webSocketStatus !== 'connected') {
                    console.log('⏳ Attente de l\'initialisation du WebSocketManager...');
                    await webSocketManager.initialize();
                    this.webSocketStatus = webSocketManager.getConnectionStatus();
                }
                
                // Charger les profils attribués
                await this.loadAssignedProfiles();
                
                // Si un profil principal est attribué, charger les clients
                if (this.currentAssignedProfile) {
                    await this.loadAssignedClients();
                    
                    // Configurer les écouteurs WebSocket pour le profil principal
                    this.setupWebSocketListeners();
                }
                
                // Configurer les écouteurs WebSocket pour le modérateur
                this.setupModeratorWebSocketListeners();
                
                console.log('✅ ModeratorStore initialisé avec succès');
                this.initialized = true;
                this.startHeartbeat();
                return true;
            } catch (error) {
                console.error('❌ Erreur lors de l\'initialisation du ModeratorStore:', error);
                this.errors.profiles = 'Erreur lors du chargement des données';
                
                if (error.message && error.message.includes('WebSocket')) {
                    this.errors.websocket = 'Problème de connexion WebSocket';
                }
                
                // Réessayer l'initialisation après un délai
                setTimeout(() => this.initialize(), 5000);
                return false;
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
         * Charge les clients attribués au modérateur
         */
        async loadAssignedClients() {
            if (!this.currentAssignedProfile) {
                console.warn('⚠️ Impossible de charger les clients: aucun profil principal attribué');
                return;
            }
            
            this.loading = true;
            this.errors.clients = null;
            
            try {
                console.log('🔍 Chargement des clients attribués...');
                const response = await axios.get('/moderateur/clients');
                
                if (response.data.clients) {
                    this.assignedClients = response.data.clients;
                    console.log(`✅ ${this.assignedClients.length} clients chargés`);
                    
                    // Si un client est sélectionné, mettre à jour ses informations
                    if (this.selectedClient) {
                        const updatedClient = this.assignedClients.find(c => c.id === this.selectedClient.id);
                        if (updatedClient) {
                            this.selectedClient = updatedClient;
                        }
                    }
                } else {
                    this.assignedClients = [];
                    console.warn('⚠️ Aucun client retourné par l\'API');
                }
            } catch (error) {
                console.error('❌ Erreur lors du chargement des clients:', error);
                this.errors.clients = 'Erreur lors du chargement des clients';
                this.assignedClients = [];
            } finally {
                this.loading = false;
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
                    
                    // Recharger les données après l'attribution d'un profil
                    await this.loadAssignedProfiles();
                    
                    // Si le profil attribué est différent du profil actuel et qu'il est principal
                    if (data.profile && 
                        data.profile.id !== this.currentAssignedProfile?.id && 
                        data.is_primary) {
                        
                        // Mettre à jour le profil principal
                        this.currentAssignedProfile = data.profile;
                        
                        // Recharger les clients
                        await this.loadAssignedClients();
                        
                        // Configurer les écouteurs WebSocket pour le nouveau profil
                        this.setupWebSocketListeners();
                        
                        // Si un client est associé à ce changement de profil
                        if (data.client_id) {
                            try {
                                // Charger les messages du client
                                await this.loadMessages(data.client_id);
                                
                                // Trouver et sélectionner le client
                                const clientInfo = this.assignedClients.find(c => c.id === data.client_id);
                                if (clientInfo) {
                                    this.selectedClient = clientInfo;
                                }
                            } catch (error) {
                                console.error('❌ Erreur lors du chargement des messages:', error);
                            }
                        }
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
                }
            });
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
                const response = await axios.post('/heartbeat');
                
                if (response.data.success) {
                    // Mettre à jour l'état local si nécessaire
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
        }
    }
});