<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use App\Models\ModeratorProfileAssignment;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal privé pour les clients (accessible uniquement par le client lui-même)
Broadcast::channel('client.{id}', function ($user, $id) {
    $isClient = method_exists($user, 'isClient') ? $user->isClient() : ($user->type === 'client');
    $authorized = (int) $user->id === (int) $id && $isClient;

    Log::info("[CHANNELS] Vérification du canal client.{$id}", [
        'user_id' => $user->id,
        'user_type' => $user->type ?? 'unknown',
        'request_id' => $id,
        'is_client' => $isClient ? 'OUI' : 'NON',
        'authorized' => $authorized ? 'OUI' : 'NON',
        'user_attributes' => $user->getAttributes()
    ]);

    return $authorized;
});

// Canal privé pour les profils
Broadcast::channel('profile.{profileId}', function ($user, $profileId) {
    Log::info("[CHANNELS] Tentative d'accès au canal profile.{$profileId}", [
        'user_id' => $user->id,
        'user_type' => $user->type ?? 'unknown',
        'profile_id' => $profileId
    ]);

    // Si l'utilisateur est client, vérifier s'il a des conversations avec ce profil
    $isClient = method_exists($user, 'isClient') ? $user->isClient() : ($user->type === 'client');
    if ($isClient) {
        $hasConversation = \App\Models\Message::where('client_id', $user->id)
            ->where('profile_id', $profileId)
            ->exists();

        Log::info("[CHANNELS] Client - Vérification des conversations", [
            'client_id' => $user->id,
            'profile_id' => $profileId,
            'has_conversation' => $hasConversation ? 'OUI' : 'NON'
        ]);

        return $hasConversation;
    }

    // Si l'utilisateur est modérateur
    /* $isModerator = method_exists($user, 'isModerator') ? $user->isModerator() : ($user->type === 'moderateur');
    if ($isModerator) {
        // Vérifier d'abord l'assignation active
        $hasAssignment = \App\Models\ModeratorProfileAssignment::where('user_id', $user->id)
            ->where('profile_id', $profileId)
            ->where('is_active', true)
            ->exists();

        // Si le modérateur a une assignation active, autoriser l'accès
        if ($hasAssignment) {
            Log::info("[CHANNELS] Modérateur - Assignation active trouvée", [
                'moderator_id' => $user->id,
                'profile_id' => $profileId
            ]);
            return true;
        }

        // Sinon, vérifier si c'est un nouveau profil qui lui est attribué
        $isPrimaryProfile = \App\Models\Profile::where('id', $profileId)
            ->whereDoesntHave('moderatorAssignments', function ($query) {
                $query->where('is_active', true);
            })
            ->exists();

        Log::info("[CHANNELS] Modérateur - Vérification du profil primaire", [
            'moderator_id' => $user->id,
            'profile_id' => $profileId,
            'is_primary_profile' => $isPrimaryProfile ? 'OUI' : 'NON'
        ]);

        return $isPrimaryProfile;
    } */

    // Si l'utilisateur est modérateur
    $isModerator = method_exists($user, 'isModerator') ? $user->isModerator() : ($user->type === 'moderateur');
    if ($isModerator) {
        // Vérifier assignation active normale
        $hasAssignment = \App\Models\ModeratorProfileAssignment::where('user_id', $user->id)
            ->where('profile_id', $profileId)
            ->where('is_active', true)
            ->exists();

        if ($hasAssignment) {
            Log::info("[CHANNELS] Modérateur - Assignation active trouvée", [
                'moderator_id' => $user->id,
                'profile_id' => $profileId
            ]);
            return true;
        }

        // Nouveau: vérifier si le modérateur est en train d'être assigné à ce profil
        // (pour les attributions multiples)
        $isPendingAssignment = \App\Models\ModeratorProfileAssignment::where('user_id', $user->id)
            ->where('profile_id', $profileId)
            ->exists();

        if ($isPendingAssignment) {
            Log::info("[CHANNELS] Modérateur - Assignation en attente trouvée", [
                'moderator_id' => $user->id,
                'profile_id' => $profileId
            ]);
            return true;
        }

        return false;
    }


    // Autoriser les accès admin
    $isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : ($user->type === 'admin');
    if ($isAdmin) {
        Log::info("[CHANNELS] Admin - Accès autorisé", ['admin_id' => $user->id]);
        return true;
    }

    Log::warning("[CHANNELS] Accès refusé au canal profile.{$profileId}", [
        'user_id' => $user->id,
        'user_type' => $user->type ?? 'unknown'
    ]);

    return false;
});

// Canal privé pour les modérateurs
Broadcast::channel('moderator.{id}', function ($user, $id) {
    $isModerator = $user->type === 'moderateur'; // Simplification de la vérification
    $authorized = (int) $user->id === (int) $id && $isModerator;

    Log::info("[CHANNELS] Vérification du canal moderator.{$id}", [
        'user_id' => $user->id,
        'user_type' => $user->type ?? 'unknown',
        'request_id' => $id,
        'is_moderator' => $isModerator ? 'OUI' : 'NON',
        'authorized' => $authorized ? 'OUI' : 'NON'
    ]);

    return $authorized;
});

// Canal de test (toujours accessible pour debug)
Broadcast::channel('test-channel', function () {
    Log::info("[CHANNELS] Accès au canal de test autorisé");
    return true;
});
