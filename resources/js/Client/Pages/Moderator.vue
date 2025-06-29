<template>
    <div
        v-if="!moderatorStore.initialized"
        class="flex items-center justify-center min-h-screen bg-gradient-to-br from-pink-100 via-purple-100 to-white"
    >
        <div class="flex flex-col items-center space-y-4">
            <svg
                class="animate-spin h-12 w-12 text-pink-500"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                ></circle>
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8v8z"
                ></path>
            </svg>
            <div class="text-lg font-semibold text-pink-600">
                Chargement de l'espace modérateur...
            </div>
            <div class="text-sm text-gray-500">
                Merci de patienter, nous synchronisons vos profils virtuels 👩‍💻
            </div>
        </div>
    </div>

    <!-- Écran de chargement pour le changement de profil -->
    <div
        v-else-if="moderatorStore.profileTransition.loadingData"
        class="flex items-center justify-center min-h-screen bg-gradient-to-br from-pink-100 via-purple-100 to-white"
    >
        <div class="flex flex-col items-center space-y-4">
            <svg
                class="animate-spin h-12 w-12 text-pink-500"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                ></circle>
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8v8z"
                ></path>
            </svg>
            <div class="text-lg font-semibold text-pink-600">
                Changement de profil en cours...
            </div>
            <div class="text-sm text-gray-500">
                Nous chargeons les données du nouveau profil et ses
                conversations
            </div>
        </div>
    </div>

    <div v-else>
        <MainLayout>
            <!-- Interface de file d'attente -->
            <div
                v-if="
                    moderatorStore.queueInfo.inQueue &&
                    !moderatorStore.currentAssignedProfile
                "
                class="bg-white p-6 rounded-xl shadow-md text-center mt-4"
            >
                <div class="text-lg font-semibold text-pink-600 mb-2">
                    Vous êtes en file d'attente pour l'attribution d'un profil
                </div>

                <div class="flex items-center justify-center mb-4">
                    <div class="relative w-24 h-24">
                        <svg
                            class="w-24 h-24 transform -rotate-90"
                            viewBox="0 0 100 100"
                        >
                            <circle
                                class="text-gray-200"
                                stroke-width="8"
                                stroke="currentColor"
                                fill="transparent"
                                r="46"
                                cx="50"
                                cy="50"
                            />
                            <circle
                                class="text-pink-500"
                                stroke-width="8"
                                stroke="currentColor"
                                fill="transparent"
                                r="46"
                                cx="50"
                                cy="50"
                                :stroke-dasharray="2 * Math.PI * 46"
                                :stroke-dashoffset="
                                    2 *
                                    Math.PI *
                                    46 *
                                    (1 - 1 / moderatorStore.queueInfo.position)
                                "
                            />
                        </svg>
                        <div
                            class="absolute inset-0 flex items-center justify-center text-2xl font-bold text-pink-600"
                        >
                            {{ moderatorStore.queueInfo.position }}
                        </div>
                    </div>
                </div>

                <p class="text-gray-600 mb-2">
                    Votre position dans la file d'attente
                </p>

                <p class="text-gray-500 text-sm mb-4">
                    Temps d'attente estimé:
                    <span class="font-semibold"
                        >{{
                            moderatorStore.queueInfo.estimatedWaitTime
                        }}
                        minutes</span
                    >
                </p>

                <p class="text-gray-400 text-xs">
                    En attente depuis:
                    {{ formatRelativeTime(moderatorStore.queueInfo.queuedAt) }}
                </p>

                <div class="mt-4">
                    <button
                        @click="leaveQueue"
                        class="bg-red-100 text-red-600 px-4 py-2 rounded-lg hover:bg-red-200 transition-colors duration-200"
                    >
                        <i class="fas fa-sign-out-alt mr-2"></i>
                        Quitter la file d'attente
                    </button>
                </div>
            </div>

            <!-- Indicateurs de verrouillage pour les profils -->
            <div
                v-if="isProfileLocked && moderatorStore.currentAssignedProfile"
                class="bg-yellow-100 border-l-4 border-yellow-500 p-4 rounded-md mb-4"
            >
                <div class="flex items-center">
                    <div class="flex-shrink-0 text-yellow-500">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            Ce profil est temporairement verrouillé
                            <span
                                v-if="
                                    profileLockInfo.moderatorId !==
                                    moderatorStore.moderatorId
                                "
                            >
                                par un autre modérateur
                            </span>
                            <span v-else> par vous </span>
                        </p>
                        <p
                            class="text-xs text-yellow-600"
                            v-if="profileLockTimeRemaining > 0"
                        >
                            Déverrouillage dans
                            {{ formatSeconds(profileLockTimeRemaining) }}
                        </p>
                    </div>
                    <div class="ml-auto">
                        <button
                            v-if="canRequestUnlock"
                            @click="
                                requestUnlock(
                                    moderatorStore.currentAssignedProfile.id
                                )
                            "
                            class="bg-yellow-200 text-yellow-800 px-3 py-1 rounded text-sm hover:bg-yellow-300"
                        >
                            Demander déverrouillage
                        </button>
                    </div>
                </div>
            </div>

            <!-- Alerte de conflit d'attribution -->
            <div
                v-if="hasActiveConflicts"
                class="bg-red-100 border-l-4 border-red-500 p-4 rounded-md mb-4"
            >
                <div class="flex items-center">
                    <div class="flex-shrink-0 text-red-500">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-red-700">
                            Conflit d'attribution détecté
                        </p>
                        <p class="text-xs text-red-600">
                            {{ latestConflict.message }}
                        </p>
                    </div>
                    <div class="ml-auto">
                        <button
                            @click="acknowledgeConflict(latestConflict.id)"
                            class="bg-red-200 text-red-800 px-3 py-1 rounded text-sm hover:bg-red-300"
                        >
                            J'ai compris
                        </button>
                    </div>
                </div>
            </div>

            <!-- Alerte de réattribution forcée -->
            <div
                v-if="showReassignmentAlert"
                class="bg-pink-100 border-l-4 border-pink-500 p-4 rounded-md mb-4 animate-pulse"
            >
                <div class="flex items-center">
                    <div class="flex-shrink-0 text-pink-500">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-pink-700">
                            Réattribution imminente
                        </p>
                        <p class="text-xs text-pink-600">
                            En raison d'inactivité, ce profil va être réattribué
                            dans {{ formatSeconds(reassignmentCountdown) }}
                        </p>
                    </div>
                    <div class="ml-auto">
                        <button
                            @click="preventReassignment"
                            class="bg-pink-200 text-pink-800 px-3 py-1 rounded text-sm hover:bg-pink-300"
                        >
                            Je suis toujours actif
                        </button>
                    </div>
                </div>
            </div>
            <!-- Notification de compte à rebours pour le changement de profil -->
            <div
                v-if="
                    moderatorStore.profileTransition.inProgress &&
                    !moderatorStore.profileTransition.loadingData
                "
                class="fixed top-4 right-4 bg-white shadow-lg rounded-lg p-4 z-50 max-w-md border-l-4 border-pink-500 animate-pulse"
            >
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <svg
                            class="h-10 w-10 text-pink-500"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800">
                            Changement de profil imminent !
                        </h3>
                        <p class="text-sm text-gray-600">
                            Votre profil va changer dans
                            <span class="font-bold text-pink-600">{{
                                moderatorStore.profileTransition.countdown
                            }}</span>
                            secondes
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            Nouveau profil :
                            {{
                                moderatorStore.profileTransition.newProfile
                                    ?.name
                            }}
                        </p>
                    </div>
                </div>
                <!-- Bouton pour demander un délai supplémentaire -->
                <div class="mt-2" v-if="!moderatorStore.delayRequested">
                    <button
                        @click="requestDelay"
                        class="bg-white text-pink-600 border border-pink-500 px-3 py-1 rounded-md text-sm hover:bg-pink-50 transition-colors"
                    >
                        <i class="fas fa-clock mr-1"></i>
                        Demander 5 min supplémentaires
                    </button>
                </div>
            </div>

            <div class="main-container">
                <div class="flex flex-col space-y-4 mb-4">
                    <div class="bg-white p-4 rounded-xl shadow-md">
                        <div class="flex justify-between items-center">
                            <div>
                                <h2 class="text-xl font-semibold text-pink-600">
                                    Espace Modérateur
                                </h2>
                                <p class="text-sm text-gray-600">
                                    Vous êtes connecté en tant que modérateur.
                                    Vous pouvez discuter avec des clients en
                                    utilisant un profil virtuel.
                                </p>
                            </div>
                            <div class="flex items-center gap-4">
                                <!-- Indicateur d'état WebSocket -->
                                <div
                                    class="flex items-center gap-2"
                                    @click="checkWebSocketConnection"
                                    title="Vérifier la connexion"
                                >
                                    <div
                                        class="w-3 h-3 rounded-full"
                                        :class="{
                                            'bg-green-500':
                                                connectionState === 'healthy',
                                            'bg-yellow-500':
                                                connectionState === 'degraded',
                                            'bg-red-500':
                                                connectionState ===
                                                'disconnected',
                                            'bg-blue-500 animate-pulse':
                                                connectionState ===
                                                'connecting',
                                        }"
                                    ></div>
                                    <span class="text-xs text-gray-600">{{
                                        connectionStateLabel
                                    }}</span>
                                </div>

                                <!-- Bouton de notifications -->
                                <div class="relative">
                                    <button
                                        @click="
                                            showNotifications =
                                                !showNotifications
                                        "
                                        class="px-4 py-2 bg-pink-100 text-pink-600 rounded-lg hover:bg-pink-200 transition-colors duration-200 flex items-center gap-2"
                                    >
                                        <i class="fas fa-bell"></i>
                                        <span
                                            v-if="
                                                notifications.filter(
                                                    (n) => !n.read
                                                ).length > 0
                                            "
                                            class="absolute -top-1 -right-1 bg-pink-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs"
                                        >
                                            {{
                                                notifications.filter(
                                                    (n) => !n.read
                                                ).length
                                            }}
                                        </span>
                                    </button>

                                    <!-- Panel de notifications -->
                                    <div
                                        v-if="showNotifications"
                                        class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg z-50 max-h-96 overflow-y-auto"
                                    >
                                        <div
                                            class="p-4 border-b border-gray-100"
                                        >
                                            <h3
                                                class="font-semibold text-gray-700"
                                            >
                                                Notifications
                                            </h3>
                                        </div>
                                        <div
                                            v-if="notifications.length > 0"
                                            class="divide-y divide-gray-100"
                                        >
                                            <div
                                                v-for="notification in notifications"
                                                :key="notification.id"
                                                @click="
                                                    goToConversation(
                                                        notification.clientId
                                                    );
                                                    markNotificationAsRead(
                                                        notification.id
                                                    );
                                                    showNotifications = false;
                                                "
                                                class="p-4 hover:bg-gray-50 cursor-pointer transition-colors duration-200"
                                                :class="{
                                                    'bg-pink-50':
                                                        !notification.read,
                                                }"
                                            >
                                                <div
                                                    class="flex items-start gap-3"
                                                >
                                                    <div class="flex-1">
                                                        <p
                                                            class="font-medium text-gray-800"
                                                        >
                                                            {{
                                                                notification.clientName
                                                            }}
                                                        </p>
                                                        <p
                                                            class="text-sm text-gray-600 truncate"
                                                        >
                                                            {{
                                                                notification.message
                                                            }}
                                                        </p>
                                                        <p
                                                            class="text-xs text-gray-400 mt-1"
                                                        >
                                                            {{
                                                                new Date(
                                                                    notification.timestamp
                                                                ).toLocaleTimeString(
                                                                    [],
                                                                    {
                                                                        hour: "2-digit",
                                                                        minute: "2-digit",
                                                                    }
                                                                )
                                                            }}
                                                        </p>
                                                    </div>
                                                    <div
                                                        v-if="
                                                            !notification.read
                                                        "
                                                        class="w-2 h-2 bg-pink-500 rounded-full flex-shrink-0 mt-2"
                                                    ></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            v-else
                                            class="p-4 text-center text-gray-500"
                                        >
                                            Aucune notification
                                        </div>
                                    </div>
                                </div>

                                <Link
                                    href="/moderateur/profile-stats"
                                    class="px-4 py-2 bg-pink-100 text-pink-600 rounded-lg hover:bg-pink-200 transition-colors duration-200 flex items-center gap-2"
                                >
                                    <i class="fas fa-chart-line"></i>
                                    Mon profil
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="!currentAssignedProfile"
                        class="bg-white p-6 rounded-xl shadow-md text-center"
                    >
                        <div class="text-lg font-medium text-gray-700">
                            En attente d'attribution...
                        </div>
                        <p class="text-gray-500 mt-2">
                            Le système vous attribuera automatiquement un profil
                            pour discuter avec des clients.
                        </p>
                        <div class="mt-4">
                            <div
                                class="animate-pulse flex space-x-4 justify-center"
                            >
                                <div
                                    class="rounded-full bg-pink-200 h-12 w-12"
                                ></div>
                                <div class="flex-1 space-y-4 max-w-md">
                                    <div
                                        class="h-4 bg-pink-200 rounded w-3/4"
                                    ></div>
                                    <div
                                        class="h-4 bg-pink-200 rounded w-1/2"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Alerte d'erreur WebSocket -->
                    <div
                        v-if="webSocketErrors"
                        class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-md mb-4"
                    >
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="ml-3">
                                <p class="font-medium">
                                    Problème de connexion WebSocket
                                </p>
                                <p class="text-sm">{{ webSocketErrors }}</p>
                            </div>
                            <div class="ml-auto">
                                <button
                                    @click="forceReconnect"
                                    class="bg-yellow-200 hover:bg-yellow-300 text-yellow-800 px-3 py-1 rounded text-sm"
                                >
                                    <i class="fas fa-sync-alt mr-1"></i>
                                    Reconnecter
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col lg:flex-row gap-6">
                    <!-- Clients Section (à gauche) -->
                    <div
                        class="w-full lg:w-1/4 bg-white rounded-xl shadow-md overflow-hidden"
                    >
                        <!-- Tabs -->
                        <div class="flex border-b border-gray-200">
                            <button
                                @click="activeTab = 'assigned'"
                                :class="[
                                    'flex-1 py-3 text-sm font-medium',
                                    activeTab === 'assigned'
                                        ? 'text-pink-600 border-b-2 border-pink-500'
                                        : 'text-gray-500 hover:text-gray-700',
                                ]"
                            >
                                Client attribué
                            </button>
                            <button
                                @click="activeTab = 'available'"
                                :class="[
                                    'flex-1 py-3 text-sm font-medium',
                                    activeTab === 'available'
                                        ? 'text-pink-600 border-b-2 border-pink-500'
                                        : 'text-gray-500 hover:text-gray-700',
                                ]"
                            >
                                Clients disponibles
                            </button>
                        </div>

                        <!-- Tab Content: Client attribué -->
                        <div v-if="activeTab === 'assigned'" class="p-4">
                            <div class="flex justify-between items-center mb-4">
                                <div class="flex items-center space-x-2">
                                    <h2 class="text-xl font-semibold">
                                        {{
                                            currentAssignedProfile?.name ||
                                            "Aucun profil assigné"
                                        }}
                                    </h2>
                                    <span
                                        v-if="isProfileShared"
                                        class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded"
                                        title="Ce profil est partagé avec d'autres modérateurs"
                                    >
                                        Partagé
                                    </span>
                                    <button
                                        @click="refreshProfileData"
                                        class="p-2 rounded-full bg-pink-100 text-pink-600 hover:bg-pink-200 transition-colors"
                                        title="Rafraîchir les données"
                                    >
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                                <div
                                    v-if="assignedClient.length > 0"
                                    class="bg-green-100 text-green-600 px-3 py-1 rounded-full text-sm"
                                >
                                    En attente de réponse
                                </div>
                                <div
                                    v-else
                                    class="bg-yellow-100 text-yellow-600 px-3 py-1 rounded-full text-sm"
                                >
                                    En attente d'attribution
                                </div>
                            </div>

                            <div class="space-y-4">
                                <!-- Liste des clients attribués -->
                                <div
                                    v-if="assignedClient.length > 0"
                                    class="space-y-4"
                                >
                                    <div
                                        v-for="client in sortedAssignedClients"
                                        :key="client.id"
                                        class="client-card transition duration-300"
                                        @click="selectClient(client)"
                                    >
                                        <div
                                            :class="[
                                                'bg-white rounded-lg shadow-sm p-4 flex items-center space-x-3 border border-gray-100',
                                                selectedClient &&
                                                selectedClient.id === client.id
                                                    ? 'border-l-4 border-pink-500'
                                                    : '',
                                            ]"
                                        >
                                            <div class="relative">
                                                <template v-if="client.avatar">
                                                    <img
                                                        :src="client.avatar"
                                                        :alt="client.name"
                                                        class="w-12 h-12 rounded-full object-cover"
                                                    />
                                                </template>
                                                <template v-else>
                                                    <div
                                                        class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center"
                                                    >
                                                        <i
                                                            class="fas fa-user text-gray-400 text-xl"
                                                        ></i>
                                                    </div>
                                                </template>
                                                <div class="online-dot"></div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div
                                                    class="flex items-center justify-between"
                                                >
                                                    <h3
                                                        class="font-semibold truncate"
                                                    >
                                                        {{ client.name }}
                                                    </h3>
                                                    <span
                                                        class="text-xs text-gray-500"
                                                        >{{
                                                            formatTime(
                                                                client.createdAt
                                                            )
                                                        }}</span
                                                    >
                                                </div>
                                                <p
                                                    class="text-sm text-gray-500"
                                                >
                                                    <span
                                                        v-if="
                                                            client.lastMessage
                                                        "
                                                        class="truncate block"
                                                        >{{
                                                            client.lastMessage
                                                        }}</span
                                                    >
                                                    <span
                                                        v-else
                                                        class="text-gray-400 italic"
                                                        >Nouvelle
                                                        conversation</span
                                                    >
                                                </p>
                                                <div
                                                    class="flex items-center mt-1 text-xs"
                                                >
                                                    <!-- Si le client a des infos de profil -->
                                                    <template
                                                        v-if="
                                                            client.profileInfo
                                                        "
                                                    >
                                                        <span
                                                            :class="`${
                                                                !client
                                                                    .profileInfo
                                                                    .isPrimary
                                                                    ? 'bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full'
                                                                    : 'text-gray-600'
                                                            }`"
                                                        >
                                                            <i
                                                                class="fas fa-user-circle mr-1"
                                                            ></i>
                                                            {{
                                                                client
                                                                    .profileInfo
                                                                    .name
                                                            }}
                                                        </span>
                                                    </template>
                                                    <!-- Ancienne façon d'afficher le profil si disponible -->
                                                    <template
                                                        v-else-if="
                                                            client.profilePhoto
                                                        "
                                                    >
                                                        <img
                                                            :src="
                                                                client.profilePhoto
                                                            "
                                                            alt="Profile"
                                                            class="w-4 h-4 rounded-full mr-1"
                                                        />
                                                        <span
                                                            class="text-gray-600"
                                                            >{{
                                                                client.profileName
                                                            }}</span
                                                        >
                                                    </template>
                                                </div>
                                            </div>
                                            <div
                                                v-if="client.unreadCount"
                                                class="bg-pink-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs"
                                            >
                                                {{ client.unreadCount }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- État vide -->
                                <div v-else class="text-center py-8">
                                    <p class="text-gray-500">
                                        Aucun client ne vous a été attribué pour
                                        le moment.
                                    </p>
                                    <p class="text-gray-400 text-sm mt-2">
                                        Le système vous attribuera
                                        automatiquement un client qui attend une
                                        réponse, ou consultez les clients
                                        disponibles.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Content: Clients disponibles -->
                        <div v-if="activeTab === 'available'" class="p-4">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-xl font-semibold">
                                    Clients disponibles
                                </h2>
                                <button
                                    @click="loadAvailableClients"
                                    class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition"
                                >
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>

                            <div class="space-y-4">
                                <!-- Liste des clients disponibles -->
                                <div v-if="availableClients.length > 0">
                                    <div
                                        v-for="client in availableClients"
                                        :key="client.id"
                                        class="client-card transition duration-300 cursor-pointer"
                                        @click="startConversation(client)"
                                    >
                                        <div
                                            class="bg-white rounded-lg shadow-sm p-4 flex items-center space-x-3 border border-gray-100 hover:border-pink-200"
                                        >
                                            <div class="relative">
                                                <template v-if="client.avatar">
                                                    <img
                                                        :src="client.avatar"
                                                        :alt="client.name"
                                                        class="w-12 h-12 rounded-full object-cover"
                                                    />
                                                </template>
                                                <template v-else>
                                                    <div
                                                        class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center"
                                                    >
                                                        <i
                                                            class="fas fa-user text-gray-400 text-xl"
                                                        ></i>
                                                    </div>
                                                </template>
                                                <div class="online-dot"></div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h3
                                                    class="font-semibold truncate"
                                                >
                                                    {{ client.name }}
                                                </h3>
                                                <p
                                                    class="text-sm text-gray-500"
                                                >
                                                    <span
                                                        v-if="
                                                            client.lastMessage
                                                        "
                                                        class="truncate block"
                                                        >{{
                                                            client.lastMessage
                                                        }}</span
                                                    >
                                                    <span
                                                        v-else-if="
                                                            client.hasHistory
                                                        "
                                                        class="text-gray-400 italic"
                                                        >Conversation
                                                        précédente</span
                                                    >
                                                    <span
                                                        v-else
                                                        class="text-green-500 italic"
                                                        >Nouveau client</span
                                                    >
                                                </p>
                                                <p
                                                    class="text-xs text-gray-400 mt-1"
                                                >
                                                    {{ client.lastActivity }}
                                                </p>
                                            </div>
                                            <button
                                                class="p-2 rounded-full bg-pink-100 text-pink-600 hover:bg-pink-200 transition"
                                            >
                                                <i class="fas fa-comments"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- État de chargement -->
                                <div v-else-if="loading" class="py-8">
                                    <div
                                        class="animate-pulse flex space-x-4 justify-center"
                                    >
                                        <div
                                            class="rounded-full bg-pink-200 h-12 w-12"
                                        ></div>
                                        <div class="flex-1 space-y-4 max-w-md">
                                            <div
                                                class="h-4 bg-pink-200 rounded w-3/4"
                                            ></div>
                                            <div
                                                class="h-4 bg-pink-200 rounded w-1/2"
                                            ></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- État vide -->
                                <div v-else class="text-center py-8">
                                    <p class="text-gray-500">
                                        Aucun client disponible pour le moment.
                                    </p>
                                    <p class="text-gray-400 text-sm mt-2">
                                        Réessayez plus tard ou attendez qu'un
                                        client soit attribué automatiquement.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Section -->
                    <div
                        class="w-full lg:w-2/4 flex flex-col"
                        ref="chatSection"
                    >
                        <!-- Version mobile du ClientInfoPanel -->
                        <div class="lg:hidden">
                            <ClientInfoDrawer
                                v-if="selectedClient"
                                :key="`drawer-${selectedClient.id}`"
                                :client-id="selectedClient.id"
                                @edit="openFullInfoModal"
                            />
                        </div>

                        <!-- Profil attribué -->
                        <!-- Profil attribué -->
                        <div
                            v-if="currentAssignedProfile"
                            class="bg-white rounded-xl shadow-md p-4 mb-4"
                        >
                            <div class="flex items-center space-x-4">
                                <img
                                    :src="
                                        currentAssignedProfile.main_photo_path ||
                                        '/images/default-avatar.png'
                                    "
                                    :alt="currentAssignedProfile.name"
                                    class="w-16 h-16 rounded-full object-cover"
                                />
                                <div>
                                    <h3
                                        class="text-lg font-semibold text-gray-800"
                                    >
                                        {{ currentAssignedProfile.name }}
                                    </h3>
                                    <p class="text-sm text-gray-600">
                                        Profil virtuel attribué
                                    </p>
                                    <div
                                        class="flex items-center mt-1 text-xs text-gray-500"
                                    >
                                        <span class="mr-2">
                                            <i class="fas fa-venus-mars"></i>
                                            {{
                                                currentAssignedProfile.gender ===
                                                "female"
                                                    ? "Femme"
                                                    : "Homme"
                                            }}
                                        </span>
                                        <span v-if="currentAssignedProfile.age">
                                            <i
                                                class="fas fa-birthday-cake ml-2 mr-1"
                                            ></i>
                                            {{ currentAssignedProfile.age }} ans
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Notification de profil partagé -->
                        <div
                            v-if="isProfileShared"
                            class="shared-profile-indicator bg-blue-100 text-blue-700 px-3 py-2 rounded-md mt-2"
                        >
                            <div class="flex items-center">
                                <i class="fas fa-users mr-2"></i>
                                <span
                                    >Ce profil est partagé avec d'autres
                                    modérateurs</span
                                >
                            </div>
                        </div>

                        <!-- Indicateurs d'activité des autres modérateurs -->
                        <div
                            v-if="otherModeratorsActive.length > 0"
                            class="bg-gray-50 p-2 rounded-md mt-2 border border-gray-100"
                        >
                            <p class="text-xs text-gray-600 font-medium">
                                <i class="fas fa-user-clock mr-1"></i>
                                Autres modérateurs actifs sur ce profil:
                            </p>
                            <div
                                v-for="activity in otherModeratorsActive"
                                :key="activity.moderatorId"
                                class="text-xs text-gray-500 mt-1"
                            >
                                <span
                                    >Modérateur #{{
                                        activity.moderatorId
                                    }}</span
                                >
                                <span class="mx-1">•</span>
                                <span>{{
                                    activity.activityType === "typing"
                                        ? "écrit"
                                        : activity.activityType
                                }}</span>
                                <span class="ml-2 text-gray-400">{{
                                    formatTime(activity.timestamp)
                                }}</span>
                            </div>
                        </div>

                        <!-- Chat Content -->
                        <div
                            v-if="selectedClient"
                            class="bg-white rounded-xl shadow-md overflow-hidden flex flex-col h-[calc(100vh-theme(spacing.32))]"
                            :key="`chat-${currentAssignedProfile?.id}-${selectedClient.id}`"
                        >
                            <!-- Chat Header -->
                            <div
                                class="border-b border-gray-200 p-4 flex items-center space-x-3"
                            >
                                <div class="relative">
                                    <template v-if="selectedClient.avatar">
                                        <img
                                            :src="selectedClient.avatar"
                                            :alt="selectedClient.name"
                                            class="w-12 h-12 rounded-full object-cover"
                                        />
                                    </template>
                                    <template v-else>
                                        <div
                                            class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center"
                                        >
                                            <i
                                                class="fas fa-user text-gray-400 text-xl"
                                            ></i>
                                        </div>
                                    </template>
                                    <div class="online-dot"></div>
                                </div>
                                <div>
                                    <h3 class="font-semibold">
                                        {{ selectedClient.name }}
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        En discussion avec vous
                                    </p>
                                </div>
                                <div
                                    class="ml-auto flex items-center space-x-2"
                                >
                                    <div class="text-sm text-gray-500">
                                        <span class="font-medium"
                                            >Client ID:</span
                                        >
                                        {{ selectedClient.id }}
                                    </div>
                                    <button
                                        @click="openFullInfoModal"
                                        class="p-2 rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 transition"
                                    >
                                        <i class="fas fa-info-circle"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Chat Messages -->
                            <div
                                class="chat-container flex-1 overflow-y-auto p-4 space-y-3"
                                ref="chatContainer"
                                @scroll="handleScroll"
                            >
                                <!-- Indicateur de chargement des messages plus anciens -->
                                <div
                                    v-if="isLoadingMore"
                                    class="text-center py-2"
                                >
                                    <div
                                        class="inline-block animate-spin rounded-full h-4 w-4 border-2 border-pink-500 border-t-transparent"
                                    ></div>
                                    <span class="text-xs text-gray-500 ml-2"
                                        >Chargement des messages...</span
                                    >
                                </div>

                                <!-- Indicateur de messages plus anciens disponibles -->
                                <div
                                    v-if="hasMoreMessages && !isLoadingMore"
                                    class="text-center text-xs text-gray-500 my-2"
                                >
                                    Faites défiler vers le haut pour charger
                                    plus de messages
                                </div>

                                <!-- Date -->
                                <div
                                    class="text-center text-xs text-gray-500 my-4"
                                >
                                    Aujourd'hui
                                </div>

                                <div
                                    v-for="(
                                        message, index
                                    ) in currentChatMessages"
                                    :key="message.id || index"
                                    :class="`flex space-x-2 ${
                                        message.isFromClient
                                            ? ''
                                            : 'justify-end'
                                    }`"
                                >
                                    <template v-if="message.isFromClient">
                                        <template v-if="selectedClient.avatar">
                                            <img
                                                :src="selectedClient.avatar"
                                                :alt="selectedClient.name"
                                                class="w-8 h-8 rounded-full object-cover flex-shrink-0"
                                            />
                                        </template>
                                        <template v-else>
                                            <div
                                                class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0"
                                            >
                                                <i
                                                    class="fas fa-user text-gray-400 text-sm"
                                                ></i>
                                            </div>
                                        </template>
                                        <div>
                                            <div
                                                class="message-in px-4 py-2 max-w-xs lg:max-w-md"
                                            >
                                                <!-- Contenu du message -->
                                                <div v-if="message.content">
                                                    {{ message.content }}
                                                </div>

                                                <!-- Image attachée -->
                                                <div
                                                    v-if="
                                                        message.attachment &&
                                                        message.attachment.mime_type.startsWith(
                                                            'image/'
                                                        )
                                                    "
                                                    class="mt-2"
                                                >
                                                    <img
                                                        :src="
                                                            message.attachment
                                                                .url
                                                        "
                                                        :alt="
                                                            message.attachment
                                                                .file_name
                                                        "
                                                        class="max-w-full rounded-lg cursor-pointer"
                                                        @click="
                                                            showImagePreview(
                                                                message.attachment
                                                            )
                                                        "
                                                    />
                                                </div>
                                            </div>
                                            <div
                                                class="flex items-center mt-1 text-xs text-gray-500"
                                            >
                                                <span>{{ message.time }}</span>
                                                <span class="mx-1">•</span>
                                                <span>{{
                                                    selectedClient.name
                                                }}</span>
                                            </div>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div>
                                            <div
                                                class="message-out px-4 py-2 max-w-xs lg:max-w-md"
                                            >
                                                <!-- Contenu du message -->
                                                <div v-if="message.content">
                                                    {{ message.content }}
                                                </div>

                                                <!-- Image attachée -->
                                                <div
                                                    v-if="
                                                        message.attachment &&
                                                        message.attachment.mime_type.startsWith(
                                                            'image/'
                                                        )
                                                    "
                                                    class="mt-2"
                                                >
                                                    <img
                                                        :src="
                                                            message.attachment
                                                                .url
                                                        "
                                                        :alt="
                                                            message.attachment
                                                                .file_name
                                                        "
                                                        class="max-w-full rounded-lg cursor-pointer"
                                                        @click="
                                                            showImagePreview(
                                                                message.attachment
                                                            )
                                                        "
                                                    />
                                                </div>
                                            </div>
                                            <div
                                                class="flex items-center justify-end mt-1 text-xs text-gray-500"
                                            >
                                                <span>{{ message.time }}</span>
                                                <span class="mx-1">•</span>
                                                <span>{{
                                                    currentAssignedProfile?.name ||
                                                    "Vous"
                                                }}</span>
                                            </div>
                                        </div>
                                        <img
                                            :src="
                                                currentAssignedProfile?.main_photo_path ||
                                                'https://via.placeholder.com/64'
                                            "
                                            :alt="
                                                currentAssignedProfile?.name ||
                                                'Profil'
                                            "
                                            class="w-8 h-8 rounded-full object-cover flex-shrink-0"
                                        />
                                    </template>
                                </div>
                            </div>

                            <!-- Indicateur d'activité de frappe -->
                            <div
                                v-if="isTyping"
                                class="typing-indicator text-xs text-gray-500 animate-pulse mb-3 px-4"
                            >
                                <div class="flex items-center">
                                    <span class="mr-2"
                                        >{{ selectedClient.name }} est en train
                                        d'écrire...</span
                                    >
                                    <div class="flex space-x-1">
                                        <div
                                            class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"
                                            style="animation-delay: 0s"
                                        ></div>
                                        <div
                                            class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"
                                            style="animation-delay: 0.2s"
                                        ></div>
                                        <div
                                            class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"
                                            style="animation-delay: 0.4s"
                                        ></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Message Input -->
                            <div
                                class="border-t border-gray-200 bg-white z-50 p-4 mb-16 lg:mb-0"
                            >
                                <div class="flex flex-col space-y-2">
                                    <!-- Prévisualisation de l'image -->
                                    <div
                                        v-if="selectedFile"
                                        class="flex justify-end"
                                    >
                                        <div class="relative inline-block">
                                            <img
                                                :src="previewUrl"
                                                class="max-h-32 rounded-lg"
                                                alt="Preview"
                                            />
                                            <button
                                                @click="removeSelectedFile"
                                                class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center"
                                            >
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="flex items-center space-x-2">
                                        <input
                                            type="file"
                                            ref="fileInput"
                                            class="hidden"
                                            accept="image/*"
                                            @change="handleFileUpload"
                                        />

                                        <!-- Sélecteur de photos de profil -->
                                        <ProfilePhotoSelector
                                            v-if="
                                                currentAssignedProfile &&
                                                selectedClient
                                            "
                                            :profile-id="
                                                currentAssignedProfile.id
                                            "
                                            :client-id="selectedClient.id"
                                            @photo-selected="
                                                handleProfilePhotoSelected
                                            "
                                        />

                                        <div class="flex-1 relative">
                                            <input
                                                v-model="newMessage"
                                                type="text"
                                                placeholder="Écrire un message..."
                                                class="w-full px-4 py-2 bg-gray-100 rounded-full focus:outline-none focus:ring-2 focus:ring-pink-500"
                                                @keyup.enter="sendMessage"
                                            />
                                        </div>
                                        <button
                                            class="p-2 rounded-full bg-pink-500 text-white hover:bg-pink-600 transition"
                                            @click="sendMessage"
                                        >
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- État vide pour le chat -->
                        <div
                            v-else
                            class="bg-white rounded-xl shadow-md p-8 flex-1 flex items-center justify-center"
                        >
                            <div class="text-center">
                                <div class="text-gray-400 mb-4">
                                    <i class="fas fa-comments text-5xl"></i>
                                </div>
                                <h3 class="text-lg font-medium text-gray-700">
                                    Sélectionnez un client pour discuter
                                </h3>
                                <p class="text-gray-500 mt-2">
                                    Choisissez un client attribué ou disponible
                                    pour commencer une conversation
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Informations client (à droite) - Version desktop uniquement -->
                    <div class="hidden lg:block lg:w-1/4">
                        <ClientInfoPanel
                            v-if="selectedClient"
                            :client-id="selectedClient.id"
                        />
                    </div>
                </div>

                <!-- Modals -->
                <Teleport to="body">
                    <!-- Modal pour édition complète sur mobile -->
                    <div
                        v-if="showFullInfoModal"
                        class="fixed inset-0 z-50 lg:hidden bg-white"
                    >
                        <div class="h-full overflow-y-auto">
                            <div
                                class="sticky top-0 bg-white border-b border-gray-200 p-4 flex items-center justify-between z-10"
                            >
                                <h2 class="text-lg font-semibold text-gray-800">
                                    Informations du client
                                </h2>
                                <button
                                    @click="showFullInfoModal = false"
                                    class="p-2 rounded-full hover:bg-gray-100"
                                >
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="p-4 pb-32">
                                <ClientInfoPanel
                                    v-if="selectedClient"
                                    :client-id="selectedClient.id"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Modal de prévisualisation d'image -->
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

                    <!-- Autres modals existants -->
                    <ProfileActionModal
                        v-if="showActionModal"
                        :show="showActionModal"
                        :profile="selectedProfileForActions"
                        @close="closeActionModal"
                        @chat="startChat"
                    />

                    <ProfileReportModal
                        v-if="showReportModalFlag && selectedProfileForReport"
                        :show="showReportModalFlag"
                        :user-id="selectedProfileForReport.userId"
                        :profile-id="selectedProfileForReport.profileId"
                        @close="closeReportModal"
                        @reported="handleReported"
                    />
                </Teleport>
            </div>
        </MainLayout>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import { useModeratorStore } from "@/stores/moderatorStore";
import { useWebSocketHealth } from "@/composables/useWebSocketHealth";
import axios from "axios";
import MainLayout from "../Layouts/MainLayout.vue";
import webSocketManager from "@/services/WebSocketManager";
import ClientInfoPanel from "@client/Components/ClientInfoPanel.vue";
import ClientInfoDrawer from "@client/Components/ClientInfoDrawer.vue";
import ProfileActionModal from "@client/Components/ProfileActionModal.vue";
import ProfileReportModal from "@client/Components/ProfileReportModal.vue";
import ProfilePhotoSelector from "@client/Components/ProfilePhotoSelector.vue";
import { toast } from 'vue3-toastify';
import 'vue3-toastify/dist/index.css';

// Ajouter un écouteur pour détecter quand Echo est prêt
document.addEventListener("echo:initialized", () => {
    console.log("🔄 Echo initialisé, vérification du token CSRF...");
    checkEchoCSRFToken();
    setupWebSocketAuthInterceptor();
});

// Ajouter un écouteur pour détecter quand Echo est connecté
document.addEventListener("echo:connected", () => {
    console.log("🔄 Echo connecté, vérification du token CSRF...");
    checkEchoCSRFToken();
});

// Initialiser les stores
const moderatorStore = useModeratorStore();
const {
    connectionStatus,
    connectionState,
    isHealthy,
    forceReconnect,
    checkConnection,
} = useWebSocketHealth();

// Props
const props = defineProps({
    auth: {
        type: Object,
        default: () => ({}), // Valeur par défaut vide pour éviter les erreurs
    },
    user: {
        type: Object,
        default: () => ({}), // Valeur par défaut vide pour éviter les erreurs
    },
});

// État local du composant
const activeTab = ref("assigned");
const selectedClient = ref(null);
const newMessage = ref("");
const chatContainer = ref(null);
const chatSection = ref(null);
const notifications = ref([]);
const showNotifications = ref(false);
const fileInput = ref(null);
const selectedFile = ref(null);
const previewUrl = ref(null);
const showPreview = ref(false);
const previewImage = ref(null);
const showFullInfoModal = ref(false);
const showActionModal = ref(false);
const showReportModalFlag = ref(false);
const selectedProfileForActions = ref(null);
const selectedProfileForReport = ref(null);
// État pour les verrouillages
const profileLockTimeRemaining = ref(0);
const profileLockInterval = ref(null);

// État pour les réattributions
const showReassignmentAlert = ref(false);
const reassignmentCountdown = ref(0);
const reassignmentInterval = ref(null);

// Formater le temps depuis une date
const formatRelativeTime = (isoString) => {
    if (!isoString) return "N/A";

    const date = new Date(isoString);
    const now = new Date();
    const diffMs = now - date;

    if (diffMs < 60000) {
        // moins d'une minute
        return "à l'instant";
    } else if (diffMs < 3600000) {
        // moins d'une heure
        const minutes = Math.floor(diffMs / 60000);
        return `il y a ${minutes} minute${minutes > 1 ? "s" : ""}`;
    } else {
        return date.toLocaleTimeString();
    }
};

// Formater les secondes en min:sec
const formatSeconds = (seconds) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs < 10 ? "0" + secs : secs}`;
};

