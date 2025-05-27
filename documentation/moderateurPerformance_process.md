# Documentation du Système de Performance des Modérateurs

## 1. Vue d'ensemble du système

Le système de performance des modérateurs est une fonctionnalité qui permet de suivre et d'analyser les performances des modérateurs dans l'application. Il comprend :

-   Un tableau de bord visuel avec des statistiques
-   Des filtres pour analyser les données
-   Des graphiques pour visualiser les tendances
-   Un tableau détaillé des performances individuelles

## 2. Structure du Code

### 2.1 Composants Frontend (Vue.js)

#### ModeratorPerformance.vue

```vue
// Principal composant du tableau de bord
<template>
    <AdminLayout>
        <!-- En-tête avec titre et boutons -->
        <!-- Barre de filtres -->
        <!-- Cartes de statistiques -->
        <!-- Graphiques -->
        <!-- Tableau des modérateurs -->
    </AdminLayout>
</template>
```

**Éléments clés :**

-   StatCard : Affiche les métriques principales (messages, temps de réponse, points, gains)
-   Graphiques : Utilise Chart.js pour visualiser les données
-   Tableau paginé : Liste détaillée des modérateurs et leurs performances

### 2.2 Backend (Laravel)

#### ModeratorStatistic Model

```php
class ModeratorStatistic extends Model
{
    protected $fillable = [
        'user_id',
        'profile_id',
        'stats_date',
        'short_messages_count',
        'long_messages_count',
        'points_received',
        'earnings',
        'average_response_time'
    ];
}
```

#### ModeratorPerformanceController

Gère la logique de récupération et de traitement des données de performance.

## 3. Fonctionnalités Détaillées

### 3.1 Système de Filtrage

-   Par période (semaine, mois, personnalisé)
-   Par modérateur
-   Par profil
-   Par niveau de performance

### 3.2 Calcul des Performances

Le système calcule plusieurs métriques :

1. **Messages**
    - Nombre total de messages
    - Répartition courts/longs messages
2. **Temps de Réponse**

    - Moyenne par modérateur
    - Tendance sur la période

3. **Points et Gains**
    - Points accumulés
    - Conversion en gains

### 3.3 Niveaux de Performance

Les modérateurs sont classés en 4 niveaux :

-   Excellent
-   Bon
-   Moyen
-   Faible

## 4. Flux de Données

1. **Frontend vers Backend**

    ```javascript
    const loadData = async () => {
        const params = {
            period: "week",
            dateRange: null,
            moderator: null,
            profile: null,
            performanceLevel: null,
        };
        // Appel API avec axios
    };
    ```

2. **Traitement Backend**
    ```php
    public function getData(Request $request)
    {
      // 1. Validation des paramètres
      // 2. Récupération des données
      // 3. Calculs et agrégations
      // 4. Formatage de la réponse
    }
    ```

## 5. Points d'Attention et Optimisations

### 5.1 Performance

-   Utilisation d'index sur les colonnes clés
-   Agrégation des données côté SQL
-   Mise en cache possible des résultats

### 5.2 Maintenance

-   Logs détaillés pour le débogage
-   Gestion des erreurs structurée
-   Documentation des API

## 6. Problèmes Actuels et Solutions Futures

### Problème Principal

Actuellement, nous avons une erreur 500 lors de l'appel à `/admin/moderator-performance/data`. Cette erreur peut être due à :

1. **Structure de la Base de Données**

    - Table `moderator_statistics` potentiellement manquante
    - Colonnes requises non présentes

2. **Gestion des Données**
    - Problème avec les valeurs NULL
    - Erreurs dans les calculs d'agrégation

### Solutions Proposées

1. **Court Terme**

    - Vérifier la structure de la base de données
    - Ajouter des logs détaillés
    - Gérer les cas où les données sont absentes

2. **Long Terme**
    - Mettre en place un système de cache
    - Optimiser les requêtes SQL
    - Ajouter des tests automatisés

## 7. Prochaines Étapes

Pour résoudre les problèmes actuels :

1. Vérifier la présence et la structure de la table `moderator_statistics`
2. Examiner les logs Laravel dans `storage/logs/laravel.log`
3. Tester chaque partie du code séparément
4. Mettre en place un système de données par défaut

## 8. Conseils pour le Développement

1. **Toujours commencer par la structure**

    - Créer les migrations
    - Définir les modèles
    - Établir les relations

2. **Tester progressivement**

    - Vérifier les données brutes
    - Tester les calculs
    - Valider l'affichage

3. **Documenter le code**
    - Commentaires clairs
    - Types PHP stricts
    - Documentation API
