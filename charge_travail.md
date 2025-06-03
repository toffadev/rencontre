# Analyse de la Répartition de la Charge de Travail des Modérateurs

## État Actuel du Système

### 1. Composants Impliqués

-   `ModeratorAssignmentService`: Service principal gérant l'attribution des profils
-   `ModeratorProfileAssignment`: Modèle gérant les assignations profil-modérateur
-   `ModeratorController`: Contrôleur gérant les interactions des modérateurs

### 2. Logique Actuelle de Répartition

#### 2.1 Méthode `findLeastBusyModerator`

```php
// Dans ModeratorAssignmentService.php
public function findLeastBusyModerator($clientId, $profileId)
{
    // Récupère les modérateurs actifs
    $onlineModerators = User::where('type', 'moderateur')
        ->where('status', 'active')
        ->get();

    // Calcule la charge de travail basée sur le nombre de clients uniques
    // dans les 30 dernières minutes
    foreach ($onlineModerators as $moderator) {
        $activeClientCount = DB::table('messages')
            ->join('moderator_profile_assignments', ...)
            ->where('messages.created_at', '>', now()->subMinutes(30))
            ->distinct('messages.client_id')
            ->count('messages.client_id');

        $workloads[$moderator->id] = $activeClientCount;
    }
}
```

#### 2.2 Problèmes Identifiés

1. **Calcul de la Charge de Travail**

    - Le système compte uniquement les clients uniques des 30 dernières minutes
    - Ne prend pas en compte si les messages ont déjà reçu une réponse
    - Ne considère pas les profils inactifs qui devraient être libérés

2. **Priorités d'Attribution**

    - Favorise les modérateurs ayant déjà le profil assigné
    - Ne redistribue pas équitablement quand un modérateur a fini de répondre

3. **Gestion des Profils Multiples**
    - Pas de limite claire sur le nombre de profils par modérateur
    - Ne libère pas les profils après réponse aux messages

## Solution Proposée

### 1. Modifications du Calcul de Charge

1. **Nouvelle Logique de Calcul**

    - Compter uniquement les conversations actives sans réponse
    - Libérer automatiquement les profils après réponse
    - Implémenter une limite de profils par modérateur

2. **Équilibrage Amélioré**
    - Redistribuer les profils après chaque réponse
    - Assurer une distribution équitable entre modérateurs

### 2. Changements Nécessaires

1. **Dans `ModeratorAssignmentService`**

    - Modifier le calcul de la charge de travail
    - Ajouter la libération automatique des profils
    - Implémenter une redistribution équitable

2. **Dans `ModeratorProfileAssignment`**
    - Ajouter un statut pour les profils (actif/en attente/terminé)
    - Gérer la durée de vie des assignations

## Implémentation

Les modifications nécessaires seront apportées dans les fichiers suivants :

-   `app/Services/ModeratorAssignmentService.php`
-   `app/Models/ModeratorProfileAssignment.php`

Les changements se concentreront sur :

1. La logique de calcul de charge
2. La gestion des assignations
3. La redistribution équitable des profils

Ces modifications permettront d'assurer que :

-   Chaque modérateur reçoit un nombre équitable de profils
-   Les profils sont libérés après réponse aux messages
-   La charge est équilibrée dynamiquement

## Problème de Synchronisation de l'Interface

### Situation Initiale

Lors du changement automatique de profil (par exemple quand un nouveau client écrit), plusieurs problèmes de synchronisation ont été identifiés :

1. **Désynchronisation des Messages**

    - Les informations du profil (nom, photo) sont mises à jour correctement
    - Cependant, les messages affichés restent ceux de l'ancien profil
    - L'utilisateur doit cliquer manuellement sur la notification pour voir les nouveaux messages

2. **Impact sur l'Expérience Utilisateur**
    - Confusion possible pour le modérateur
    - Risque d'erreurs dans les réponses
    - Perte de temps dans le traitement des messages

### Solution Implémentée

1. **Amélioration de la Gestion des Événements**

    - Mise en place d'une écoute active des changements de profil
    - Synchronisation automatique des messages lors du changement de profil
    - Mise à jour immédiate de l'interface sans intervention manuelle