// Vérifier si le profil actuel est verrouillé
const isProfileLocked = computed(() => {
    if (!moderatorStore.currentAssignedProfile) return false;

    const profileId = moderatorStore.currentAssignedProfile.id;
    return moderatorStore.lockedProfiles[profileId] !== undefined;
});

// Obtenir les informations de verrouillage du profil
const profileLockInfo = computed(() => {
    if (!moderatorStore.currentAssignedProfile) return {};

    const profileId = moderatorStore.currentAssignedProfile.id;
    return moderatorStore.lockedProfiles[profileId] || {};
});

// Vérifier si l'utilisateur peut demander un déverrouillage
const canRequestUnlock = computed(() => {
    if (!isProfileLocked.value) return false;

    // On peut demander un déverrouillage si on n'est pas le propriétaire du verrou
    return profileLockInfo.value.moderatorId !== moderatorStore.moderatorId;
});

// Vérifier s'il y a des conflits actifs
const hasActiveConflicts = computed(() => {
    return moderatorStore.assignmentConflicts.length > 0;
});

// Obtenir le dernier conflit
const latestConflict = computed(() => {
    if (!hasActiveConflicts.value) return {};

    return moderatorStore.assignmentConflicts[
        moderatorStore.assignmentConflicts.length - 1
    ];
});

