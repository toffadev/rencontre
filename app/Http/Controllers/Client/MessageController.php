<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Profile;
use App\Events\MessageSent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    /**
     * Récupère les messages entre le client authentifié et un profil spécifique
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMessages(Request $request)
    {
        $request->validate([
            'profile_id' => 'required|integer|exists:profiles,id',
        ]);

        $clientId = Auth::id();
        $profileId = $request->profile_id;

        // Récupérer les messages entre ce client et ce profil
        $messages = Message::where('profile_id', $profileId)
            ->where('client_id', $clientId)
            ->orderBy('created_at')
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'isOutgoing' => $message->is_from_client, // Pour le client, "outgoing" = is_from_client
                    'time' => $message->created_at->format('H:i'),
                    'date' => $message->created_at->format('Y-m-d'),
                ];
            });

        // Marquer les messages non lus comme lus (uniquement les messages des profils)
        Message::where('profile_id', $profileId)
            ->where('client_id', $clientId)
            ->where('is_from_client', false)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'messages' => $messages
        ]);
    }

    /**
     * Envoie un message du client à un profil
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'profile_id' => 'required|integer|exists:profiles,id',
            'content' => 'required|string|max:1000',
        ]);

        $clientId = Auth::id();
        $profileId = $request->profile_id;

        // Créer le nouveau message
        $message = Message::create([
            'client_id' => $clientId,
            'profile_id' => $profileId,
            'moderator_id' => null, // Pas de modérateur car vient du client
            'content' => $request->content,
            'is_from_client' => true,
        ]);

        // Diffuser l'événement de message
        event(new MessageSent($message));

        return response()->json([
            'success' => true,
            'message' => 'Message envoyé avec succès',
            'messageData' => [
                'id' => $message->id,
                'content' => $message->content,
                'isOutgoing' => true,
                'time' => $message->created_at->format('H:i'),
                'date' => $message->created_at->format('Y-m-d'),
            ]
        ]);
    }
}
