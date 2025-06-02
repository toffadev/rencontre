# Documentation du Système de Modération

## 1. Introduction et Vue d'ensemble

Ce document explique le système de modération mis en place dans l'application de rencontres. Le concept principal est le suivant :

-   Les **modérateurs** sont des utilisateurs qui peuvent utiliser des **profils virtuels** pour discuter avec les **clients**.
-   Les clients ne voient pas le modérateur mais uniquement le profil virtuel.
-   **Chaque modérateur peut maintenant gérer PLUSIEURS profils virtuels simultanément** pour répondre efficacement aux messages des clients.
-   **Un profil virtuel peut être utilisé par plusieurs modérateurs, mais les conversations sont distribuées équitablement**.
-   **Le système attribue automatiquement les profils aux modérateurs disponibles**.
-   **Le système attribue automatiquement les clients aux modérateurs selon une logique d'équilibrage de charge, en favorisant toujours le modérateur avec le moins de conversations en cours**.
-   **Lorsqu'un client envoie un message, le système l'attribue automatiquement au modérateur le plus disponible ayant accès au profil concerné**.
-   Les discussions entre les profils virtuels et les clients sont visibles par tous les modérateurs qui ont été attribués à ce profil.

## 2. Structure de la Base de Données

### 2.1 Modèles et Tables

Nous avons créé deux nouveaux modèles principaux :

#### `ModeratorProfileAssignment`

-   Enregistre l'attribution d'un profil à un modérateur.
-   Table: `moderator_profile_assignments`
-   Champs:
    -   `user_id`: ID du modérateur
    -   `profile_id`: ID du profil virtuel
    -   `is_active`: Indique si l'attribution est active
    -   `is_primary`: **Indique si ce profil est le profil principal du modérateur**
    -   `is_exclusive`: **Indique si ce profil est attribué exclusivement à ce modérateur**
    -   `last_activity`: Horodatage de la dernière activité du modérateur

#### `Message`

-   Stocke les messages échangés entre les profils virtuels et les clients.
-   Table: `messages`
-   Champs:
    -   `client_id`: ID du client
    -   `profile_id`: ID du profil virtuel
    -   `moderator_id`: ID du modérateur qui a envoyé le message
    -   `content`: Contenu du message
    -   `is_from_client`: Indique si le message vient du client
    -   `read_at`: Horodatage de lecture du message

### 2.2 Migrations

Nous avons modifié la migration pour la table `moderator_profile_assignments` :

```php
Schema::create('moderator_profile_assignments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('profile_id')->constrained()->onDelete('cascade');
    $table->boolean('is_active')->default(true);
    $table->boolean('is_primary')->default(false); // Profil principal du modérateur
    $table->boolean('is_exclusive')->default(false); // Attribution exclusive
    $table->timestamp('last_activity')->nullable();
    $table->timestamps();

    // Un modérateur ne peut avoir qu'un seul profil principal actif à la fois
    $table->unique(['user_id', 'is_primary', 'is_active'], 'moderator_active_primary_profile');
});
```

**Points importants à noter :**

-   La contrainte d'unicité sur `user_id`, `is_primary` et `is_active` garantit qu'un modérateur ne peut avoir qu'un seul profil principal actif à la fois.
-   Le champ `is_primary` identifie le profil principal d'un modérateur (utilisé par défaut dans l'interface).
-   Le champ `is_exclusive` permet d'attribuer un profil à un seul modérateur si nécessaire.
-   Un modérateur peut maintenant avoir plusieurs attributions actives simultanément.

## 3. Le Service d'Attribution de Profils et Clients

Nous avons considérablement amélioré le service (`ModeratorAssignmentService`) pour gérer l'attribution équilibrée des profils et des clients aux modérateurs :

### 3.1 Méthodes principales

#### `assignProfileToModerator(User $moderator, ?Profile $profile = null, $makePrimary = true)`

-   Attribue un profil à un modérateur.
-   Si aucun profil n'est spécifié, le système en sélectionne un automatiquement.
-   Le paramètre `$makePrimary` permet de définir si ce profil doit être le profil principal du modérateur.
-   Déclenche l'événement `ProfileAssigned`.