// Mettre à jour le temps restant de verrouillage
const updateLockTimeRemaining = () => {
    if (!isProfileLocked.value) return;

    const expiresAt = new Date(profileLockInfo.value.expiresAt);
    const now = new Date();
    const diffSeconds = Math.floor((expiresAt - now) / 1000);

    profileLockTimeRemaining.value = Math.max(0, diffSeconds);

    if (profileLockTimeRemaining.value <= 0) {
        clearInterval(profileLockInterval.value);
        profileLockInterval.value = null;
    }
};

// Quitter la file d'attente
const leaveQueue = async () => {
    try {
        const response = await axios.post("/moderateur/queue/leave");

        if (response.data.status === "success") {
            moderatorStore.queueInfo.inQueue = false;
            moderatorStore.queueInfo.position = null;
            moderatorStore.queueInfo.estimatedWaitTime = null;
            moderatorStore.queueInfo.queuedAt = null;
        }
    } catch (error) {
        console.error("Erreur lors de la sortie de la file d'attente:", error);
    }
};

// Demander le déverrouillage d'un profil
const requestUnlock = async (profileId) => {
    await moderatorStore.requestProfileUnlock(profileId);
};

// Accusé de réception d'un conflit
const acknowledgeConflict = (conflictId) => {
    moderatorStore.assignmentConflicts =
        moderatorStore.assignmentConflicts.filter(
            (conflict) => conflict.id !== conflictId
        );
};

