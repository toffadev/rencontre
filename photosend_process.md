# Documentation du Système d'Envoi de Photos de Profil

## Introduction

Ce document explique le fonctionnement du système d'envoi et d'affichage des photos de profil dans notre application de rencontres. Ce système permet aux modérateurs de sélectionner et d'envoyer des photos associées à un profil virtuel spécifique lors d'une conversation avec un client.

## Vue d'ensemble du système

Le système comprend les composants suivants :

1. **Backend (Laravel)**

    - Contrôleurs pour gérer les requêtes d'envoi et de récupération des photos
    - Modèles pour les photos de profil et les pièces jointes des messages
    - Services pour la gestion des fichiers

2. **Frontend (Vue.js)**
    - Composant de sélection de photos
    - Intégration dans l'interface de chat des modérateurs
    - Affichage des photos dans les conversations

## Structure des données

### Modèles principaux

1. **ProfilePhoto**

    - Stocke les informations sur les photos associées à un profil virtuel
    - Champs : `id`, `profile_id`, `path`, `order`

2. **Message**

    - Représente un message échangé entre un client et un profil virtuel
    - Champs : `id`, `client_id`, `profile_id`, `moderator_id`, `content`, `is_from_client`, `read_at`

3. **MessageAttachment**
    - Stocke les informations sur les pièces jointes des messages (y compris les photos)
    - Champs : `id`, `message_id`, `file_path`, `file_name`, `mime_type`, `file_size`

## Processus d'envoi de photos

### 1. Récupération des photos disponibles

Lorsqu'un modérateur souhaite envoyer une photo de profil à un client, le système doit d'abord récupérer toutes les photos disponibles pour ce profil et identifier celles qui ont déjà été envoyées au client.

#### Contrôleur : `ProfilePhotoController@getProfilePhotos`

```php
public function getProfilePhotos(Request $request)
{
    // Valider la requête
    $request->validate([
        'profile_id' => 'required|exists:profiles,id',
        'client_id' => 'required|exists:users,id',
    ]);

    $profileId = $request->profile_id;
    $clientId = $request->client_id;

    // Vérifier que le modérateur a accès à ce profil
    $hasAccess = DB::table('moderator_profile_assignments')
        ->where('user_id', Auth::id())
        ->where('profile_id', $profileId)
        ->where('is_active', true)
        ->exists();

    if (!$hasAccess) {
        return response()->json([
            'success' => false,
            'message' => 'Vous n\'avez pas accès à ce profil'
        ], 403);
    }

    // Récupérer toutes les photos du profil
    $profilePhotos = ProfilePhoto::where('profile_id', $profileId)
        ->orderBy('order')
        ->get();

    // Récupérer les IDs des photos déjà envoyées à ce client
    $sentPhotoIds = $this->getSentPhotoIds($profileId, $clientId);

    // Formater les données pour le frontend
    $photos = $profilePhotos->map(function ($photo) use ($sentPhotoIds) {
        // Normaliser le chemin
        $normalizedPath = $this->normalizeFilePath($photo->path);

        return [
            'id' => $photo->id,
            'path' => $normalizedPath,
            'url' => asset($normalizedPath),
            'order' => $photo->order,
            'already_sent' => in_array($photo->id, $sentPhotoIds),
        ];
    });

    return response()->json([
        'success' => true,
        'photos' => $photos
    ]);
}
```

#### Fonction utilitaire : `normalizeFilePath`

Cette fonction est cruciale pour résoudre les problèmes de chemins d'accès aux fichiers, notamment les doubles slashes qui peuvent causer des erreurs 403.

```php
private function normalizeFilePath($path)
{
    // Si le chemin commence par /storage/, le convertir en storage/
    if (strpos($path, '/storage/') === 0) {
        return 'storage' . substr($path, 8);
    }

    // Si le chemin commence par storage/ (sans slash), le laisser tel quel
    if (strpos($path, 'storage/') === 0) {
        return $path;
    }

    // Sinon, ajouter storage/ au début si nécessaire
    if (!str_starts_with($path, 'storage/') && !str_starts_with($path, '/storage/')) {
        return 'storage/' . $path;
    }

    return $path;
}
```

