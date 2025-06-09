# Documentation : Correction de l'Authentification WebSocket pour Multi-Types d'Utilisateurs

## Contexte du Problème

Notre application utilise Laravel WebSockets (Reverb) pour la communication en temps réel entre les utilisateurs. Nous avons deux types d'utilisateurs principaux :

-   **Clients** : Utilisateurs qui envoient des messages aux profils
-   **Modérateurs** : Utilisateurs qui gèrent les profils et répondent aux messages des clients

### Problème Initial

-   L'authentification WebSocket fonctionnait pour les clients mais échouait pour les modérateurs
-   Les modérateurs ne pouvaient pas recevoir de messages en temps réel
-   L'erreur principale était "❌ Utilisateur non authentifié"

## Solution Détaillée

### 1. Identification du Problème

#### Symptômes :

```javascript
// Pour les clients (fonctionnait) :
Données utilisateur récupérées: {id: 2, type: 'client'}

// Pour les modérateurs (échouait) :
Données utilisateur récupérées: null
⚠️ Aucune donnée utilisateur trouvée
```

#### Cause Racine :

-   La meta tag utilisée était spécifique aux clients (`client-id`)
-   Pas de distinction du type d'utilisateur dans les meta tags
-   La fonction `getUserData()` supposait que tous les utilisateurs étaient des clients

### 2. Modifications Apportées

#### A. Modification du Template Blade (`app.blade.php`)

**Avant :**

```html
@auth
<meta name="client-id" content="{{ auth()->id() }}" />
<script>
    window.Laravel = {
        user: {
            id: {{ auth()->id() }},
            type: "{{ auth()->user()->type }}"
        }
    };
</script>
@endauth
```

**Après :**

```html
@auth
<meta name="user-id" content="{{ auth()->id() }}" />
<meta name="user-type" content="{{ auth()->user()->type }}" />
<script>
    window.Laravel = {
        user: {
            id: {{ auth()->id() }},
            type: "{{ auth()->user()->type }}"
        }
    };
</script>
@endauth
```

**Explications :**

-   Remplacement de `client-id` par `user-id` pour être plus générique
-   Ajout de `user-type` pour identifier le type d'utilisateur
-   Conservation de `window.Laravel` comme source de données primaire

#### B. Modification de Bootstrap.js

**Avant :**

```javascript
function getUserData() {
    if (window.Laravel && window.Laravel.user) {
        return window.Laravel.user;
    }

    const clientId = document
        .querySelector('meta[name="client-id"]')
        ?.getAttribute("content");
    if (clientId) {
        return { id: parseInt(clientId), type: "client" };
    }

    return null;
}
```

**Après :**

```javascript
function getUserData() {
    if (window.Laravel && window.Laravel.user) {
        return window.Laravel.user;
    }

    const userId = document
        .querySelector('meta[name="user-id"]')
        ?.getAttribute("content");
    const userType = document
        .querySelector('meta[name="user-type"]')
        ?.getAttribute("content");

    if (userId && userType) {
        return {
            id: parseInt(userId),
            type: userType,
        };
    }

    return null;
}
```

**Explications :**

-   Lecture des deux meta tags : `user-id` et `user-type`
-   Support de tous les types d'utilisateurs
-   Maintien de la compatibilité avec `window.Laravel`

### 3. Fonctionnement des Canaux WebSocket

#### Pour les Clients :

```javascript
// Canal privé pour le client
private-client.${userId}
// Exemple : private-client.2
```

#### Pour les Modérateurs :

```javascript
// Canal pour les notifications du modérateur
private-moderator.${userId}
// Exemple : private-moderator.4

// Canal pour les messages des profils gérés
private-profile.${profileId}
// Exemple : private-profile.2
```

### 4. Processus d'Authentification

1. L'utilisateur se connecte
2. Les meta tags et `window.Laravel` sont injectés dans la page
3. `bootstrap.js` initialise Laravel Echo
4. `getUserData()` récupère les informations de l'utilisateur
5. Lors de la souscription à un canal privé :
    - Une requête est envoyée à `/broadcasting/auth`
    - Le middleware vérifie l'authentification
    - Le canal est autorisé ou refusé

### 5. Points Importants à Retenir

1. **Double Source de Vérité :**

    - `window.Laravel` (source primaire)
    - Meta tags (source secondaire)

2. **Sécurité :**

    - Les données sont injectées côté serveur
    - L'authentification est vérifiée à chaque requête WebSocket
    - Les canaux sont privés et sécurisés

3. **Flexibilité :**

    - Le système supporte maintenant tous les types d'utilisateurs
    - Facilement extensible pour de nouveaux types d'utilisateurs

4. **Maintenance :**
    - Les modifications sont non-intrusives
    - La rétrocompatibilité est maintenue
    - Le code est plus générique et réutilisable

## Conclusion

Cette correction permet une gestion unifiée de l'authentification WebSocket pour tous les types d'utilisateurs, tout en maintenant la sécurité et la flexibilité du système. Les modérateurs peuvent maintenant recevoir les messages en temps réel comme les clients, chacun sur leurs canaux respectifs.

## Conseils pour l'Implémentation Future

1. Toujours utiliser des noms génériques pour les identifiants
2. Prévoir la gestion multi-types dès le début
3. Documenter les canaux WebSocket et leur utilisation
4. Tester l'authentification avec différents types d'utilisateurs
5. Maintenir une séparation claire entre les canaux de différents types d'utilisateurs
