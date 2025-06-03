# Système de Notifications et Gestion des Conversations

## Vue d'ensemble

Le système implémente trois fonctionnalités principales :

1. Tri automatique des conversations par date du dernier message
2. Gestion précise des compteurs de notification
3. Indicateurs visuels pour les conversations en attente de réponse

## Architecture Technique

### 1. Base de Données

#### 1.1 Table `conversation_states`

```php
Schema::create('conversation_states', function (Blueprint $table) {
    $table->id();
    $table->foreignId('client_id')->constrained('users');
    $table->foreignId('profile_id')->constrained('profiles');
    $table->unsignedBigInteger('last_read_message_id')->nullable();
    $table->boolean('has_been_opened')->default(false);
    $table->boolean('awaiting_reply')->default(false);
    $table->timestamps();
});
```

Cette table stocke :

-   `last_read_message_id` : ID du dernier message lu
-   `has_been_opened` : Si la conversation a déjà été ouverte
-   `awaiting_reply` : Si une réponse est attendue du client

### 2. Backend (Laravel)

#### 2.1 MessageController

```php
class MessageController extends Controller
{
    /**
     * Récupère les messages avec leur état
     */
    public function getMessages(Request $request)
    {
        // Récupération des messages
        $messages = Message::where('profile_id', $profileId)
            ->where('client_id', $clientId)
            ->orderBy('created_at')
            ->get();

        // Calcul des messages non lus
        $unreadCount = $messages->where('is_from_client', false)
            ->where('read_at', null)
            ->count();

        // Vérification si une réponse est attendue
        $lastMessage = $messages->last();
        $awaitingReply = $lastMessage && !$lastMessage->is_from_client;

        // Gestion de l'état de la conversation
        $conversationState = ConversationState::updateOrCreate(
            ['client_id' => $clientId, 'profile_id' => $profileId],
            [
                'has_been_opened' => true,
                'awaiting_reply' => $awaitingReply
            ]
        );

        return response()->json([
            'messages' => $formattedMessages,
            'conversation_state' => [
                'unread_count' => $unreadCount,
                'awaiting_reply' => $awaitingReply,
                'last_read_message_id' => $conversationState->last_read_message_id,
                'has_been_opened' => $conversationState->has_been_opened
            ]
        ]);
    }

    /**
     * Marque les messages comme lus
     */
    public function markAsRead(Request $request)
    {
        // Marquer tous les messages non lus comme lus
        Message::where('client_id', $clientId)
            ->where('profile_id', $profileId)
            ->where('is_from_client', false)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Mettre à jour l'état de la conversation
        ConversationState::updateOrCreate(
            ['client_id' => $clientId, 'profile_id' => $profileId],
            [
                'last_read_message_id' => $request->last_message_id,
                'has_been_opened' => true,
                'awaiting_reply' => true
            ]
        );
    }
}
```

### 3. Frontend (Vue.js)

#### 3.1 Composant Home.vue

```javascript
// Gestion des états de conversation
const conversationStates = ref(new Map());

// Chargement des messages
async function loadMessages(profileId) {
    const response = await axios.get("/messages", {
        params: { profile_id: profileId },
    });

    // Mise à jour des messages
    messagesMap.value = {
        ...messagesMap.value,
        [profileId]: response.data.messages,
    };

    // Mise à jour de l'état de la conversation
    const state = response.data.conversation_state;
    conversationStates.value.set(profileId, {
        unreadCount: state.unread_count,
        lastReadMessageId: state.last_read_message_id,
        isOpen: selectedProfile.value?.id === profileId,
        hasBeenOpened: state.has_been_opened,
        awaitingReply: state.awaiting_reply,
    });
}

// Gestion des messages en temps réel
window.Echo.private(`client.${window.clientId}`).listen(
    ".message.sent",
    async (data) => {
        const profileId = data.profile_id;

        // Mise à jour des messages
        await loadMessages(profileId);

        // Mise à jour du compteur si ce n'est pas la conversation active
        const state = conversationStates.value.get(profileId);
        if (
            state &&
            (!selectedProfile.value || selectedProfile.value.id !== profileId)
        ) {
            state.unreadCount = (state.unreadCount || 0) + 1;
            state.awaitingReply = true;
        }
    }
);
```

#### 3.2 Composant ActiveConversations.vue

```javascript
// Tri des conversations
const sortedConversations = computed(() => {
    return activeProfiles.value
        .map((profile) => {
            const messages = props.messages[profile.id] || [];
            const lastMessage = messages[messages.length - 1];
            const state = props.conversationStates.get(profile.id) || {
                unreadCount: 0,
                awaitingReply: false,
                hasBeenOpened: false,
            };

            return {
                ...profile,
                lastMessage,
                unreadCount: state.unreadCount || 0,
                awaitingReply: state.awaitingReply || false,
                hasBeenOpened: state.hasBeenOpened || false,
                lastMessageDate: lastMessage
                    ? new Date(lastMessage.created_at)
                    : new Date(0),
            };
        })
        .sort((a, b) => b.lastMessageDate - a.lastMessageDate);
});
```

```vue
<!-- Template pour les indicateurs -->
<template>
    <!-- Badge de notification -->
    <div
        v-if="profile.unreadCount > 0"
        class="absolute -top-1 -right-1 bg-pink-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"
    >
        {{ profile.unreadCount }}
    </div>

    <!-- Indicateur de réponse en attente -->
    <div
        v-else-if="profile.awaitingReply"
        class="absolute -top-1 -right-1 bg-yellow-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"
        title="En attente de votre réponse"
    >
        <i class="fas fa-reply"></i>
    </div>
</template>
```

## Flux de Données

1. **Chargement Initial**

    - Le backend charge les messages et calcule l'état initial
    - Le frontend initialise les états de conversation
    - Les conversations sont triées par date du dernier message

2. **Réception d'un Message**

    - Le WebSocket reçoit le message
    - L'état de la conversation est mis à jour
    - Le compteur de messages non lus est incrémenté si la conversation n'est pas active
    - La liste des conversations est retriée

3. **Ouverture d'une Conversation**

    - Les messages sont marqués comme lus dans la base de données
    - Le compteur de messages non lus est réinitialisé
    - L'indicateur de réponse en attente est affiché si nécessaire

4. **Envoi d'un Message**
    - Le message est enregistré
    - L'état "en attente de réponse" est désactivé
    - La conversation est déplacée en haut de la liste

## Indicateurs Visuels

1. **Badge Rose** : Affiche le nombre de messages non lus
2. **Badge Jaune** : Indique qu'une réponse est attendue
3. **Tri** : Les conversations les plus récentes apparaissent en haut