#### Fonction : `getSentPhotoIds`

Cette fonction identifie les photos qui ont déjà été envoyées à un client spécifique pour éviter les doublons.

```php
private function getSentPhotoIds($profileId, $clientId)
{
    // Récupérer tous les messages avec pièces jointes envoyés par ce profil à ce client
    $sentPhotoUrls = Message::where('profile_id', $profileId)
        ->where('client_id', $clientId)
        ->where('is_from_client', false)
        ->whereHas('attachments')
        ->with('attachments')
        ->get()
        ->pluck('attachments')
        ->flatten()
        ->pluck('file_path')
        ->toArray();

    // Normaliser les chemins des photos envoyées
    $normalizedSentUrls = array_map(function($url) {
        return $this->normalizeFilePath($url);
    }, $sentPhotoUrls);

    // Récupérer toutes les photos du profil
    $profilePhotos = ProfilePhoto::where('profile_id', $profileId)->get();

    // Filtrer manuellement pour gérer les différentes formes de chemins
    $sentPhotoIds = [];
    foreach ($profilePhotos as $photo) {
        $normalizedPhotoPath = $this->normalizeFilePath($photo->path);

        // Vérifier si cette photo a été envoyée
        foreach ($normalizedSentUrls as $sentUrl) {
            if ($sentUrl === $normalizedPhotoPath || basename($sentUrl) === basename($normalizedPhotoPath)) {
                $sentPhotoIds[] = $photo->id;
                break;
            }
        }
    }

    return $sentPhotoIds;
}
```

### 2. Envoi d'une photo de profil

Lorsque le modérateur sélectionne une photo à envoyer, le système crée un nouveau message avec cette photo en pièce jointe.

#### Contrôleur : `ProfilePhotoController@sendProfilePhoto`

```php
public function sendProfilePhoto(Request $request)
{
    $request->validate([
        'profile_id' => 'required|exists:profiles,id',
        'client_id' => 'required|exists:users,id',
        'photo_id' => 'required|exists:profile_photos,id',
    ]);

    $profileId = $request->profile_id;
    $clientId = $request->client_id;
    $photoId = $request->photo_id;
    $moderatorId = Auth::id();

    // Vérifier que le modérateur a accès à ce profil
    $hasAccess = DB::table('moderator_profile_assignments')
        ->where('user_id', $moderatorId)
        ->where('profile_id', $profileId)
        ->where('is_active', true)
        ->exists();

    if (!$hasAccess) {
        return response()->json([
            'success' => false,
            'message' => 'Vous n\'avez pas accès à ce profil'
        ], 403);
    }

    // Vérifier que la photo appartient bien au profil
    $photo = ProfilePhoto::where('id', $photoId)
        ->where('profile_id', $profileId)
        ->first();

    if (!$photo) {
        return response()->json([
            'success' => false,
            'message' => 'Cette photo n\'appartient pas à ce profil'
        ], 404);
    }

    // Vérifier que la photo n'a pas déjà été envoyée à ce client
    $sentPhotoIds = $this->getSentPhotoIds($profileId, $clientId);
    if (in_array($photoId, $sentPhotoIds)) {
        return response()->json([
            'success' => false,
            'message' => 'Cette photo a déjà été envoyée à ce client'
        ], 400);
    }

    try {
        // Créer un nouveau message
        $message = Message::create([
            'client_id' => $clientId,
            'profile_id' => $profileId,
            'moderator_id' => $moderatorId,
            'content' => '',
            'is_from_client' => false,
        ]);

        // Normaliser le chemin
        $normalizedPath = $this->normalizeFilePath($photo->path);

        // Créer une pièce jointe pour ce message
        $attachment = MessageAttachment::create([
            'message_id' => $message->id,
            'file_path' => $normalizedPath,
            'file_name' => basename($normalizedPath),
            'mime_type' => 'image/jpeg', // Par défaut, ajustez selon vos besoins
            'file_size' => 0, // Taille inconnue, mais ce n'est pas critique
        ]);

        // Préparer les données pour la réponse
        $messageData = [
            'id' => $message->id,
            'content' => '',
            'isFromClient' => false,
            'time' => $message->created_at->format('H:i'),
            'date' => $message->created_at->format('Y-m-d'),
            'created_at' => $message->created_at->toISOString(),
            'attachment' => [
                'id' => $attachment->id,
                'file_name' => $attachment->file_name,
                'mime_type' => $attachment->mime_type,
                'url' => asset($normalizedPath),
            ],
        ];

        // Émettre l'événement en temps réel
        broadcast(new \App\Events\MessageSent($message))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Photo envoyée avec succès',
            'messageData' => $messageData
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de l\'envoi de la photo',
            'error' => $e->getMessage()
        ], 500);
    }
}
```

