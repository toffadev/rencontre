# Guide de Configuration de Laravel Echo et Laravel Reverb

Ce document détaille le processus complet d'installation et de configuration de Laravel Echo et Laravel Reverb pour la mise en place de WebSockets dans une application Laravel 11.

## Étape 1: Installation des dépendances

```bash
# Installation de Laravel Reverb (la solution WebSocket officielle de Laravel 11)
composer require laravel/reverb

# Installation des dépendances côté client
npm install laravel-echo pusher-js
```

## Étape 2: Configuration du fichier .env

Ajoutez les variables d'environnement suivantes à votre fichier `.env` :

```
BROADCAST_DRIVER=reverb
REVERB_APP_ID=712715
REVERB_APP_KEY=baf003e69f63c48c7f09c3c160b8b24c
REVERB_APP_SECRET=4b7354d547757cbac91e490e3ebf73c0046f45acce910c5abb9d37301f506160
REVERB_HOST=127.0.0.1
REVERB_PORT=8002
REVERB_SCHEME=http

# Variables exposées à Vite pour le frontend
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Notes importantes:

-   `BROADCAST_DRIVER=reverb` indique à Laravel d'utiliser Reverb pour la diffusion des événements
-   Les clés et identifiants Reverb sont générés par Laravel Reverb
-   Les variables VITE\_\* sont nécessaires pour que le frontend puisse accéder à ces valeurs

## Étape 3: Configuration de Vite

Mettez à jour votre fichier `vite.config.js` pour exposer les variables d'environnement Reverb au frontend :

```javascript
export default defineConfig({
    // ... autres configurations
    define: {
        "process.env": {
            VITE_REVERB_APP_KEY: process.env.VITE_REVERB_APP_KEY,
            VITE_REVERB_HOST: process.env.VITE_REVERB_HOST,
            VITE_REVERB_PORT: process.env.VITE_REVERB_PORT,
            VITE_REVERB_SCHEME: process.env.VITE_REVERB_SCHEME,
        },
    },
});
```

## Étape 4: Initialisation de Laravel Echo

Créez ou mettez à jour le fichier `resources/js/bootstrap.js` pour initialiser Laravel Echo :

```javascript
import axios from "axios";
import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;

const initEcho = () => {
    try {
        window.Echo = new Echo({
            broadcaster: "reverb",
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST || "127.0.0.1",
            wsPort: parseInt(import.meta.env.VITE_REVERB_PORT || "8002"),
            forceTLS: false,
            encrypted: false,
            enabledTransports: ["ws"],
            disableStats: true,
            namespace: "",
        });

        console.log("Echo initialized with config:", {
            key: import.meta.env.VITE_REVERB_APP_KEY,
            host: import.meta.env.VITE_REVERB_HOST,
            port: import.meta.env.VITE_REVERB_PORT,
            scheme: import.meta.env.VITE_REVERB_SCHEME,
        });
    } catch (error) {
        console.error("Failed to initialize Echo:", error);
    }
};

// Initialiser Echo après que le DOM est chargé
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initEcho);
} else {
    initEcho();
}

window.axios = axios;
window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";
```

Notes importantes:

-   `broadcaster: 'reverb'` indique à Echo d'utiliser le broadcaster Reverb
-   Les paramètres de connexion sont importés depuis les variables d'environnement
-   Nous ajoutons une vérification pour s'assurer que Echo est initialisé après le chargement du DOM

## Étape 5: Activation de BroadcastServiceProvider

Assurez-vous que le `BroadcastServiceProvider` est décommenté dans le fichier `config/app.php` :

```php
App\Providers\BroadcastServiceProvider::class,
```

## Étape 6: Configuration des canaux

Configurez vos canaux de diffusion dans le fichier `routes/channels.php` :

```php
<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('test-channel', function () {
    return true;
});
```

Note importante:

-   Pour le canal `test-channel`, nous autorisons toutes les connexions en retournant `true`
-   Pour des raisons de sécurité dans un environnement de production, vous devriez implémenter une logique d'autorisation appropriée

## Étape 7: Création d'un événement de test

Créez un événement qui implémente l'interface `ShouldBroadcast` :

```bash
php artisan make:event TestEvent
```

Puis modifiez la classe générée dans `app/Events/TestEvent.php` :

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TestEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('test-channel');
    }

    public function broadcastAs(): string
    {
        return 'TestEvent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message
        ];
    }
}
```

Notes importantes:

-   `implements ShouldBroadcast` indique à Laravel que cet événement doit être diffusé via WebSockets
-   `broadcastOn()` définit le canal sur lequel l'événement sera diffusé
-   `broadcastAs()` définit le nom de l'événement côté client
-   `broadcastWith()` définit les données à envoyer

## Étape 8: Création d'un contrôleur pour déclencher l'événement

Créez un contrôleur pour déclencher l'événement de test :

```bash
php artisan make:controller TestEventController
```

Mettez à jour le contrôleur dans `app/Http/Controllers/TestEventController.php` :

```php
<?php

namespace App\Http\Controllers;

use App\Events\TestEvent;
use Illuminate\Http\Request;

class TestEventController extends Controller
{
    public function sendTestEvent(Request $request)
    {
        $message = $request->input('message', 'Test message from controller');

        // Déclencher l'événement
        event(new TestEvent($message));

        // Répondre avec confirmation
        return response()->json([
            'success' => true,
            'message' => 'Événement envoyé avec succès',
            'event_data' => [
                'channel' => 'test-channel',
                'event' => 'TestEvent',
                'message' => $message
            ]
        ]);
    }
}
```

## Étape 9: Ajout d'une route API pour tester

Ajoutez une route API dans `routes/api.php` :

```php
use App\Http\Controllers\TestEventController;

// Route pour tester l'envoi d'événements
Route::post('/test-event', [TestEventController::class, 'sendTestEvent']);
```

