<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\Message;
use Illuminate\Http\Request;

class ProfileDiscussionController extends Controller
{
    public function checkActiveDiscussion($profileId)
    {
        try {
            // Chercher le dernier message échangé avec ce profil
            $lastMessage = Message::where('profile_id', $profileId)
                ->latest()
                ->first();

            // Si on trouve un message, on retourne l'ID du modérateur
            if ($lastMessage) {
                return response()->json([
                    'moderator_id' => $lastMessage->moderator_id
                ]);
            }

            // Si pas de message, pas de modérateur
            return response()->json([
                'moderator_id' => null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'moderator_id' => null,
                'error' => 'Erreur lors de la vérification de la discussion'
            ], 500);
        }
    }
}