## Composant Frontend

### Composant de sélection de photos : `ProfilePhotoSelector.vue`

Ce composant affiche un modal contenant toutes les photos disponibles pour un profil spécifique, en indiquant celles qui ont déjà été envoyées au client actuel.

```vue
<template>
    <div>
        <!-- Bouton pour ouvrir le modal -->
        <button
            class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition"
            title="Galerie de photos du profil"
            @click="openModal"
        >
            <i class="fas fa-images"></i>
        </button>

        <!-- Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen">
                <!-- Overlay de fond -->
                <div
                    class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                    @click="closeModal"
                ></div>

                <!-- Contenu du modal -->
                <div
                    class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full"
                >
                    <!-- En-tête du modal -->
                    <div class="bg-gray-100 px-4 py-3 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">
                            Photos du profil
                        </h3>
                    </div>

                    <!-- Corps du modal -->
                    <div class="px-4 py-3">
                        <!-- État de chargement -->
                        <div
                            v-if="loading"
                            class="flex justify-center items-center py-8"
                        >
                            <div
                                class="animate-spin rounded-full h-12 w-12 border-b-2 border-pink-500"
                            ></div>
                        </div>

                        <!-- Message d'erreur -->
                        <div
                            v-else-if="error"
                            class="text-red-500 text-center py-4"
                        >
                            {{ error }}
                        </div>

                        <!-- Grille de photos -->
                        <div
                            v-else-if="photos.length"
                            class="grid grid-cols-3 gap-4"
                        >
                            <div
                                v-for="photo in photos"
                                :key="photo.id"
                                class="relative cursor-pointer"
                                @click="selectPhoto(photo)"
                            >
                                <img
                                    :src="photo.url"
                                    :alt="'Photo ' + photo.id"
                                    class="w-full h-32 object-cover rounded-lg"
                                    :class="{
                                        'opacity-50': photo.already_sent,
                                    }"
                                />
                                <div
                                    v-if="photo.already_sent"
                                    class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-30 rounded-lg"
                                >
                                    <span
                                        class="text-white text-xs font-medium px-2 py-1 bg-red-500 rounded"
                                    >
                                        Déjà envoyée
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Aucune photo disponible -->
                        <div v-else class="text-center py-8 text-gray-500">
                            Aucune photo disponible pour ce profil.
                        </div>
                    </div>

                    <!-- Pied du modal -->
                    <div
                        class="bg-gray-100 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse"
                    >
                        <button
                            type="button"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pink-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            @click="closeModal"
                        >
                            Fermer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from "vue";
import axios from "axios";

const props = defineProps({
    profileId: {
        type: [Number, String],
        required: true,
    },
    clientId: {
        type: [Number, String],
        required: true,
    },
});

const emit = defineEmits(["photo-selected"]);

const showModal = ref(false);
const photos = ref([]);
const loading = ref(false);
const error = ref(null);

// Fonction pour ouvrir le modal et charger les photos
function openModal() {
    showModal.value = true;
    loadPhotos();
}

// Fonction pour fermer le modal
function closeModal() {
    showModal.value = false;
}

// Fonction pour charger les photos du profil
async function loadPhotos() {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get("/moderateur/profile-photos", {
            params: {
                profile_id: props.profileId,
                client_id: props.clientId,
            },
        });

        if (response.data.success) {
            photos.value = response.data.photos;
        } else {
            error.value =
                response.data.message || "Erreur lors du chargement des photos";
        }
    } catch (err) {
        error.value =
            err.response?.data?.message ||
            "Erreur lors du chargement des photos";
    } finally {
        loading.value = false;
    }
}

// Fonction pour sélectionner une photo
function selectPhoto(photo) {
    // Ne pas permettre la sélection de photos déjà envoyées
    if (photo.already_sent) {
        return;
    }

    // Émettre l'événement avec la photo sélectionnée
    emit("photo-selected", photo);

    // Fermer le modal
    closeModal();
}

// Recharger les photos si les props changent
watch([() => props.profileId, () => props.clientId], () => {
    if (showModal.value) {
        loadPhotos();
    }
});
</script>
```