#### `getAllAssignedProfiles(User $moderator)`

-   **NOUVEAU** : Récupère tous les profils actuellement attribués à un modérateur.
-   Permet à l'interface utilisateur d'afficher les multiples profils disponibles pour le modérateur.

#### `findLeastBusyModerator($clientId, $profileId)`

-   **NOUVEAU** : Trouve le modérateur avec la charge de travail la plus faible pour gérer un nouveau message client.
-   Prend en compte le nombre de conversations actives pour chaque modérateur.
-   Utilise les priorités suivantes :
    1. Les modérateurs qui ont déjà ce profil attribué et sans conversations
    2. Les modérateurs qui ont déjà ce profil attribué avec la plus faible charge
    3. N'importe quel modérateur sans conversation en cours
    4. Le modérateur avec la plus faible charge de travail

#### `assignClientToModerator($clientId, $profileId)`

-   **NOUVEAU** : Attribue un client à un modérateur spécifique selon la charge de travail.
-   Si le modérateur n'a pas encore le profil attribué, le système le lui attribue automatiquement.
-   Déclenche l'événement `ClientAssigned` pour notifier le modérateur.

#### `updateLastActivity(User $moderator, $profileId = null)`

-   Mise à jour pour accepter un ID de profil spécifique.
-   Permet de mettre à jour l'activité sur un profil particulier ou tous les profils.

#### `getClientsNeedingResponse()`

-   **NOUVEAU** : Récupère tous les clients qui attendent une réponse, ordonnés par priorité.
-   Se concentre sur les messages les plus anciens pour garantir que tous les clients reçoivent une réponse.

#### `processUnassignedMessages()`

-   **NOUVEAU** : Traite tous les messages clients non attribués et les assigner automatiquement aux modérateurs selon la charge de travail.
-   Exécuté régulièrement pour s'assurer qu'aucun client n'est laissé sans réponse.

## 4. Contrôleur pour les Modérateurs

Le `ModeratorController` a été considérablement amélioré pour prendre en charge les multiples profils et l'équilibrage de charge :

### 4.1 Routes

Nous avons ajouté une nouvelle route dans `routes/web.php` :

```php
Route::post('/set-primary-profile', [ModeratorController::class, 'setPrimaryProfile'])->name('set-primary-profile');
```

### 4.2 Méthodes du contrôleur

#### `index()`

-   Vérifie maintenant si le modérateur a des profils attribués (au pluriel).
-   Exécute `processUnassignedMessages()` pour distribuer les messages non attribués aux modérateurs disponibles.

#### `getClients()`

-   Complètement redessiné pour récupérer les clients associés à TOUS les profils attribués au modérateur.
-   Renvoie pour chaque client des informations sur le profil concerné par la conversation.
-   Les clients sont triés par ordre chronologique, les plus anciens messages apparaissant en premier.

#### `getAssignedProfile()`

-   Renommé mais conservé pour la compatibilité avec le frontend.
-   Renvoie maintenant la liste de tous les profils attribués ainsi que le profil principal.

#### `getMessages(Request $request)`

-   Mis à jour pour exiger un `profile_id` spécifique dans la requête.
-   Vérifie que le modérateur a bien accès au profil demandé.

#### `sendMessage(Request $request)`

-   Mis à jour pour exiger un `profile_id` spécifique dans la requête.
-   Vérifie que le modérateur a bien accès au profil demandé.
-   Met à jour l'activité uniquement pour ce profil spécifique.

#### `getAvailableClients()`

-   Amélioré pour prendre en compte tous les profils attribués au modérateur.
-   Pour chaque client, indique l'historique des conversations avec les différents profils du modérateur.

#### `startConversation(Request $request)`

-   Mis à jour pour exiger un `profile_id` spécifique.
-   Si le modérateur n'a pas accès à ce profil, tente de le lui attribuer automatiquement.

#### `setPrimaryProfile(Request $request)` **(NOUVEAU)**