// Prévenir la réattribution en signalant l'activité
const preventReassignment = async () => {
    try {
        await axios.post("/moderateur/activity/signal", {
            profile_id: moderatorStore.currentAssignedProfile?.id,
        });

        showReassignmentAlert.value = false;
        clearInterval(reassignmentInterval.value);
        reassignmentInterval.value = null;
    } catch (error) {
        console.error("Erreur lors de la signalisation d'activité:", error);
    }
};

// Démarrer l'alerte de réattribution
const startReassignmentAlert = () => {
    if (reassignmentInterval.value) return;

    showReassignmentAlert.value = true;
    reassignmentCountdown.value = 60; // 60 secondes avant réattribution

    reassignmentInterval.value = setInterval(() => {
        reassignmentCountdown.value--;

        if (reassignmentCountdown.value <= 0) {
            clearInterval(reassignmentInterval.value);
            reassignmentInterval.value = null;
            showReassignmentAlert.value = false;
        }
    }, 1000);
};

// Messages pour la conversation actuelle
const currentChatMessages = computed(() => {
    if (!selectedClient.value) return [];
    return moderatorStore.getMessagesForClient(selectedClient.value.id) || [];
});

// Clients triés par date de dernier message
const sortedAssignedClients = computed(() => {
    return moderatorStore.getSortedAssignedClients();
});