## Étape 10: Création d'un composant frontend pour tester Echo

Créez un composant Vue pour tester la connexion Echo :

```vue
<template>
    <div class="p-4 bg-white rounded shadow">
        <h3 class="text-lg font-semibold mb-4">Test de connexion Echo</h3>
        <div class="space-y-2">
            <div class="text-sm">
                Status:
                <span
                    :class="{
                        'text-green-600': isConnected,
                        'text-red-600': !isConnected,
                    }"
                >
                    {{ isConnected ? "Connecté" : "Déconnecté" }}
                </span>
            </div>
            <div v-if="error" class="text-red-600 text-sm">
                Erreur: {{ error }}
            </div>
            <div v-if="lastMessage" class="text-sm bg-gray-50 p-2 rounded">
                Dernier message reçu: {{ lastMessage }}
                <span class="text-xs text-gray-500 block mt-1"
                    >Reçu à: {{ lastMessageTime }}</span
                >
            </div>
            <div class="flex space-x-2">
                <button
                    @click="testConnection"
                    class="mt-2 px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
                >
                    Tester la connexion
                </button>
                <button
                    @click="clearLastMessage"
                    class="mt-2 px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600"
                >
                    Effacer message
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from "vue";

const isConnected = ref(false);
const error = ref(null);
const lastMessage = ref(null);
const lastMessageTime = ref(null);

const clearLastMessage = () => {
    lastMessage.value = null;
    lastMessageTime.value = null;
};

const testConnection = () => {
    try {
        if (!window.Echo) {
            error.value = "Echo n'est pas disponible";
            return;
        }

        // Test d'abonnement au canal
        const channel = window.Echo.channel("test-channel");

        // Faire un test en envoyant manuellement un événement au serveur
        fetch("/api/test-event", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content"),
            },
            body: JSON.stringify({
                message:
                    "Test depuis le frontend à " +
                    new Date().toLocaleTimeString(),
            }),
        });
    } catch (err) {
        error.value = err.message;
    }
};

onMounted(() => {
    try {
        if (!window.Echo) {
            error.value = "Echo n'est pas initialisé";
            return;
        }

        // S'abonner au canal de test
        const channel = window.Echo.channel("test-channel");

        // Écouter l'événement TestEvent
        channel.listen("TestEvent", (e) => {
            lastMessage.value = e.message || JSON.stringify(e);
            lastMessageTime.value = new Date().toLocaleTimeString();
            isConnected.value = true;
        });

        // Vérifier périodiquement la connexion
        const checkConnection = setInterval(() => {
            try {
                const channel = window.Echo.connector.channels["test-channel"];
                isConnected.value = !!channel;
            } catch (err) {
                isConnected.value = false;
            }
        }, 5000);

        // Nettoyage
        onUnmounted(() => {
            clearInterval(checkConnection);
            if (window.Echo) {
                window.Echo.leaveChannel("test-channel");
            }
        });
    } catch (err) {
        error.value = err.message;
    }
});
</script>
```

Notes importantes:

-   Le composant affiche l'état de la connexion (connecté/déconnecté)
-   Il permet d'envoyer un événement de test via l'API
-   Il écoute les événements sur le canal `test-channel`

## Étape 11: Démarrage du serveur Reverb

Démarrez le serveur Reverb avec la commande :

```bash
php artisan reverb:start
```

Note importante: Assurez-vous que le port 8002 est disponible. Si nécessaire, vous pouvez modifier le port dans le fichier `.env` et dans la configuration du serveur Reverb.

## Étape 12: Lancement du serveur Laravel

Dans un autre terminal, démarrez le serveur Laravel :

```bash
php artisan serve
```

## Étape 13: Compilation des assets

Compilez les assets JavaScript avec:

```bash
npm run dev
```

## Problèmes courants et solutions

### 1. Problèmes de port

Si vous rencontrez des erreurs indiquant que le port est déjà utilisé, vous pouvez :

-   Changer le port de Reverb dans le fichier `.env`
-   Vérifier quels processus utilisent le port avec `netstat -ano | findstr :8002` (Windows) ou `lsof -i :8002` (Linux/Mac)
-   Arrêter le processus qui utilise ce port

### 2. Événements non reçus par le frontend

Si les événements ne sont pas reçus par le frontend :

-   Vérifiez que l'événement implémente bien l'interface `ShouldBroadcast`
-   Assurez-vous que les noms des canaux correspondent exactement (entre `broadcastOn()` et `Echo.channel()`)
-   Vérifiez que le nom de l'événement dans `broadcastAs()` correspond à ce que vous écoutez avec `channel.listen()`
-   Assurez-vous que les données sont correctement formatées dans `broadcastWith()`

### 3. Erreurs CORS

Si vous rencontrez des erreurs CORS :

-   Vérifiez que `REVERB_HOST` est configuré correctement
-   Assurez-vous que les origines autorisées sont bien configurées dans `config/reverb.php`

### 4. Vérification que tout fonctionne

Pour confirmer que tout fonctionne correctement :

1. Vous devriez voir "Echo initialized with config" dans la console du navigateur
2. Le statut dans le composant EchoTest devrait afficher "Connecté"
3. Vous pouvez tester l'envoi d'événements via Tinker avec:
    ```php
    php artisan tinker
    event(new App\Events\TestEvent('Message de test de Tinker'));
    ```
4. Vous devriez voir le message apparaître dans le composant EchoTest

## Conclusion

Vous avez maintenant configuré avec succès Laravel Echo et Laravel Reverb pour les WebSockets dans votre application Laravel 11. Cette configuration vous permet d'implémenter des fonctionnalités en temps réel comme les notifications, les chats, et les mises à jour en direct.