-   Permet à un modérateur de définir l'un de ses profils attribués comme profil principal.
-   Le profil principal est utilisé par défaut dans l'interface utilisateur.

## 5. Système d'Événements en Temps Réel

Pour permettre la communication en temps réel et la notification des nouveaux messages, nous utilisons Laravel Echo et Pusher. Les événements suivants ont été optimisés :

### 5.1 Événement `MessageSent`

-   Déclenché lorsqu'un message est envoyé.
-   Diffuse le message sur deux canaux :
    -   Canal privé du client : `client.{client_id}`
    -   Canal privé du profil : `profile.{profile_id}`
-   Les données diffusées incluent :
    -   Le contenu du message
    -   Les IDs du client et du profil
    -   L'horodatage du message
    -   Les informations du client (nom, avatar)
    -   Le statut du message (lu/non lu)

### 5.2 Événement `ProfileAssigned`

-   Déclenché lorsqu'un profil est attribué à un modérateur.
-   Diffuse sur le canal privé du modérateur : `moderator.{moderator_id}`
-   Les données diffusées incluent :
    -   Les détails du profil attribué
    -   L'historique des conversations associées au profil
    -   Le statut d'activité du profil

### 5.3 Événement `ClientAssigned`

-   Déclenché lorsqu'un client est attribué à un modérateur.
-   Diffuse sur le canal privé du modérateur : `moderator.{moderator_id}`
-   Les données diffusées incluent :
    -   Les détails du client attribué
    -   Le profil concerné par la conversation
    -   L'historique des messages récents
    -   Le statut de la conversation

### 5.4 Gestion des Notifications

-   Les notifications sont gérées de manière cumulative :
    -   Chaque nouveau message crée une nouvelle notification
    -   Les notifications sont stockées dans l'ordre chronologique inverse
    -   Les anciennes notifications restent accessibles
    -   Le modérateur peut naviguer dans l'historique des notifications
-   L'interface affiche :
    -   Le nom du client
    -   Le contenu du message
    -   L'horodatage
    -   Le profil concerné

## 6. Équilibrage de Charge des Messages

L'un des points forts du nouveau système est l'équilibrage intelligent des messages clients entre les modérateurs disponibles :

### 6.1 Critères d'attribution

Le système attribue les messages selon ces critères (par ordre de priorité) :

1. **Modérateurs déjà assignés au profil** : Le système privilégie les modérateurs qui ont déjà le profil concerné, pour assurer la continuité des conversations.
2. **Modérateurs avec moins de charge de travail** : Entre deux modérateurs ayant le même profil, celui qui gère le moins de conversations est privilégié.
3. **Modérateurs sans conversation** : Un modérateur sans aucune conversation en cours sera privilégié par rapport à un modérateur déjà occupé.
4. **Distribution équitable** : Le système compte le nombre de clients uniques avec lesquels chaque modérateur discute activement.

### 6.2 Processus d'attribution

1. Quand un client envoie un message à un profil :

    - L'événement `MessageSent` est capturé par `MessageListener`
    - Le listener appelle `assignClientToModerator`
    - Le service trouve le modérateur le plus approprié selon les critères ci-dessus
    - Si nécessaire, le profil est automatiquement attribué au modérateur sélectionné
    - Un événement `ClientAssigned` est envoyé au modérateur pour le notifier

2. Le modérateur est notifié en temps réel et peut immédiatement voir le nouveau message dans son interface.

## 7. Interface Utilisateur du Modérateur

L'interface utilisateur des modérateurs a été optimisée pour une meilleure gestion des conversations et des notifications :

### 7.1 Structure générale

L'interface est divisée en trois sections principales :

1. **Section Clients (à gauche)**

    - Onglet "Client attribué" :
        - Liste des clients en attente de réponse
        - Notifications triées du plus récent au plus ancien
        - Indicateur de statut pour chaque client
    - Onglet "Clients disponibles" :
        - Liste des clients non attribués
        - Bouton de rafraîchissement
        - Statut de disponibilité

