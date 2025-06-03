# Documentation : Implémentation du système d'envoi de fichiers dans une application de chat Laravel + Vue.js

## Table des matières

1. [Vue d'ensemble](#vue-densemble)
2. [Base de données](#base-de-données)
3. [Backend (Laravel)](#backend-laravel)
4. [Frontend (Vue.js)](#frontend-vuejs)
5. [Gestion des fichiers](#gestion-des-fichiers)
6. [Sécurité et validation](#sécurité-et-validation)
7. [Configuration Laravel 11](#configuration-laravel-11)

## Vue d'ensemble

Cette documentation explique l'implémentation d'un système d'envoi de fichiers images dans une application de chat en temps réel. Le système permet aux clients et aux modérateurs d'envoyer des images dans leurs conversations.

### Technologies utilisées

-   Backend : Laravel 11
-   Frontend : Vue.js 3
-   Base de données : MySQL
-   Temps réel : Laravel Echo + Reverb
-   Stockage : Laravel Storage

## Base de données

### 1. Table des pièces jointes

Nous avons créé une table `message_attachments` pour stocker les informations des fichiers :

```sql
Schema::create('message_attachments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('message_id')->constrained()->onDelete('cascade');
    $table->string('file_path');    // Chemin du fichier dans le stockage
    $table->string('file_name');    // Nom original du fichier
    $table->string('mime_type');    // Type MIME du fichier
    $table->integer('file_size');   // Taille du fichier en octets
    $table->timestamps();
});
```

### 2. Relations

-   Un message peut avoir une pièce jointe
-   La pièce jointe est liée au message via `message_id`

## Backend (Laravel)

### 1. Modèle MessageAttachment

```php
class MessageAttachment extends Model
{
    protected $fillable = [
        'message_id',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
    ];

    public function message()
    {
        return $this->belongsTo(Message::class);
    }
}
```

### 2. Mise à jour du MessageController

Points clés ajoutés dans le contrôleur :

-   Validation des fichiers
-   Stockage des fichiers
-   Création de l'enregistrement dans la base de données

```php
public function sendMessage(Request $request)
{
    $request->validate([
        'content' => 'required_without:attachment|string|max:1000',
        'attachment' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
    ]);

    // Gestion du fichier
    if ($request->hasFile('attachment')) {
        $file = $request->file('attachment');
        $path = $file->store('message-attachments', 'public');

        // Créer l'attachement
        $attachment = MessageAttachment::create([
            'message_id' => $message->id,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);
    }
}
```

## Frontend (Vue.js)

### 1. Configuration Axios

Configuration globale d'Axios pour la gestion du CSRF et des requêtes :

```javascript
// Configuration d'Axios pour inclure le CSRF token
const csrfToken = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute("content");
axios.defaults.headers.common["X-CSRF-TOKEN"] = csrfToken;
axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";
axios.defaults.withCredentials = true;
```

### 2. Interface utilisateur

Ajout des éléments d'interface :

```html
<!-- Input de fichier caché -->
<input
    type="file"
    ref="fileInput"
    class="hidden"
    accept="image/*"
    @change="handleFileUpload"
/>

<!-- Bouton d'upload -->
<button class="p-2 rounded-full bg-gray-100" @click="$refs.fileInput.click()">
    <i class="fas fa-image"></i>
</button>

<!-- Prévisualisation de l'image -->
<div v-if="selectedFile" class="relative inline-block">
    <img :src="previewUrl" class="max-h-32 rounded-lg" alt="Preview" />
    <button
        @click="removeSelectedFile"
        class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full"
    >
        <i class="fas fa-times"></i>
    </button>
</div>
```

### 3. Gestion des fichiers côté client

Variables d'état :

```javascript
const fileInput = ref(null);
const selectedFile = ref(null);
const previewUrl = ref(null);
```

Fonctions de gestion :

```javascript
// Gestion de l'upload de fichier
function handleFileUpload(event) {
    const file = event.target.files[0];
    if (file) {
        // Validation
        if (!file.type.startsWith("image/")) {
            alert("Seules les images sont autorisées");
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            alert("La taille du fichier ne doit pas dépasser 5MB");
            return;
        }

        // Création de la prévisualisation
        selectedFile.value = file;
        previewUrl.value = URL.createObjectURL(file);
    }
}

// Suppression du fichier sélectionné
function removeSelectedFile() {
    selectedFile.value = null;
    previewUrl.value = null;
    fileInput.value.value = "";
}
```

### 4. Envoi du message avec fichier

```javascript
async function sendMessage() {
    const formData = new FormData();
    formData.append("client_id", selectedClient.value.id);
    formData.append("profile_id", currentAssignedProfile.value.id);

    if (newMessage.value.trim()) {
        formData.append("content", newMessage.value);
    }
    if (selectedFile.value) {
        formData.append("attachment", selectedFile.value);
    }

    try {
        const response = await axios.post("/moderateur/send-message", formData);

        if (response.data.success) {
            // Mise à jour du message local avec les données du serveur
            const index = chatMessages.value[selectedClient.value.id].findIndex(
                (msg) => msg.id === localMessage.id
            );
            if (index !== -1) {
                chatMessages.value[selectedClient.value.id][index] =
                    response.data.messageData;
            }
        }
    } catch (error) {
        console.error("Erreur lors de l'envoi du message:", error);
        console.error("Détails de l'erreur:", {
            status: error.response?.status,
            data: error.response?.data,
            message: error.message,
        });
    }
}
```

## Gestion des fichiers

### 1. Stockage

-   Les fichiers sont stockés dans le dossier `storage/app/public/message-attachments`
-   Les fichiers sont accessibles via un lien symbolique vers `public/storage`
-   Configuration dans `config/filesystems.php`

### 2. Affichage des images

```html
<div
    v-if="message.attachment && message.attachment.mime_type.startsWith('image/')"
    class="mt-2"
>
    <img
        :src="message.attachment.url"
        :alt="message.attachment.file_name"
        class="max-w-full rounded-lg cursor-pointer"
        @click="showImagePreview(message.attachment)"
    />
</div>
```

### 3. Prévisualisation plein écran

```html
<div
    v-if="showPreview"
    class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50"
    @click="closeImagePreview"
>
    <div class="max-w-4xl max-h-full p-4">
        <img
            :src="previewImage.url"
            :alt="previewImage.file_name"
            class="max-w-full max-h-[90vh] object-contain"
        />
    </div>
</div>
```

## Sécurité et validation

### 1. Validation côté serveur

-   Types de fichiers autorisés : jpeg, png, jpg, gif
-   Taille maximale : 5MB
-   Validation MIME type
-   Protection CSRF

### 2. Gestion du CSRF

-   Le token CSRF est automatiquement géré par le middleware `VerifyCsrfToken`
-   Le token est inclus dans les requêtes Axios via la configuration globale
-   Les requêtes sont authentifiées via le middleware `auth`

### 3. Bonnes pratiques

-   Utilisation de noms de fichiers uniques
-   Stockage sécurisé dans un dossier dédié
-   Nettoyage des fichiers temporaires
-   Gestion des erreurs

## Points importants à retenir

1. **Structure de données**

    - Table séparée pour les pièces jointes
    - Relations bien définies entre les modèles

2. **Gestion des fichiers**

    - Validation stricte des types et tailles
    - Stockage organisé et sécurisé
    - Prévisualisation pour une meilleure expérience utilisateur

3. **Interface utilisateur**

    - Interface intuitive pour l'upload
    - Prévisualisation des images
    - Retour visuel immédiat

4. **Sécurité**

    - Validation côté serveur et client
    - Protection contre les fichiers malveillants
    - Gestion sécurisée du stockage

5. **Performance**
    - Compression des images si nécessaire
    - Chargement optimisé des images
    - Gestion efficace de l'espace de stockage

Cette implémentation fournit une base solide pour un système d'envoi de fichiers sécurisé et convivial dans une application de chat.

## Configuration Laravel 11

### 1. Configuration des Middlewares

Dans Laravel 11, les middlewares sont configurés dans `bootstrap/app.php` :

```php
->withMiddleware(function (Middleware $middleware) {
    // Base Web Middleware
    $middleware->web([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        \App\Http\Middleware\HandleInertiaRequests::class,
    ]);

    // API Middleware
    $middleware->api(Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class);

    // Named Middleware
    $middleware->alias([
        'auth' => \App\Http\Middleware\Authenticate::class,
        'moderator' => \App\Http\Middleware\ModeratorMiddleware::class,
        // ... autres middlewares
    ]);
})
```

### 2. Configuration des Routes

Configuration des routes avec les middlewares appropriés :

```php
Route::middleware(['auth', 'moderator'])->prefix('moderateur')->name('moderator.')->group(function () {
    Route::post('/send-message', [ModeratorController::class, 'sendMessage'])
        ->name('send-message')
        ->middleware(['web']);
});
```
