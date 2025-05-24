<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Profile;
use App\Events\MessageSent;
use App\Events\NewClientMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\PointService;
use Exception;

class MessageController extends Controller
{
    protected $pointService;

    public function __construct(PointService $pointService)
    {
        $this->pointService = $pointService;
    }

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

        // Log pour le débogage
        Log::info("[DEBUG] Chargement des messages", [
            'client_id' => $clientId,
            'profile_id' => $profileId
        ]);

        // Récupérer les messages entre ce client et ce profil
        $messages = Message::where('profile_id', $profileId)
            ->where('client_id', $clientId)
            ->orderBy('created_at')
            ->get();

        Log::info("[DEBUG] Messages trouvés", [
            'count' => $messages->count()
        ]);

        $formattedMessages = $messages->map(function ($message) {
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
            'messages' => $formattedMessages
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
            'profile_id' => 'required|exists:users,id',
            'content' => 'required|string|max:1000'
        ]);

        $user = Auth::user();
        $clientId = $user->id;
        $profileId = $request->profile_id;

        try {
            // Créer le nouveau message
            $message = Message::create([
                'client_id' => $clientId,
                'profile_id' => $profileId,
                'moderator_id' => null, // Pas de modérateur car vient du client
                'content' => $request->content,
                'is_from_client' => true,
            ]);

            // Déduire les points après la création du message pour pouvoir le lier
            if (!$this->pointService->deductPoints($user, 'message_sent', PointService::POINTS_PER_MESSAGE, $message)) {
                // Si la déduction échoue, supprimer le message et retourner une erreur
                $message->delete();
                return response()->json([
                    'error' => 'Points insuffisants pour envoyer un message',
                    'remaining_points' => $user->points
                ], 403);
            }

            // Log pour le débogage
            Log::info("[DEBUG] Message client envoyé", [
                'client_id' => $clientId,
                'profile_id' => $profileId,
                'message_id' => $message->id,
                'content' => $message->content,
                'points_remaining' => $user->points
            ]);

            // Diffuser l'événement de message
            broadcast(new MessageSent($message))->toOthers();

            // Déclencher l'événement NewClientMessage pour la gestion des attributions
            event(new NewClientMessage($message));

            return response()->json([
                'success' => true,
                'message' => 'Message envoyé avec succès',
                'messageData' => [
                    'id' => $message->id,
                    'content' => $message->content,
                    'isOutgoing' => true,
                    'time' => $message->created_at->format('H:i'),
                    'date' => $message->created_at->format('Y-m-d'),
                ],
                'remaining_points' => $user->points
            ]);
        } catch (Exception $e) {
            Log::error('Erreur lors de l\'envoi du message:', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'profile_id' => $profileId
            ]);

            return response()->json([
                'error' => 'Une erreur est survenue lors de l\'envoi du message',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