// Données du modérateur
const currentAssignedProfile = computed(
    () => moderatorStore.currentAssignedProfile
);
const assignedClient = computed(() => moderatorStore.assignedClients);
const availableClients = computed(() => moderatorStore.availableClients);
const loading = computed(() => moderatorStore.loading);
const isLoadingMore = computed(() => moderatorStore.isLoadingMore);
const hasMoreMessages = computed(() => {
    if (!selectedClient.value) return false;
    return moderatorStore.hasMoreMessages(selectedClient.value.id);
});

// Étiquette pour l'état de connexion
const connectionStateLabel = computed(() => {
    switch (connectionState.value) {
        case "healthy":
            return "Connecté";
        case "degraded":
            return "Connexion instable";
        case "connecting":
            return "Connexion en cours...";
        case "disconnected":
            return "Déconnecté";
        default:
            return "Inconnu";
    }
});

// Erreurs WebSocket
const webSocketErrors = computed(() => moderatorStore.errors.websocket);

// Vérifier si le client est en train de taper
const isTyping = computed(() => {
    if (!selectedClient.value) return false;

    const typingKey = `${currentAssignedProfile.value?.id}-${selectedClient.value.id}`;
    return moderatorStore.typingStatus[typingKey]?.isTyping || false;
});

// Vérifier si le profil est partagé
const isProfileShared = computed(() => {
    return moderatorStore.sharedProfiles.includes(
        currentAssignedProfile.value?.id
    );
});

// Obtenir les activités des autres modérateurs sur ce profil
const otherModeratorsActive = computed(() => {
    if (!currentAssignedProfile.value) return [];

    const profileId = currentAssignedProfile.value.id;
    return moderatorStore.activeModeratorsByProfile[profileId] || [];
});

// Demander un délai avant changement de profil
function requestDelay() {
    if (currentAssignedProfile.value) {
        moderatorStore.requestProfileChangeDelay(
            currentAssignedProfile.value.id,
            5
        );
    }
}

