# Implémentation technique du système de notification pour modérateurs

## 1. Nouvelles migrations à créer

### 1.1 Migration pour ajouter des champs à la table users

-   Ajouter un champ `is_online` (boolean, défaut false) pour suivre si le modérateur est connecté
-   Ajouter un champ `last_online_at` (timestamp, nullable) pour connaître la dernière connexion

### 1.2 Migration pour créer la table moderator_notification_rounds

-   Créer une table pour suivre les cycles de notification avec les champs:
    -   `id` (clé primaire)
    -   `round_number` (integer)
    -   `moderator_ids_notified` (json)
    -   `sent_at` (timestamp)
    -   `pending_messages_count` (integer, défaut 0)
    -   timestamps standards

### 1.3 Migration pour créer la table pending_client_messages

-   Créer une table pour suivre les messages clients en attente avec les champs:
    -   `id` (clé primaire)
    -   `message_id` (clé étrangère vers messages)
    -   `client_id` (clé étrangère vers users)
    -   `profile_id` (clé étrangère vers profiles)
    -   `pending_since` (timestamp)
    -   `is_notified` (boolean, défaut false)
    -   `is_processed` (boolean, défaut false)
    -   timestamps standards
    -   Index combiné sur (is_processed, is_notified, pending_since)

## 2. Nouveaux modèles à créer

### 2.1 Modèle ModeratorNotificationRound

-   Créer un modèle qui correspond à la table moderator_notification_rounds
-   Définir les fillable, les casts (array pour moderator_ids_notified)

### 2.2 Modèle PendingClientMessage

-   Créer un modèle qui correspond à la table pending_client_messages
-   Définir les fillable, les casts
-   Ajouter des relations avec Message, User (client) et Profile

## 3. Mise à jour des modèles existants

### 3.1 Mise à jour du modèle User

-   Ajouter une méthode updateOnlineStatus(bool $isOnline)
-   Ajouter une méthode getActiveConversationsCount() pour calculer la charge de travail directement

### 3.2 Mise à jour du modèle Message

-   Ajouter une relation pendingClientMessage (hasOne)
-   Ajouter une méthode createPendingEntry() pour créer une entrée dans pending_client_messages

## 4. Nouveaux jobs à créer

### 4.1 Job ProcessPendingMessages

-   Créer un job pour traiter les messages clients en attente
-   Logique:
    1. Compter les modérateurs en ligne
    2. Compter les messages clients en attente
    3. Vérifier si les conditions de notification sont remplies
    4. Récupérer le dernier round pour déterminer le numéro du prochain round
    5. Sélectionner les modérateurs à notifier (3 avec la charge la plus faible)
    6. Créer un nouveau round de notification
    7. Envoyer les emails aux modérateurs sélectionnés
    8. Marquer les messages comme notifiés
    9. Planifier la vérification du round dans 30 minutes

### 4.2 Job CheckNotificationRound

-   Créer un job pour vérifier s'il faut passer au round suivant
-   Logique:
    1. Vérifier si les messages sont toujours en attente après 30 minutes
    2. Si oui, lancer un nouveau round avec les 3 modérateurs suivants

### 4.3 Job CalculateModeratorActivity

-   Créer un job pour mettre à jour le statut en ligne des modérateurs
-   Logique:
    1. Récupérer tous les modérateurs
    2. Pour chaque modérateur, vérifier last_activity_at
    3. Si inactif depuis 5+ minutes, marquer comme hors ligne

## 5. Nouveau service à créer

### 5.1 ModeratorNotificationService

-   Créer un service pour centraliser la gestion des notifications
-   Fonctionnalités:
    -   getModeratorsByActivity(): récupérer les modérateurs les moins actifs
    -   createNotificationRound(): créer un nouveau round de notification
    -   sendEmailToModerator(): envoyer un email à un modérateur
    -   shouldTriggerRound(): vérifier si un round doit être déclenché

## 6. Nouvelle notification Laravel à créer

### 6.1 PendingMessageNotification

-   Créer une notification pour les messages en attente
-   Canaux: email uniquement
-   Contenu: informations sur le nombre de messages en attente, lien direct vers l'interface

## 7. Ajout d'un listener pour NewClientMessage

-   Mettre à jour le listener existant pour créer une entrée dans pending_client_messages

## 8. Modifications au niveau du frontend

### 8.1 Mise à jour du moderatorStore.js

-   Ajouter une fonction sendHeartbeat() qui envoie un signal toutes les 2 minutes
-   Ajouter un gestionnaire pour les événements de fermeture de navigateur
    -   Écouter l'événement beforeunload pour mettre à jour le statut en ligne

## 9. Configuration dans bootstrap/app.php

### 9.1 Enregistrement des commandes et jobs

-   Enregistrer les nouveaux jobs dans le schedule pour qu'ils s'exécutent périodiquement:
    ```
    // Jobs à exécuter périodiquement
    $schedule->job(new ProcessPendingMessages())->everyTenMinutes();
    $schedule->job(new CalculateModeratorActivity())->everyFiveMinutes();
    ```

## 10. Mise à jour des API routes dans routes/web.php

### 10.1 Ajout de routes pour le heartbeat modérateur

-   POST /moderateur/heartbeat (pour mettre à jour l'activité)

## 11. Création de nouvelles vues

### 11.1 Création d'un template d'email pour les notifications

-   Créer des templates pour les emails de notification pending_message.blade.php

## Ordre d'implémentation suggéré

1. Créer les migrations et les exécuter
2. Créer les modèles et mettre à jour les modèles existants
3. Créer le service ModeratorNotificationService
4. Créer la notification email PendingMessageNotification
5. Créer les jobs et les configurer dans le schedule
6. Mettre à jour les contrôleurs et ajouter l'endpoint API heartbeat
7. Modifier le frontend pour intégrer le heartbeat et la détection de fermeture de navigateur
8. Créer le template d'email
9. Tester l'ensemble du système

Cette implémentation simplifiée permet de résoudre efficacement la problématique des messages clients sans réponse en mettant en place un système de notification par email ciblant les modérateurs hors ligne avec un système de rotation.