2. **Modifications Techniques**

    - Ajout d'un watcher sur le profil courant dans le composant Vue
    - Chargement automatique des messages du nouveau profil
    - Mise à jour de l'interface en temps réel

3. **Gestion des Notifications**
    - Conservation du système de notifications comme backup
    - Amélioration de la visibilité des changements de profil
    - Indication claire du profil actif et des messages associés

### Détails Techniques des Modifications

1. **Amélioration du Watcher sur currentAssignedProfile**

    ```javascript
    watch(currentAssignedProfile, async (newProfile, oldProfile) => {
        if (newProfile && window.Echo) {
            listenToProfileMessages(newProfile.id);

            // Gestion du changement de profil
            if (oldProfile && newProfile.id !== oldProfile.id) {
                await loadAssignedData();
                if (assignedClient.value.length > 0) {
                    const mostRecentClient = assignedClient.value[0];
                    await selectClient(mostRecentClient);
                }
            }
        }
    });
    ```

2. **Optimisation de la Fonction selectClient**

    ```javascript
    const selectClient = async (client) => {
        selectedClient.value = client;
        hasMoreMessages.value = true;
        currentPage.value[client.id] = 1;

        try {
            const profileId = currentAssignedProfile.value?.id;
            if (!profileId) return;

            await loadMessages(client.id, 1, false);

            // Gestion automatique des notifications
            const notification = notifications.value.find(
                (n) => n.clientId === client.id && !n.read
            );
            if (notification) {
                markNotificationAsRead(notification.id);
            }
        } catch (error) {
            console.error("Erreur lors de la sélection du client:", error);
        }
    };
    ```

3. **Amélioration de l'Événement profile.assigned**

    ```javascript
    .listen(".profile.assigned", async (data) => {
      await loadAssignedData();

      if (data.profile &&
          data.profile.id !== currentAssignedProfile.value?.id &&
          data.is_primary) {

        currentAssignedProfile.value = data.profile;

        if (data.client_id) {
          const clientResponse = await axios.get("/moderateur/messages", {
            params: {
              client_id: data.client_id,
              profile_id: data.profile.id,
            },
          });

          if (clientResponse.data.messages) {
            chatMessages.value[data.client_id] = clientResponse.data.messages;
            const clientInfo = assignedClient.value.find(c => c.id === data.client_id);
            if (clientInfo) {
              selectedClient.value = clientInfo;
            }
          }
        }
      }
    });
    ```

4. **Gestion Améliorée des Messages en Temps Réel**

    ```javascript
    const listenToProfileMessages = (profileId) => {
        if (window.Echo) {
            window.Echo.leave(`profile.${profileId}`); // Éviter les doublons
        }

        window.Echo.private(`profile.${profileId}`).listen(
            ".message.sent",
            async (data) => {
                if (data.is_from_client) {
                    // Vérification des doublons
                    if (
                        chatMessages.value[clientId]?.some(
                            (msg) => msg.id === data.id
                        )
                    ) {
                        return;
                    }

                    // Mise à jour automatique des messages
                    chatMessages.value[clientId].push({
                        id: data.id,
                        content: data.content,
                        isFromClient: true,
                        time: new Date(data.created_at).toLocaleTimeString(),
                    });

                    // Mise à jour de l'interface
                    if (selectedClient.value?.id === clientId) {
                        nextTick(() => {
                            if (chatContainer.value) {
                                chatContainer.value.scrollTop =
                                    chatContainer.value.scrollHeight;
                            }
                        });
                    }
                }
            }
        );
    };
    ```

Ces modifications garantissent :

-   Une synchronisation immédiate lors des changements de profil
-   Une mise à jour automatique des messages sans intervention manuelle
-   Une meilleure gestion des notifications et de l'état de l'interface
-   Une expérience utilisateur plus fluide et cohérente

Les modérateurs peuvent maintenant voir instantanément les messages du nouveau profil attribué sans avoir à cliquer sur les notifications, et l'interface reste synchronisée en permanence avec le profil actif.