// Fonction pour initialiser ou vérifier la connexion WebSocket
async function ensureWebSocketConnection() {
    try {
        // Vérifier l'état des données utilisateur pour le debug
        console.log("🔍 État des données utilisateur:");
        console.log(
            "  → window.Laravel.user:",
            window.Laravel?.user || "Non disponible"
        );
        console.log(
            "  → meta[user-id]:",
            document
                .querySelector('meta[name="user-id"]')
                ?.getAttribute("content") || "Non disponible"
        );
        console.log("  → props.auth:", props.auth || "Non disponible");

        // Synchroniser immédiatement les données utilisateur si disponibles dans props
        if (
            props.auth &&
            props.auth.user &&
            (!window.Laravel || !window.Laravel.user)
        ) {
            console.log(
                "🔄 Synchronisation immédiate des données utilisateur depuis props..."
            );
            if (!window.Laravel) window.Laravel = {};
            window.Laravel.user = {
                id: props.auth.user.id,
                type: props.auth.user.type,
                name: props.auth.user.name,
            };

            // Définir également les variables globales
            window.clientId = parseInt(props.auth.user.id);
            window.userType = props.auth.user.type;
        }

        // Attendre un court instant pour que les scripts soient chargés
        await new Promise((resolve) => setTimeout(resolve, 100));

        // Vérifier si nous avons besoin de rafraîchir le token CSRF
        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content");
        if (!csrfToken) {
            console.warn(
                "⚠️ Token CSRF manquant, tentative de rafraîchissement..."
            );
            try {
                await axios.get("/sanctum/csrf-cookie");
                console.log("✅ Token CSRF rafraîchi");
            } catch (error) {
                console.error(
                    "❌ Échec du rafraîchissement du token CSRF:",
                    error
                );
            }
        }

        if (!window.Echo) {
            console.log(
                "🔄 Initialisation des services WebSocket depuis Moderator.vue..."
            );
            try {
                // Initialiser avec un timeout plus court pour une meilleure UX
                const initPromise = initializeWebSocketServices();
                const timeoutPromise = new Promise((_, reject) =>
                    setTimeout(() => reject(new Error("Timeout local")), 3000)
                );

                await Promise.race([initPromise, timeoutPromise]);
                console.log(
                    "✅ Services WebSocket initialisés avec succès depuis Moderator.vue"
                );
            } catch (error) {
                if (error.message === "Timeout local") {
                    console.warn(
                        "⚠️ Timeout local atteint, continuons avec fonctionnalités limitées"
                    );
                } else {
                    console.warn(
                        "⚠️ Initialisation des WebSockets échouée:",
                        error
                    );
                }

                // Continuer avec l'initialisation du store même si Echo a échoué
            }
        }

        // Vérifier si le moderator store est initialisé - toujours tenter ceci
        if (!moderatorStore.initialized) {
            console.log(
                "🔄 Initialisation du store modérateur depuis Moderator.vue..."
            );
            try {
                await moderatorStore.initialize();
                console.log(
                    "✅ Store modérateur initialisé avec succès depuis Moderator.vue"
                );
            } catch (error) {
                console.warn(
                    "⚠️ Initialisation du store modérateur échouée:",
                    error
                );
            }
        }

        // Considérer la connexion comme prête même si Echo n'est pas disponible
        return (
            webSocketManager.isConnected() ||
            (window.Echo && window.echoReady) ||
            !!window.Laravel?.user
        );
    } catch (error) {
        console.error(
            "❌ Erreur lors de l'initialisation WebSocket depuis Moderator.vue:",
            error
        );
        // Même en cas d'erreur, essayer de continuer avec les fonctionnalités de base
        return !!window.Laravel?.user;
    }
}

// Version améliorée avec sendBeacon
function updateOfflineStatus() {
    const data = new FormData();
    data.append("is_online", "false");

    // Utiliser sendBeacon qui est plus fiable pour les requêtes lors de la fermeture du navigateur
    navigator.sendBeacon("/moderateur/heartbeat", data);
    console.log("✅ Statut mis à jour: hors ligne (via sendBeacon)");
}

// Dans checkWebSocketConnection()
function checkWebSocketConnection() {
    if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
        const pusherState = window.Echo.connector.pusher.connection.state;

        // Mettre à jour l'état de connexion directement sans utiliser updateConnectionState
        if (pusherState === "connected") {
            connectionState.value = "healthy";
        } else if (pusherState === "connecting") {
            connectionState.value = "connecting";
        } else if (pusherState === "unavailable" || pusherState === "failed") {
            connectionState.value = "degraded";
        } else if (pusherState === "disconnected") {
            connectionState.value = "disconnected";
        }

        console.log(
            "État WebSocket vérifié:",
            pusherState,
            "→",
            connectionState.value
        );
        return pusherState;
    }

    // Si Echo n'est pas disponible
    connectionState.value = "disconnected";
    return "not_available";
}

// Fonction pour vérifier et corriger le token CSRF dans Echo
function checkEchoCSRFToken() {
    if (
        !window.Echo ||
        !window.Echo.connector ||
        !window.Echo.connector.options
    ) {
        return false;
    }

    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");
    if (!csrfToken) {
        return false;
    }

    // Vérifier si le token CSRF dans Echo correspond au token actuel
    const echoToken =
        window.Echo.connector.options.auth?.headers?.["X-CSRF-TOKEN"];
    if (echoToken !== csrfToken) {
        console.log("🔄 Mise à jour du token CSRF dans Echo...");
        window.Echo.connector.options.auth.headers["X-CSRF-TOKEN"] = csrfToken;

        // Mettre également à jour dans Pusher si disponible
        if (
            window.Echo.connector.pusher &&
            window.Echo.connector.pusher.config
        ) {
            window.Echo.connector.pusher.config.auth.headers["X-CSRF-TOKEN"] =
                csrfToken;
        }

        return true;
    }

    return false;
}
// Fonction pour rafraîchir le token CSRF
async function refreshCSRFToken() {
    try {
        console.log("🔄 Rafraîchissement du token CSRF...");

        // Appeler l'endpoint sanctum/csrf-cookie
        await axios.get("/sanctum/csrf-cookie", {
            withCredentials: true,
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
        });

        // Attendre un peu pour s'assurer que le cookie est bien défini
        await new Promise((r) => setTimeout(r, 300));

        // Récupérer le nouveau token depuis les meta tags
        const newToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute("content");

        if (newToken) {
            // Mettre à jour le token dans les en-têtes Axios
            axios.defaults.headers.common["X-CSRF-TOKEN"] = newToken;

            // Mettre à jour le token dans Echo si disponible
            if (
                window.Echo &&
                window.Echo.connector &&
                window.Echo.connector.options &&
                window.Echo.connector.options.auth
            ) {
                window.Echo.connector.options.auth.headers["X-CSRF-TOKEN"] =
                    newToken;

                // Forcer la reconnexion de Pusher pour utiliser le nouveau token
                if (
                    window.Echo.connector.pusher &&
                    window.Echo.connector.pusher.connection
                ) {
                    // Mettre à jour le token dans les options de connexion Pusher
                    window.Echo.connector.pusher.config.auth.headers[
                        "X-CSRF-TOKEN"
                    ] = newToken;
                }
            }

            console.log("✅ Token CSRF rafraîchi:", newToken);
            return true;
        } else {
            console.warn(
                "⚠️ Impossible de rafraîchir le token CSRF: token non trouvé"
            );
            return false;
        }
    } catch (error) {
        console.error(
            "❌ Erreur lors du rafraîchissement du token CSRF:",
            error
        );
        return false;
    }
}

// Fonction pour configurer l'intercepteur d'authentification WebSocket
function setupWebSocketAuthInterceptor() {
    if (
        !window.Echo ||
        !window.Echo.connector ||
        !window.Echo.connector.pusher
    ) {
        console.warn(
            "⚠️ Echo ou Pusher non disponible pour configurer l'intercepteur d'authentification"
        );
        return;
    }

    // Remplacer la méthode d'authentification par défaut de Pusher
    const originalAuthorizer = window.Echo.connector.pusher.config.authorizer;

    if (!originalAuthorizer) {
        console.warn("⚠️ Authorizer Pusher non disponible");
        return;
    }

    // Remplacer l'authorizer par notre version personnalisée
    window.Echo.connector.pusher.config.authorizer = function (channel) {
        return {
            authorize: async function (socketId, callback) {
                try {
                    // Rafraîchir le token CSRF avant chaque tentative d'authentification
                    await refreshCSRFToken();

                    // Récupérer le token CSRF actuel
                    const csrfToken = document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute("content");

                    // Mettre à jour les en-têtes de l'authentification
                    if (window.Echo.connector.options.auth && csrfToken) {
                        window.Echo.connector.options.auth.headers[
                            "X-CSRF-TOKEN"
                        ] = csrfToken;
                    }

                    // Utiliser l'authorizer original avec les en-têtes mis à jour
                    const authorizerInstance = originalAuthorizer(channel);
                    authorizerInstance.authorize(
                        socketId,
                        function (err, data) {
                            if (err && err.status === 419) {
                                console.warn(
                                    "⚠️ Erreur CSRF 419 malgré le rafraîchissement du token"
                                );
                            }
                            callback(err, data);
                        }
                    );
                } catch (error) {
                    console.error(
                        "❌ Erreur lors de l'autorisation du canal:",
                        error
                    );
                    callback(error, null);
                }
            },
        };
    };

    console.log("✅ Intercepteur d'authentification WebSocket configuré");
}

