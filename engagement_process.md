# Processus d'Engagement Utilisateur - Documentation Technique

## Vue d'ensemble

Ce document détaille l'implémentation du système de notifications automatiques pour l'application HeartMatch. Le système vise à augmenter l'engagement des utilisateurs en envoyant trois types de notifications email :

1. **Notification de message non lu** : Envoyée 30 minutes après réception d'un message non consulté
2. **Notification de message en attente de réponse** : Envoyée 2 heures après lecture d'un message sans réponse
3. **Notification de réactivation** : Envoyée après 48h d'inactivité totale

## Modifications de Base de Données

### Nouvelles Migrations

1. **Migration pour ajouter last_activity_at dans la table users**

    - Ajoute un champ timestamp `last_activity_at` pour suivre la dernière activité de l'utilisateur

2. **Migration pour ajouter notification_sent_at dans la table messages**

    - Ajoute un champ timestamp `notification_sent_at` pour éviter les doublons de notifications

3. **Migration pour créer la table client_notifications**
    - Crée une nouvelle table avec les champs : user_id, type, message_id, sent_at, opened_at

## Modèles

### Modifications de Modèles Existants

1. **User.php**

    - Ajout de la relation `clientNotifications()`
    - Ajout de la méthode `updateLastActivity()`

2. **Message.php**
    - Ajout de la relation `notifications()`
    - Ajout de la méthode `markNotificationSent()`

### Nouveaux Modèles

1. **ClientNotification.php**
    - Modèle complet pour gérer les notifications envoyées aux clients
    - Relations avec User et Message
    - Méthode `markAsOpened()` pour suivre l'ouverture des notifications

## Middleware

1. **TrackUserActivity.php**
    - Middleware qui met à jour automatiquement le champ `last_activity_at` à chaque requête
    - À enregistrer dans bootstrap/app.php

## Jobs pour les Notifications

1. **SendUnreadMessageNotification.php**

    - Identifie les messages non lus depuis plus de 30 minutes
    - Crée et envoie les notifications appropriées

2. **SendAwaitingReplyNotification.php**

    - Identifie les messages lus depuis plus de 2 heures sans réponse
    - Crée et envoie les notifications appropriées

3. **SendReactivationNotification.php**
    - Identifie les utilisateurs inactifs depuis 48 heures
    - Crée et envoie les notifications de réactivation

## Classes de Notification

1. **UnreadMessageNotification.php**

    - Notification pour les messages non lus
    - Inclut les paramètres UTM pour le tracking

2. **AwaitingReplyNotification.php**

    - Notification pour les messages en attente de réponse
    - Inclut les paramètres UTM pour le tracking

3. **ReactivationNotification.php**
    - Notification pour les utilisateurs inactifs
    - Inclut les paramètres UTM pour le tracking

## Command Artisan

1. **ProcessNotifications.php**
    - Commande qui exécute les trois jobs de notification
    - À planifier pour s'exécuter toutes les 15 minutes

## Modifications du Kernel

1. **app/Console/Kernel.php**
    - Ajout de la planification pour exécuter `app:process-notifications` toutes les 15 minutes

## Modifications du Controller

1. **HomeController.php**

    - Mise à jour de la méthode `index()` pour gérer les paramètres UTM des notifications
    - Marquage des notifications comme ouvertes
    - Redirection vers le bon profil en cas de clic sur une notification

2. **MessageController.php**
    - Ajout de la méthode `markSingleMessageAsRead()` pour marquer un message spécifique comme lu

## Modifications Frontend

### ClientStore.js

1. **Nouvelles propriétés d'état**

    - `lastActivity`: Timestamp de la dernière activité
    - `activityInterval`: Intervalle pour vérifier l'activité
    - `heartbeatInterval`: Intervalle pour envoyer les heartbeats
    - `messageObserver`: Observateur pour détecter les messages visibles

2. **Nouvelles méthodes**

    - `setupActivityTracking()`: Configure le suivi d'activité utilisateur
    - `setupMessageReadTracking()`: Configure le suivi de lecture des messages
    - `markMessageAsRead()`: Marque un message spécifique comme lu
    - `observeMessages()`: Observe les nouveaux messages pour les marquer comme lus

3. **Mise à jour de méthodes existantes**
    - `initialize()`: Ajout de l'initialisation du tracking d'activité et de lecture
    - `cleanup()`: Nettoyage des ressources de tracking

### Home.vue

1. **Ajout d'attributs data aux messages**
    - `data-message-id`: ID du message
    - `data-profile-id`: ID du profil
    - `data-is-from-client`: Si le message vient du client
    - `data-is-read`: Si le message a été lu

## Nouvelles Routes

1. **Route pour le heartbeat**

    - `/user/heartbeat`: Endpoint pour mettre à jour l'activité utilisateur

2. **Route pour marquer un message comme lu**
    - `/messages/mark-as-read-single`: Endpoint pour marquer un message spécifique comme lu

## Résumé des Fichiers Modifiés/Créés

### Migrations

-   `XXXX_XX_XX_add_last_activity_at_to_users_table.php` (Nouveau)
-   `XXXX_XX_XX_add_notification_sent_at_to_messages_table.php` (Nouveau)
-   `XXXX_XX_XX_create_client_notifications_table.php` (Nouveau)

### Modèles

-   `app/Models/User.php` (Modifié)
-   `app/Models/Message.php` (Modifié)
-   `app/Models/ClientNotification.php` (Nouveau)

### Middleware

-   `app/Http/Middleware/TrackUserActivity.php` (Nouveau)

### Jobs

-   `app/Jobs/SendUnreadMessageNotification.php` (Nouveau)
-   `app/Jobs/SendAwaitingReplyNotification.php` (Nouveau)
-   `app/Jobs/SendReactivationNotification.php` (Nouveau)

### Notifications

-   `app/Notifications/UnreadMessageNotification.php` (Nouveau)
-   `app/Notifications/AwaitingReplyNotification.php` (Nouveau)
-   `app/Notifications/ReactivationNotification.php` (Nouveau)

### Commands

-   `app/Console/Commands/ProcessNotifications.php` (Nouveau)

### Controllers

-   `app/Http/Controllers/Client/HomeController.php` (Modifié)
-   `app/Http/Controllers/Client/MessageController.php` (Modifié)

### Frontend

-   `resources/js/stores/clientStore.js` (Modifié)
-   `resources/js/Client/Pages/Home.vue` (Modifié)

### Configuration

-   `app/Console/Kernel.php` (Modifié)
-   `bootstrap/app.php` (Modifié pour enregistrer le middleware)

### Routes

-   `routes/web.php` (Modifié pour ajouter les nouvelles routes)
