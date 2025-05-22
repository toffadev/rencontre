<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal privé pour les clients (accessible uniquement par le client lui-même)
Broadcast::channel('client.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id && $user->type === 'client';
});

// Canal privé pour les profils (accessible par les modérateurs assignés au profil)
Broadcast::channel('profile.{profileId}', function ($user, $profileId) {
    // Si l'utilisateur est client, vérifier s'il a des conversations avec ce profil
    if ($user->type === 'client') {
        return \App\Models\Message::where('client_id', $user->id)
            ->where('profile_id', $profileId)
            ->exists();
    }

    // Si l'utilisateur est modérateur, vérifier s'il est assigné à ce profil
    if ($user->type === 'moderator') {
        return \App\Models\ModeratorProfileAssignment::where('user_id', $user->id)
            ->where('profile_id', $profileId)
            ->where('is_active', true)
            ->exists();
    }

    return false;
});

// Canal privé pour les modérateurs (accessible uniquement par le modérateur lui-même)
Broadcast::channel('moderator.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id && $user->type === 'moderator';
});

Broadcast::channel('test-channel', function () {
    return true;
});