// Fonction pour détecter et gérer les erreurs 419
function setupCSRFErrorHandler() {
    if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
        const originalAuthorizer =
            window.Echo.connector.pusher.config.authorizer;

        if (originalAuthorizer) {
            window.Echo.connector.pusher.config.authorizer = function (
                channel
            ) {
                return {
                    authorize: async (socketId, callback) => {
                        try {
                            // Utiliser l'authorizer original
                            const originalAuth = originalAuthorizer(channel);

                            originalAuth.authorize(
                                socketId,
                                async (error, data) => {
                                    if (
                                        error &&
                                        (error.status === 419 ||
                                            error.code === 4019)
                                    ) {
                                        console.warn(
                                            "⚠️ Erreur CSRF 419 détectée, rafraîchissement du token..."
                                        );

                                        try {
                                            // Rafraîchir le token CSRF
                                            await refreshCSRFToken();

                                            // Réessayer l'autorisation avec le nouveau token
                                            const retryAuth =
                                                originalAuthorizer(channel);
                                            retryAuth.authorize(
                                                socketId,
                                                (retryError, retryData) => {
                                                    callback(
                                                        retryError,
                                                        retryData
                                                    );
                                                }
                                            );
                                        } catch (refreshError) {
                                            console.error(
                                                "❌ Échec du rafraîchissement du token CSRF:",
                                                refreshError
                                            );
                                            callback(error, null);
                                        }
                                    } else {
                                        callback(error, data);
                                    }
                                }
                            );
                        } catch (err) {
                            console.error("❌ Erreur dans l'authorizer:", err);
                            callback(err, null);
                        }
                    },
                };
            };
        }
    }
}

// Variables pour le nettoyage
let csrfRefreshInterval;
let axiosInterceptorId;
let heartbeatInterval; // Nouvelle variable pour le heartbeat
let dataRefreshInterval; // Intervalle pour le rafraîchissement périodique des données
// Configurer l'intercepteur Axios pour les erreurs CSRF
axiosInterceptorId = axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        // Si l'erreur est une erreur CSRF (419)
        if (error.response && error.response.status === 419) {
            console.warn(
                "⚠️ Erreur CSRF 419 détectée dans la réponse Axios, rafraîchissement du token..."
            );

            try {
                // Rafraîchir le token CSRF
                await refreshCSRFToken();

                // Récupérer la requête originale et réessayer
                const originalRequest = error.config;
                originalRequest._retry = true; // Marquer comme réessayée pour éviter les boucles infinies

                // Mettre à jour le token dans la requête
                originalRequest.headers["X-CSRF-TOKEN"] = document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content");

                return axios(originalRequest);
            } catch (refreshError) {
                console.error(
                    "❌ Échec du rafraîchissement du token CSRF:",
                    refreshError
                );
                return Promise.reject(error);
            }
        }

        return Promise.reject(error);
    }
);

// Initialisation
onMounted(async () => {
    try {
        console.log("🚀 Initialisation du composant Moderator...");

        // S'assurer que la connexion WebSocket est établie
        const connected = await ensureWebSocketConnection();
        const connectionCheckInterval = setInterval(
            checkWebSocketConnection,
            5000
        );

        if (connected) {
            console.log("✅ Connexion WebSocket établie avec succès");

            // Configurer l'intercepteur d'authentification WebSocket
            setupWebSocketAuthInterceptor();
        } else {
            console.warn(
                "⚠️ Connexion WebSocket non établie, fonctionnalités limitées"
            );
        }

        // Initialiser le store du modérateur
        await moderatorStore.initialize();

        // Envoyer un heartbeat initial pour mettre à jour le statut en ligne
        await moderatorStore.sendHeartbeat();

        // Configurer les écouteurs spécifiques au modérateur
        if (currentAssignedProfile.value) {
            moderatorStore.setupProfileListeners(
                currentAssignedProfile.value.id
            );
        }

        // Vérifier l'état de la connexion WebSocket
        checkWebSocketConnection();

        // Configurer un intervalle pour rafraîchir le token CSRF périodiquement
        csrfRefreshInterval = setInterval(refreshCSRFToken, 30 * 60 * 1000); // 30 minutes

        // Configurer l'intervalle de heartbeat pour maintenir le statut en ligne
        heartbeatInterval = setInterval(() => {
            moderatorStore.sendHeartbeat();
        }, 2 * 60 * 1000); // 2 minutes

        // Configurer l'intervalle de rafraîchissement périodique des données
        dataRefreshInterval = setInterval(() => {
            if (moderatorStore.currentAssignedProfile) {
                console.log('🔄 Rafraîchissement périodique des données...');
                moderatorStore.forceProfileRefresh();
            }
        }, 60 * 1000); // Vérification toutes les 60 secondes
    } catch (error) {
        console.error(
            "❌ Erreur lors de l'initialisation du composant Moderator:",
            error
        );
    }

    // Configurer l'écouteur d'événement pour la fermeture du navigateur
    window.addEventListener("beforeunload", updateOfflineStatus);
});

// Nettoyage lors du démontage
onUnmounted(() => {
    console.log("🧹 Nettoyage du composant Moderator...");
    clearInterval(connectionCheckInterval);
    // Nettoyer l'intervalle de rafraîchissement CSRF
    if (csrfRefreshInterval) {
        clearInterval(csrfRefreshInterval);
    }

    // Nettoyer l'intervalle de heartbeat
    if (heartbeatInterval) {
        clearInterval(heartbeatInterval);
    }

    // Nettoyer l'intervalle de rafraîchissement des données
    if (dataRefreshInterval) {
        clearInterval(dataRefreshInterval);
    }

    // Supprimer l'intercepteur Axios
    if (axiosInterceptorId !== undefined) {
        axios.interceptors.response.eject(axiosInterceptorId);
    }

    // Supprimer l'écouteur d'événement beforeunload
    window.removeEventListener("beforeunload", updateOfflineStatus);

    // Nettoyer le store
    moderatorStore.cleanup();
});

// Sélectionner un client
async function selectClient(client) {
    selectedClient.value = client;

    try {
        // Charger les messages
        await moderatorStore.loadMessages(client.id);

        // Marquer les notifications comme lues
        const notification = notifications.value.find(
            (n) => n.clientId === client.id && !n.read
        );
        if (notification) {
            markNotificationAsRead(notification.id);
        }

        // Faire défiler jusqu'à la section de chat sur mobile
        nextTick(() => {
            if (window.innerWidth < 1024) {
                chatSection.value?.scrollIntoView({ behavior: "smooth" });
            }
            // Faire défiler le conteneur de messages vers le bas
            if (chatContainer.value) {
                chatContainer.value.scrollTop =
                    chatContainer.value.scrollHeight;
            }
        });
    } catch (error) {
        console.error("Erreur lors de la sélection du client:", error);
    }
}

// Démarrer une conversation
async function startConversation(client) {
    try {
        loading.value = true;

        await moderatorStore.startConversation(client.id);

        // Sélectionner ce client
        const updatedClient = moderatorStore.getClientById(client.id);
        if (updatedClient) {
            selectedClient.value = updatedClient;
        }

        // Changer l'onglet
        activeTab.value = "assigned";

        // Faire défiler jusqu'à la section de chat sur mobile
        nextTick(() => {
            if (window.innerWidth < 1024) {
                chatSection.value?.scrollIntoView({ behavior: "smooth" });
            }
            // Faire défiler le conteneur de messages vers le bas
            if (chatContainer.value) {
                chatContainer.value.scrollTop =
                    chatContainer.value.scrollHeight;
            }
        });
    } catch (error) {
        console.error("Erreur lors du démarrage de la conversation:", error);
    } finally {
        loading.value = false;
    }
}

// Gérer le défilement pour charger plus de messages
function handleScroll(event) {
    const container = event.target;
    if (
        container.scrollTop <= 100 &&
        selectedClient.value &&
        !isLoadingMore.value
    ) {
        moderatorStore.loadMoreMessages(selectedClient.value.id);
    }
}

// Envoyer un message
async function sendMessage() {
    if (
        (!newMessage.value.trim() && !selectedFile.value) ||
        !currentAssignedProfile.value ||
        !selectedClient.value
    ) {
        return;
    }

    const messageContent = newMessage.value.trim();

    try {
        // Effacer le champ avant d'envoyer pour éviter les doublons visuels
        newMessage.value = "";

        // Envoyer le message
        await moderatorStore.sendMessage({
            clientId: selectedClient.value.id,
            profileId: currentAssignedProfile.value.id,
            content: messageContent,
            file: selectedFile.value,
        });

        // Mettre à jour l'activité de dernière réponse
        if (currentAssignedProfile.value && selectedClient.value) {
            // Cette ligne est nouvelle
            moderatorStore.updateLastMessageActivity(
                currentAssignedProfile.value.id,
                selectedClient.value.id
            );
        }

        // Réinitialiser le fichier sélectionné
        removeSelectedFile();

        // Faire défiler vers le bas
        nextTick(() => {
            if (chatContainer.value) {
                chatContainer.value.scrollTop =
                    chatContainer.value.scrollHeight;
            }
        });
    } catch (error) {
        console.error("Erreur lors de l'envoi du message:", error);
    }
}

// Gestion des fichiers
function handleFileUpload(event) {
    const file = event.target.files[0];
    if (file) {
        // Vérifier le type de fichier
        if (!file.type.startsWith("image/")) {
            alert("Seules les images sont autorisées");
            return;
        }

        // Vérifier la taille du fichier (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            alert("La taille du fichier ne doit pas dépasser 5MB");
            return;
        }

        selectedFile.value = file;
        previewUrl.value = URL.createObjectURL(file);
    }
}

function removeSelectedFile() {
    selectedFile.value = null;
    previewUrl.value = null;
    if (fileInput.value) {
        fileInput.value.value = "";
    }
}

