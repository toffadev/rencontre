<template>
    <!-- No changes to template section -->
</template>

<script>
export default {
    setup() {
        // No changes to setup section
    },
    methods: {
        async initializeEcho() {
            // Écouter le canal des modérateurs pour les nouveaux messages
            window.Echo.private("moderators").listen(
                "NewClientMessage",
                (data) => {
                    // Vérifier si le message est pour un profil que nous gérons déjà
                    const isForCurrentProfile =
                        this.currentProfile?.id === data.profile.id;

                    if (isForCurrentProfile) {
                        // Ajouter le message à la conversation actuelle
                        this.messages.push({
                            id: data.message.id,
                            content: data.message.content,
                            isOutgoing: data.message.is_from_client,
                            time: new Date(
                                data.message.created_at
                            ).toLocaleTimeString(),
                            date: new Date(
                                data.message.created_at
                            ).toLocaleDateString(),
                        });

                        // Mettre à jour le compteur de messages non lus
                        this.updateUnreadCount(
                            data.profile.id,
                            data.unread_count
                        );
                    } else {
                        // Ajouter ou mettre à jour la notification
                        this.updateNotification(data);
                    }
                }
            );

            // Écouter les attributions de profils
            window.Echo.private(`moderator.${this.moderatorId}`).listen(
                "ProfileAssigned",
                (data) => {
                    // Mettre à jour la liste des profils attribués
                    this.updateAssignedProfiles(data.profile);

                    // Si c'est une nouvelle attribution, charger les messages non lus
                    if (data.unread_messages) {
                        this.loadUnreadMessages(
                            data.profile.id,
                            data.unread_messages
                        );
                    }
                }
            );
        },

        updateNotification(data) {
            const existingIndex = this.notifications.findIndex(
                (n) => n.profileId === data.profile.id
            );

            const notification = {
                id: Date.now(),
                profileId: data.profile.id,
                profileName: data.profile.name,
                profilePhoto: data.profile.photo_url,
                message: data.message.content,
                unreadCount: data.unread_count,
                timestamp: new Date(data.message.created_at),
            };

            if (existingIndex !== -1) {
                this.notifications[existingIndex] = notification;
            } else {
                this.notifications.push(notification);
            }

            // Trier les notifications par timestamp
            this.notifications.sort((a, b) => b.timestamp - a.timestamp);
        },

        async loadUnreadMessages(profileId, unreadMessages) {
            // Convertir les messages non lus au format attendu
            const formattedMessages = unreadMessages.map((msg) => ({
                id: msg.id,
                content: msg.content,
                isOutgoing: msg.is_from_client,
                time: new Date(msg.created_at).toLocaleTimeString(),
                date: new Date(msg.created_at).toLocaleDateString(),
            }));

            // Si c'est le profil actuel, ajouter les messages
            if (this.currentProfile?.id === profileId) {
                this.messages = [...this.messages, ...formattedMessages];
            }

            // Mettre à jour le compteur de messages non lus
            this.updateUnreadCount(profileId, unreadMessages.length);
        },

        updateUnreadCount(profileId, count) {
            const profile = this.assignedProfiles.find(
                (p) => p.id === profileId
            );
            if (profile) {
                profile.unreadCount = count;
            }
        },

        async switchProfile(profile) {
            // Sauvegarder l'état de la conversation actuelle si nécessaire
            if (this.currentProfile) {
                this.savedConversations[this.currentProfile.id] = [
                    ...this.messages,
                ];
            }

            // Mettre à jour le profil actuel
            this.currentProfile = profile;

            // Réinitialiser les messages
            this.messages = [];

            // Charger les messages sauvegardés ou faire une nouvelle requête
            if (this.savedConversations[profile.id]) {
                this.messages = [...this.savedConversations[profile.id]];
            } else {
                await this.loadMessages(profile.id);
            }

            // Mettre à jour l'interface
            this.updateProfileUI(profile);
        },

        updateProfileUI(profile) {
            // Mettre à jour les éléments visuels
            this.currentProfile = {
                ...profile,
                photo: profile.main_photo_path,
                name: profile.name,
            };

            // Mettre à jour le titre de la conversation
            document.title = `Chat avec ${profile.name}`;
        },
    },
};
</script>
