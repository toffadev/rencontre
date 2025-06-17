<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Message;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RecoverMessagesController extends Controller
{
    /**
     * Récupère les messages manquants pour une conversation entre un profil et un client.
     * Cette méthode est utilisée pour synchroniser les messages qui auraient pu être manqués
     * en raison de problèmes de connexion WebSocket.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function recoverMessages(Request $request)
    {
        try {
            // Valider les paramètres de requête
            $validated = $request->validate([
                'client_id' => 'required|integer|exists:users,id',
                'profile_id' => 'required|integer|exists:profiles,id',
                'last_message_id' => 'nullable|integer'
            ]);

            $clientId = $validated['client_id'];
            $profileId = $validated['profile_id'];
            $lastMessageId = $validated['last_message_id'] ?? null;

            // Vérifier que le modérateur est autorisé à accéder à ce profil
            $moderator = Auth::user();
            $profile = Profile::findOrFail($profileId);

            if (!$moderator->canAccessProfile($profile)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'êtes pas autorisé à accéder à ce profil'
                ], 403);
            }

            // Vérifier que le client existe
            $client = User::findOrFail($clientId);

            // Vérifier que l'utilisateur est bien un client
            if (!$client->isClient()) {
                return response()->json([
                    'success' => false,
                    'message' => 'L\'utilisateur spécifié n\'est pas un client'
                ], 400);
            }

            // Construire la requête pour récupérer les messages
            $query = Message::where('client_id', $clientId)
                ->where('profile_id', $profileId)
                ->orderBy('created_at', 'asc');

            // Si un ID de dernier message est fourni, récupérer les messages plus récents
            if ($lastMessageId) {
                $query->where('id', '>', $lastMessageId);
            } else {
                // Sinon, récupérer les messages des dernières 24 heures
                $query->where('created_at', '>=', Carbon::now()->subDay());
            }

            // Récupérer les messages avec leurs attachements
            $messages = $query->with('attachments')->get();

            // Formater les messages pour la réponse
            $formattedMessages = $messages->map(function ($message) {
                $attachment = $message->attachments->first();
                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'isFromClient' => $message->is_from_client,
                    'time' => Carbon::parse($message->created_at)->format('H:i'),
                    'date' => Carbon::parse($message->created_at)->toDateString(),
                    'created_at' => $message->created_at,
                    'attachment' => $attachment ? [
                        'id' => $attachment->id,
                        'url' => $attachment->url,
                        'file_name' => $attachment->file_name,
                        'mime_type' => $attachment->mime_type,
                    ] : null,
                ];
            });

            // Retourner la réponse
            return response()->json([
                'success' => true,
                'messages' => $formattedMessages,
                'count' => $formattedMessages->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des messages: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la récupération des messages',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