function showImagePreview(attachment) {
    previewImage.value = attachment;
    showPreview.value = true;
}

function closeImagePreview() {
    showPreview.value = false;
    previewImage.value = null;
}

// Gestion des photos de profil
async function handleProfilePhotoSelected(photo) {
    try {
        if (!currentAssignedProfile.value || !selectedClient.value) {
            console.error("Profil ou client non sélectionné");
            return;
        }

        await moderatorStore.sendProfilePhoto({
            profileId: currentAssignedProfile.value.id,
            clientId: selectedClient.value.id,
            photoId: photo.id,
            photoUrl: photo.url,
        });

        // Faire défiler vers le bas
        nextTick(() => {
            if (chatContainer.value) {
                chatContainer.value.scrollTop =
                    chatContainer.value.scrollHeight;
            }
        });
    } catch (error) {
        console.error("Erreur lors de l'envoi de la photo:", error);
    }
}

// Gestion des notifications
function markNotificationAsRead(notificationId) {
    const index = notifications.value.findIndex((n) => n.id === notificationId);
    if (index !== -1) {
        notifications.value[index].read = true;
    }
}

function goToConversation(clientId) {
    const client = assignedClient.value.find((c) => c.id === clientId);
    if (client) {
        selectClient(client);
        activeTab.value = "assigned";
    }
}

// Fonctions pour les modals
function openFullInfoModal() {
    showFullInfoModal.value = true;
}

function closeActionModal() {
    showActionModal.value = false;
    selectedProfileForActions.value = null;
}

function closeReportModal() {
    showReportModalFlag.value = false;
    selectedProfileForReport.value = null;
}

function startChat(profile) {
    // Implémenter la logique pour démarrer un chat
    closeActionModal();
}

function handleReported() {
    // Implémenter la logique après un rapport
    closeReportModal();
}

// Utilitaires
function formatTime(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
}

// Charger les clients disponibles
function loadAvailableClients() {
    moderatorStore.loadAvailableClients();
}

// Observer les changements de profil attribué
watch(
    () => moderatorStore.currentAssignedProfile,
    async (newProfile, oldProfile) => {
        if (newProfile && newProfile.id !== oldProfile?.id) {
            // Configurer les écouteurs pour le nouveau profil
            moderatorStore.setupProfileListeners(newProfile.id);

            // Réinitialiser le client sélectionné pour éviter l'affichage d'une conversation d'un autre profil
            selectedClient.value = moderatorStore.selectedClient;

            // S'assurer que les messages affichés correspondent au client sélectionné
            if (selectedClient.value) {
                // Faire défiler le conteneur de messages vers le bas après le rendu
                nextTick(() => {
                    if (chatContainer.value) {
                        chatContainer.value.scrollTop =
                            chatContainer.value.scrollHeight;
                    }
                });
            }

            // Ajouter une notification système pour informer du changement de profil
            const notification = {
                id: Date.now(),
                message: `Vous utilisez maintenant le profil ${newProfile.name}`,
                clientId: null,
                clientName: "Système",
                timestamp: new Date(),
                read: false,
            };

            notifications.value.unshift(notification);
        }
    }
);

// Observer les changements du client sélectionné dans le store
watch(
    () => moderatorStore.selectedClient,
    (newSelectedClient) => {
        if (newSelectedClient) {
            // Synchroniser le client sélectionné local avec celui du store
            selectedClient.value = newSelectedClient;

            // Faire défiler le conteneur de messages vers le bas après le rendu
            nextTick(() => {
                if (chatContainer.value) {
                    chatContainer.value.scrollTop =
                        chatContainer.value.scrollHeight;
                }

                // Sur mobile, faire défiler jusqu'à la section de chat
                if (window.innerWidth < 1024) {
                    chatSection.value?.scrollIntoView({ behavior: "smooth" });
                }
            });
        }
    }
);

// Surveiller l'état de la connexion WebSocket
watch(connectionState, (newState) => {
    console.log(`État WebSocket changé: ${newState}`);

    if (newState === "disconnected") {
        // Afficher une notification
        const notification = {
            id: Date.now(),
            message:
                "Connexion WebSocket perdue. Les messages en temps réel ne sont plus disponibles.",
            clientId: null,
            clientName: "Système",
            timestamp: new Date(),
            read: false,
        };

        notifications.value.unshift(notification);
    } else if (newState === "healthy") {
        // Ajouter une notification de reconnexion
        const notification = {
            id: Date.now(),
            message: "Connexion WebSocket rétablie.",
            clientId: null,
            clientName: "Système",
            timestamp: new Date(),
            read: false,
        };

        notifications.value.unshift(notification);
    }
});

// Surveiller l'input de message pour signaler l'activité de frappe
let typingTimeout = null;
let lastTypingTime = 0;

watch(newMessage, (value) => {
    if (
        value &&
        value.length > 0 &&
        currentAssignedProfile.value &&
        selectedClient.value
    ) {
        // Vérifier si assez de temps s'est écoulé depuis le dernier événement (500ms)
        const now = Date.now();
        if (now - lastTypingTime < 500) {
            return; // Trop tôt pour envoyer un autre événement
        }

        lastTypingTime = now;

        // Annuler le timeout précédent s'il existe
        if (typingTimeout) {
            clearTimeout(typingTimeout);
        }

        // Envoyer l'événement de frappe
        moderatorStore.recordTypingActivity(
            currentAssignedProfile.value.id,
            selectedClient.value.id
        );

        // Définir un nouveau timeout pour arrêter l'indicateur après 3 secondes d'inactivité
        typingTimeout = setTimeout(() => {
            // Envoyer un événement pour indiquer que l'utilisateur a arrêté de taper
            // Vous pourriez ajouter une méthode stopTypingActivity dans votre store
            typingTimeout = null;
        }, 3000);
    }
});

// Observer les changements de verrouillage
watch(
    () => moderatorStore.lockedProfiles,
    (newValue, oldValue) => {
        if (isProfileLocked.value && !profileLockInterval.value) {
            updateLockTimeRemaining();
            profileLockInterval.value = setInterval(
                updateLockTimeRemaining,
                1000
            );
        } else if (!isProfileLocked.value && profileLockInterval.value) {
            clearInterval(profileLockInterval.value);
            profileLockInterval.value = null;
        }
    },
    { deep: true }
);

// Nettoyage lors du démontage
onUnmounted(() => {
    if (profileLockInterval.value) {
        clearInterval(profileLockInterval.value);
    }

    if (reassignmentInterval.value) {
        clearInterval(reassignmentInterval.value);
    }
});

/**
 * Rafraîchir manuellement les données du profil
 */
async function refreshProfileData() {
    try {
        // Afficher un indicateur de chargement
        isLoading.value = true;
        
        // Forcer le rechargement des données
        await moderatorStore.forceProfileRefresh();
        
        // Afficher une notification de succès
        toast.success('Données rafraîchies avec succès');
    } catch (error) {
        console.error('❌ Erreur lors du rafraîchissement des données:', error);
        toast.error('Erreur lors du rafraîchissement des données');
    } finally {
        // Masquer l'indicateur de chargement
        isLoading.value = false;
    }
}
</script>

<style scoped>
.client-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1),
        0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.chat-container {
    height: 400px;
    overflow-y: auto;
}

.message-in {
    background-color: #f3f4f6;
    border-radius: 18px 18px 18px 4px;
}

.message-out {
    background-color: #ec4899;
    color: white;
    border-radius: 18px 18px 4px 18px;
}

.online-dot {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 12px;
    height: 12px;
    background-color: #10b981;
    border-radius: 50%;
    border: 2px solid white;
}

.message-in img,
.message-out img {
    max-width: 200px;
    height: auto;
    border-radius: 8px;
    margin-top: 4px;
}

.message-in img:hover,
.message-out img:hover {
    opacity: 0.9;
    cursor: zoom-in;
}

/* Ajustement des styles pour le mobile */
@media (max-width: 1024px) {
    .chat-container {
        height: calc(100vh - 20rem);
        /* Augmenté pour tenir compte du menu mobile */
    }
}

/* Ajout d'un style pour le conteneur principal sur mobile */
@media (max-width: 1024px) {
    .main-container {
        padding-bottom: env(safe-area-inset-bottom, 5rem);
    }
}

/* Styles pour le modal mobile */
.modal-mobile-enter-active,
.modal-mobile-leave-active {
    transition: transform 0.3s ease-out;
}

.modal-mobile-enter-from,
.modal-mobile-leave-to {
    transform: translateY(100%);
}

/* Style pour fixer la zone de saisie en bas */
.chat-input {
    position: sticky;
    bottom: 0;
    background: white;
    z-index: 10;
}

/* Support pour le safe area sur iOS */
@supports (padding: max(0px)) {
    .chat-input {
        padding-bottom: max(1rem, env(safe-area-inset-bottom));
    }
}

/* Slide transition for mobile drawer */
.slide-enter-active,
.slide-leave-active {
    transition: transform 0.3s ease-out;
}

.slide-enter-from,
.slide-leave-to {
    transform: translateY(-100%);
}

/* Animation pour le compte à rebours */
@keyframes pulse {
    0% {
        opacity: 1;
    }
    50% {
        opacity: 0.6;
    }
    100% {
        opacity: 1;
    }
}

.animate-pulse {
    animation: pulse 1s ease-in-out infinite;
}
</style>
