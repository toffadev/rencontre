<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal privé pour les clients (accessible uniquement par le client lui-même)
Broadcast::channel('client.{id}', function ($user, $id) {
    $authorized = (int) $user->id === (int) $id && $user->isClient();

    \Illuminate\Support\Facades\Log::info("[CHANNELS] Vérification du canal client.{$id}", [
        'user_id' => $user->id,
        'request_id' => $id,
        'is_client' => $user->isClient() ? 'OUI' : 'NON',
        'authorized' => $authorized ? 'OUI' : 'NON'
    ]);

    return $authorized;
});

// Canal privé pour les profils (accessible par les modérateurs assignés au profil)
Broadcast::channel('profile.{profileId}', function ($user, $profileId) {
    // Si l'utilisateur est client, vérifier s'il a des conversations avec ce profil
    if ($user->isClient()) {
        return \App\Models\Message::where('client_id', $user->id)
            ->where('profile_id', $profileId)
            ->exists();
    }

    // Si l'utilisateur est modérateur, vérifier s'il est assigné à ce profil
    if ($user->isModerator()) {
        $hasAssignment = \App\Models\ModeratorProfileAssignment::where('user_id', $user->id)
            ->where('profile_id', $profileId)
            ->where('is_active', true)
            ->exists();

        \Illuminate\Support\Facades\Log::info("[CHANNELS] Vérification du canal profile.{$profileId}", [
            'moderator_id' => $user->id,
            'profile_id' => $profileId,
            'authorized' => $hasAssignment ? 'OUI' : 'NON'
        ]);

        return $hasAssignment;
    }

    // Autoriser les accès admin
    if ($user->isAdmin()) {
        return true;
    }

    return false;
});

// Canal privé pour les modérateurs (accessible uniquement par le modérateur lui-même)
Broadcast::channel('moderator.{id}', function ($user, $id) {
    $authorized = (int) $user->id === (int) $id && $user->isModerator();

    \Illuminate\Support\Facades\Log::info("[CHANNELS] Vérification du canal moderator.{$id}", [
        'user_id' => $user->id,
        'request_id' => $id,
        'is_moderator' => $user->isModerator() ? 'OUI' : 'NON',
        'authorized' => $authorized ? 'OUI' : 'NON'
    ]);

    return $authorized;
});

Broadcast::channel('test-channel', function () {
    return true;
});