### Intégration dans la page Moderator.vue

Le composant `ProfilePhotoSelector` est intégré dans l'interface de chat des modérateurs pour remplacer le bouton standard d'upload d'image.

```vue
<div class="flex items-center space-x-2">
    <input
        type="file"
        ref="fileInput"
        class="hidden"
        accept="image/*"
        @change="handleFileUpload"
    />
    <!-- Bouton d'upload d'image personnelle désactivé au profit du sélecteur de photos de profil -->
    <!-- <button
        class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition"
        title="Ajouter une image"
        @click="$refs.fileInput.click()"
    >
        <i class="fas fa-image"></i>
    </button> -->
    
    <!-- Sélecteur de photos de profil -->
    <ProfilePhotoSelector 
        v-if="currentAssignedProfile && selectedClient"
        :profile-id="currentAssignedProfile.id"
        :client-id="selectedClient.id"
        @photo-selected="handleProfilePhotoSelected"
    />
</div>
```

### Fonction de gestion de la sélection de photo

Cette fonction est appelée lorsqu'une photo est sélectionnée dans le composant `ProfilePhotoSelector`.

```javascript
// Fonction pour gérer la sélection d'une photo de profil
async function handleProfilePhotoSelected(photo) {
    try {
        // Vérifier que les données nécessaires sont disponibles
        if (!currentAssignedProfile.value || !selectedClient.value) {
            console.error("Profil ou client non sélectionné");
            return;
        }

        // Afficher un indicateur de chargement
        const loadingMessage = {
            id: "temp-" + Date.now(),
            content: "",
            time: new Date().toLocaleTimeString([], {
                hour: "2-digit",
                minute: "2-digit",
            }),
            isFromClient: false,
            date: new Date().toISOString().split("T")[0],
            attachment: {
                url: photo.url,
                file_name: "Envoi en cours...",
                mime_type: "image/jpeg",
            },
            pending: true,
        };

        // Ajouter le message temporaire à la conversation
        if (!chatMessages.value[selectedClient.value.id]) {
            chatMessages.value[selectedClient.value.id] = [];
        }
        chatMessages.value[selectedClient.value.id].push(loadingMessage);

        // Faire défiler vers le bas
        nextTick(() => {
            scrollToBottom();
        });

        // Envoyer la photo au serveur
        const response = await axios.post("/moderateur/send-profile-photo", {
            profile_id: currentAssignedProfile.value.id,
            client_id: selectedClient.value.id,
            photo_id: photo.id,
            content: "", // Message optionnel
        });

        // Remplacer le message temporaire par le message réel
        if (response.data.success) {
            const index = chatMessages.value[selectedClient.value.id].findIndex(
                (msg) => msg.id === loadingMessage.id
            );

            if (index !== -1) {
                chatMessages.value[selectedClient.value.id][index] =
                    response.data.messageData;
            }
        } else {
            // En cas d'erreur, marquer le message comme échoué
            const index = chatMessages.value[selectedClient.value.id].findIndex(
                (msg) => msg.id === loadingMessage.id
            );

            if (index !== -1) {
                chatMessages.value[selectedClient.value.id][
                    index
                ].pending = false;
                chatMessages.value[selectedClient.value.id][
                    index
                ].failed = true;
            }
        }
    } catch (error) {
        console.error("Erreur lors de l'envoi de la photo:", error);
    }
}
```