2. **Section Chat (au centre)**

    - En-tête avec les informations du profil actif
    - Zone de conversation avec messages horodatés
    - Zone de saisie avec indicateurs de statut
    - Chargement instantané des conversations

3. **Section Informations (à droite)**
    - Informations détaillées sur le client sélectionné
    - Historique des interactions
    - Options de gestion

### 7.2 Fonctionnalités principales

-   **Gestion multi-profils** :

    -   Affichage clair du profil actif
    -   Changement instantané entre les profils
    -   Indicateur visuel du profil principal

-   **Système de notifications** :

    -   Accumulation des notifications par ordre chronologique inverse
    -   Affichage du nom du client et du contenu du message
    -   Indicateurs visuels pour les nouveaux messages
    -   Navigation facile dans l'historique des notifications

-   **Gestion des conversations** :

    -   Chargement instantané lors du changement de client
    -   Affichage chronologique des messages
    -   Indicateurs de statut (lu/non lu)
    -   Réponse possible sans attendre les messages précédents

-   **Interface réactive** :
    -   Mise à jour en temps réel des notifications
    -   Changement fluide entre les conversations
    -   Indicateurs de chargement pour les actions longues
    -   Gestion optimisée de la mémoire

## 8. Flux de Travail Global

Voici comment fonctionne le système amélioré :

1. **Attribution des profils** :

    - Lorsqu'un modérateur se connecte, le système vérifie s'il a déjà des profils attribués
    - Si non, le système lui attribue automatiquement un profil disponible
    - Le modérateur peut voir tous ses profils attribués et changer de profil actif selon les besoins

2. **Réception des messages clients** :

    - Quand un client envoie un message à un profil :
        - Une nouvelle notification est ajoutée à la liste des notifications (sans remplacer les précédentes)
        - Les notifications sont triées du plus récent au plus ancien
        - Le système recherche le modérateur le plus approprié selon la charge de travail
        - Le profil est automatiquement attribué au modérateur si nécessaire
        - La conversation complète est chargée instantanément lors de la sélection du client
    - Les modérateurs peuvent répondre aux clients dans n'importe quel ordre, sans attendre une réponse précédente

3. **Travail du modérateur** :
    - Le modérateur voit toutes les notifications en attente, triées par ordre chronologique inverse
    - Il peut sélectionner n'importe quelle notification pour répondre au client correspondant
    - Le changement de conversation charge instantanément tous les messages associés
    - Les réponses peuvent être envoyées indépendamment de l'état des autres conversations

## 9. Planification des Tâches avec Laravel 11

Nous avons implémenté un système automatisé pour traiter les messages clients non assignés et équilibrer la charge de travail entre les modérateurs. Ce système utilise la nouvelle architecture de tâches planifiées de Laravel 11.

### 9.1 Structure des Tâches dans Laravel 11

Contrairement aux versions précédentes de Laravel qui utilisaient `app/Console/Kernel.php` pour définir les tâches planifiées, Laravel 11 introduit un nouveau système basé sur des classes dédiées dans le dossier `app/Tasks`.

#### `ProcessUnassignedMessagesTask`

Cette classe représente notre tâche principale pour traiter les messages non assignés :

```php
<?php

namespace App\Tasks;

use App\Services\ModeratorAssignmentService;
use Illuminate\Support\Facades\Log;

class ProcessUnassignedMessagesTask
{
    protected $assignmentService;

    public function __construct(ModeratorAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    // Définit quand la tâche s'exécute (toutes les 2 minutes)
    public function schedule(): string
    {
        return '*/2 * * * *'; // Format cron
    }

    // Code exécuté lors de chaque lancement de la tâche
    public function __invoke(): void
    {
        // Libère d'abord les profils des modérateurs inactifs
        $releasedCount = $this->assignmentService->reassignInactiveProfiles(30);

        // Traite les messages non assignés
        $assignedCount = $this->assignmentService->processUnassignedMessages();

        Log::info("{$assignedCount} client(s) assigné(s) à des modérateurs.");
    }
}
```

Cette tâche effectue deux actions importantes :

