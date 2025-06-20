# Implémentation technique du système de notification pour modérateurs (Version Simplifiée)

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
    -   `pending_messages_count` (integer)
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

-   Ajouter une méthode `updateOnlineStatus(bool $isOnline)`
-   Ajouter une méthode `getActiveConversationsCount()` qui compte les conversations actives

### 3.2 Mise à jour du modèle Message

-   Ajouter une relation `pendingClientMessage` (hasOne)
-   Ajouter une méthode `createPendingEntry()` pour créer une entrée dans pending_client_messages

## 4. Nouveaux jobs à créer

### 4.1 Job ProcessPendingMessages

-   Créer un job pour traiter les messages clients en attente
-   Logique:
    1. Compter les modérateurs en ligne
    2. Compter les messages clients en attente
    3. Vérifier si les conditions de notification sont remplies (aucun modérateur en ligne OU clients en attente > 2x modérateurs connectés)
    4. Récupérer le dernier round pour déterminer le numéro du prochain round
    5. Sélectionner les 3 modérateurs hors ligne avec le moins de conversations actives
    6. Créer un nouveau round de notification
    7. Envoyer les notifications aux modérateurs sélectionnés
    8. Marquer les messages comme notifiés
    9. Planifier la vérification du round dans 30 minutes

### 4.2 Job CheckNotificationRound

-   Créer un job pour vérifier si suffisamment de modérateurs ont répondu
-   Logique:
    1. Récupérer le round par son ID
    2. Vérifier combien de modérateurs du round se sont connectés
    3. Si moins de 2 modérateurs ont répondu, lancer un nouveau round
    4. Vérifier s'il reste des messages non traités

## 5. Nouveau service à créer

### 5.1 ModeratorNotificationService

-   Créer un service pour centraliser la gestion des notifications
-   Fonctionnalités:
    -   `getModeratorsByWorkload()`: récupérer les 3 modérateurs hors ligne avec le moins de conversations actives
    -   `createNotificationRound()`: créer un nouveau round de notification
    -   `sendNotificationToModerator()`: envoyer une notification à un modérateur
    -   `shouldTriggerRound()`: vérifier si un round doit être déclenché

## 6. Nouvelle notification Laravel à créer

### 6.1 PendingMessageNotification

-   Créer une notification pour les messages en attente
-   Canal: email uniquement
-   Contenu: informations sur le nombre de messages en attente, lien direct vers l'interface

## 7. Ajout d'un nouveau middleware

### 7.1 TrackModeratorActivity

-   Créer un middleware pour suivre l'activité des modérateurs
-   Logique:
    1. Après chaque requête d'un modérateur authentifié
    2. Mettre à jour last_online_at et vérifier/mettre à jour is_online

## 8. Mise à jour des contrôleurs existants

### 8.1 ModeratorController

-   Mettre à jour l'index() pour enregistrer l'activité du modérateur
-   Ajouter une méthode pour le heartbeat

### 8.2 Ajout d'un listener pour NewClientMessage

-   Mettre à jour le listener existant pour créer une entrée dans pending_client_messages

## 9. Modifications au niveau du frontend

### 9.1 Mise à jour du moderatorStore.js

-   Ajouter une fonction sendHeartbeat() qui envoie un signal toutes les 2 minutes

### 9.2 Mise à jour du composant Moderator.vue

-   **Déjà implémenté**: L'indicateur de statut en ligne/hors ligne existe déjà dans l'interface
-   Connecter l'indicateur existant avec la nouvelle API heartbeat et les nouveaux champs de base de données
-   Assurer que l'état visuel reflète correctement les champs `is_online` et `last_online_at`

### 9.3 Ajout d'un gestionnaire pour les événements de fermeture de navigateur

-   Écouter l'événement beforeunload pour mettre à jour le statut en ligne

## 10. Configuration dans bootstrap/app.php

### 10.1 Enregistrement des jobs

```php
$schedule->job(new ProcessPendingMessages())->everyTenMinutes();
```

### 10.2 Enregistrement du middleware TrackModeratorActivity

-   Ajouter le middleware à la liste des middlewares web

## 11. Mise à jour des API routes dans routes/web.php

### 11.1 Ajout de routes pour le heartbeat

-   POST /moderateur/heartbeat (pour mettre à jour l'activité)

## 12. Création de nouvelles vues

### 12.1 Création d'un template d'email pour les notifications

-   Créer un template pour les emails de notification pending_message.blade.php

## 13. Tests unitaires à créer

-   Tests pour le service ModeratorNotificationService
-   Tests pour les jobs
-   Tests pour les notifications

## Ordre d'implémentation suggéré

1. Créer les migrations et les exécuter
2. Créer les modèles et mettre à jour les modèles existants
3. Créer le service ModeratorNotificationService
4. Créer les notifications
5. Créer les jobs et les configurer dans le schedule
6. Mettre à jour les contrôleurs et ajouter le nouveau endpoint API
7. Modifier le frontend pour intégrer le heartbeat et connecter l'indicateur de statut existant aux nouvelles données
8. Créer les templates d'emails
9. Tester l'ensemble du système

Cette implémentation simplifiée se concentre sur l'essentiel : détecter les messages en attente, sélectionner les bons modérateurs et leur envoyer des notifications par email avec un système de rotation efficace.