## Affichage des images dans les conversations

### Dans la page modérateur

```vue
<!-- Image attachée -->
<div
    v-if="
        message.attachment && message.attachment.mime_type.startsWith('image/')
    "
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

### Dans la page client

```vue
<!-- Image attachée -->
<div
    v-if="
        message.attachment && message.attachment.mime_type.startsWith('image/')
    "
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

## Gestion des chemins d'images

### Problème des doubles slashes

Un problème courant lors de l'affichage des images est la présence de doubles slashes dans les URLs, ce qui peut causer des erreurs 403 (Forbidden). Par exemple :

```
http://127.0.0.1:8000/storage//storage/profiles/pQtdlkCQpWpIGynXOdN69WSlnLLVQV2fRuAo89GS.jpg
```

Ce problème se produit lorsque :

1. Les chemins sont stockés en base de données avec déjà un `/storage/` au début
2. La fonction `Storage::url()` ajoute un autre `/storage/`

### Solution : Normalisation des chemins

La fonction `normalizeFilePath()` a été ajoutée dans plusieurs contrôleurs pour résoudre ce problème :

```php
private function normalizeFilePath($path)
{
    // Si le chemin commence par /storage/, le convertir en storage/
    if (strpos($path, '/storage/') === 0) {
        return 'storage' . substr($path, 8);
    }

    // Si le chemin commence par storage/ (sans slash), le laisser tel quel
    if (strpos($path, 'storage/') === 0) {
        return $path;
    }

    // Sinon, ajouter storage/ au début si nécessaire
    if (!str_starts_with($path, 'storage/') && !str_starts_with($path, '/storage/')) {
        return 'storage/' . $path;
    }

    return $path;
}
```

Cette fonction normalise les chemins de fichiers pour éviter les doubles slashes et assurer que les URLs générées sont correctes.

### Utilisation de `asset()` au lieu de `Storage::url()`

Nous avons remplacé `Storage::url()` par `asset()` pour générer les URLs des images :

```php
// Avant
'url' => Storage::url($attachment->file_path)

// Après
'url' => asset($normalizedPath)
```

La fonction `asset()` génère une URL absolue basée sur le chemin fourni, sans ajouter automatiquement `/storage/` comme le fait `Storage::url()`.

## Résumé du processus complet

1. **Initialisation**

    - Le modérateur est assigné à un profil virtuel et discute avec un client
    - Le bouton d'upload d'image standard est remplacé par le sélecteur de photos de profil

2. **Sélection d'une photo**

    - Le modérateur clique sur le bouton de sélection de photos
    - Le modal s'ouvre et charge toutes les photos du profil actuel
    - Les photos déjà envoyées au client sont marquées comme telles et ne peuvent pas être sélectionnées à nouveau

3. **Envoi de la photo**

    - Le modérateur sélectionne une photo
    - Un message temporaire avec la photo est ajouté à la conversation
    - La photo est envoyée au serveur via une requête API
    - Le message temporaire est remplacé par le message réel une fois la photo envoyée

4. **Affichage de la photo**

    - La photo est affichée dans la conversation côté modérateur
    - La photo est également affichée dans la conversation côté client
    - Les chemins des images sont normalisés pour éviter les problèmes d'affichage

5. **Gestion des erreurs**
    - Si l'envoi échoue, le message est marqué comme échoué
    - Si la photo a déjà été envoyée, elle ne peut pas être sélectionnée à nouveau

## Conclusion

Ce système permet aux modérateurs d'envoyer facilement des photos associées aux profils virtuels qu'ils gèrent, tout en évitant d'envoyer deux fois la même photo à un client. La normalisation des chemins de fichiers résout les problèmes courants d'affichage des images, assurant ainsi une expérience utilisateur fluide tant pour les modérateurs que pour les clients.