1. **Libération des profils inactifs** : Si un modérateur est inactif pendant plus de 30 minutes, ses profils sont libérés.
2. **Attribution des messages** : Les messages clients non attribués sont assignés aux modérateurs selon leur charge de travail.

### 9.2 Enregistrement des Tâches

Pour que Laravel 11 reconnaisse et exécute notre tâche, nous l'avons enregistrée dans le fichier `bootstrap/tasks.php` :

```php
<?php

use App\Tasks\ProcessUnassignedMessagesTask;

return [
    // Autres tâches déjà enregistrées

    // Notre tâche pour traiter les messages non assignés
    ProcessUnassignedMessagesTask::class,
];
```

### 9.3 Commande Artisan Manuelle

En plus de la tâche planifiée automatique, nous avons créé une commande Artisan qui permet d'exécuter manuellement le traitement des messages :

```php
// Dans routes/console.php
Artisan::command('messages:process', function (ModeratorAssignmentService $assignmentService) {
    $this->info('Traitement des messages non assignés...');

    $releasedCount = $assignmentService->reassignInactiveProfiles(30);
    $assignedCount = $assignmentService->processUnassignedMessages();

    $this->info("{$assignedCount} client(s) assigné(s) à des modérateurs.");
})->purpose('Traiter manuellement les messages non assignés');
```

Cette commande peut être exécutée avec :

```bash
php artisan messages:process
```

### 9.4 Fonctionnement Global du Système de Planification

1. **Exécution périodique** : La tâche s'exécute automatiquement toutes les 2 minutes.
2. **Traitement des profils inactifs** : Libère les profils des modérateurs qui n'ont pas été actifs depuis 30 minutes.
3. **Distribution des messages** :
    - Identifie tous les messages clients qui n'ont pas reçu de réponse
    - Pour chaque message, trouve le modérateur le plus approprié selon les critères d'équilibrage
    - Assigne le message au modérateur sélectionné
    - Si nécessaire, attribue automatiquement le profil au modérateur
4. **Notification** : Le modérateur est notifié en temps réel de la nouvelle attribution.

### 9.5 Mise en Production

Pour que le système fonctionne en production, vous devez :

1. Configurer un planificateur de tâches (Cron) pour exécuter `php artisan schedule:run` chaque minute
2. Ou, pour les environnements de développement, exécuter `php artisan schedule:work` qui lance un processus en arrière-plan

Cette approche garantit que :

-   Les clients reçoivent toujours des réponses, même si certains modérateurs sont absents
-   La charge de travail est distribuée équitablement entre tous les modérateurs disponibles
-   Le système s'adapte dynamiquement aux fluctuations d'activité des modérateurs

## 10. Prochaines Étapes

Pour finaliser l'implémentation, il faudra :

1. Mettre à jour l'interface utilisateur pour prendre en charge la gestion multi-profils
2. ~~Implémenter une tâche planifiée pour exécuter régulièrement `processUnassignedMessages()`~~ ✓ (Implémenté)
3. Ajouter des statistiques sur la charge de travail des modérateurs pour l'administration
4. Mettre en place des tests automatisés pour vérifier l'équilibrage de charge
5. Développer une fonction de transfert manuel de conversations entre modérateurs

## 11. Considérations de Sécurité

-   Assurez-vous que les canaux de diffusion sont correctement protégés
-   Vérifiez les autorisations dans les contrôleurs (un modérateur ne doit accéder qu'aux conversations liées aux profils qui lui sont attribués)
-   Protégez les données sensibles des clients
-   Mettez en place des logs d'activité pour auditer les actions des modérateurs

## 12. Conclusion

Ce système amélioré permet une gestion beaucoup plus efficace des conversations entre clients et profils virtuels, avec une distribution équilibrée du travail entre les modérateurs. La possibilité pour un modérateur de gérer plusieurs profils simultanément augmente considérablement la flexibilité et l'efficacité du système, tout en garantissant que les clients reçoivent des réponses rapides à leurs messages.

---

Cette documentation a été mise à jour pour refléter les nouvelles fonctionnalités d'attribution équilibrée des messages et de gestion multi-profils pour les modérateurs.
