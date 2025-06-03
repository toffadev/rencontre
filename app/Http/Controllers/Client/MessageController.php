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
use App\Models\ConversationState;
use App\Services\MessageAttachmentService;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    protected $pointService;
    protected $attachmentService;

    public function __construct(PointService $pointService, MessageAttachmentService $attachmentService)
    {
        $this->pointService = $pointService;
        $this->attachmentService = $attachmentService;
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

        // Récupérer les messages avec leurs pièces jointes
        $messages = Message::with('attachments')
            ->where('profile_id', $profileId)
            ->where('client_id', $clientId)
            ->orderBy('created_at')
            ->get();

        $formattedMessages = $messages->map(function ($message) {
            $attachmentData = null;
            if ($message->attachments->isNotEmpty()) {
                $attachment = $message->attachments->first();
                $attachmentData = [
                    'id' => $attachment->id,
                    'file_name' => $attachment->file_name,
                    'mime_type' => $attachment->mime_type,
                    'url' => Storage::url($attachment->file_path)
                ];
            }

            return [
                'id' => $message->id,
                'content' => $message->content,
                'isOutgoing' => $message->is_from_client,
                'time' => $message->created_at->format('H:i'),
                'date' => $message->created_at->format('Y-m-d'),
                'created_at' => $message->created_at->toISOString(),
                'attachment' => $attachmentData
            ];
        });

        // Compter les messages non lus
        $unreadCount = $messages->where('is_from_client', false)
            ->where('read_at', null)
            ->count();

        // Vérifier si le dernier message nécessite une réponse
        $lastMessage = $messages->last();
        $awaitingReply = $lastMessage && !$lastMessage->is_from_client;

        // Récupérer ou créer l'état de la conversation
        $conversationState = ConversationState::updateOrCreate(
            [
                'client_id' => $clientId,
                'profile_id' => $profileId
            ],
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
     * Envoie un message du client à un profil
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'profile_id' => 'required|integer|exists:profiles,id',
            'content' => 'required_without:attachment|string|max:1000',
            'attachment' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
        ]);

        $user = Auth::user();
        $clientId = $user->id;
        $profileId = $request->profile_id;

        try {
            // Créer le nouveau message
            $message = Message::create([
                'client_id' => $clientId,
                'profile_id' => $profileId,
                'moderator_id' => null,
                'content' => $request->content ?? '',
                'is_from_client' => true,
            ]);

            // Gérer la pièce jointe si présente
            $attachment = null;
            if ($request->hasFile('attachment')) {
                $attachment = $this->attachmentService->storeAttachment($message, $request->file('attachment'));
            }

            // Déduire les points après la création du message
            if (!$this->pointService->deductPoints($user, 'message_sent', PointService::POINTS_PER_MESSAGE, $message)) {
                // Si la déduction échoue, supprimer le message et retourner une erreur
                if ($attachment) {
                    $this->attachmentService->deleteAttachment($attachment);
                }
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
                'has_attachment' => $attachment !== null,
                'points_remaining' => $user->points
            ]);

            // Préparer les données de l'attachement pour la réponse
            $attachmentData = null;
            if ($attachment) {
                $attachmentData = [
                    'id' => $attachment->id,
                    'file_name' => $attachment->file_name,
                    'mime_type' => $attachment->mime_type,
                    'url' => Storage::url($attachment->file_path)
                ];
            }

            // Diffuser l'événement de message
            broadcast(new MessageSent($message))->toOthers();

            // Déclencher l'événement NewClientMessage pour la gestion des attributions
            event(new NewClientMessage($message));

            // Mettre à jour l'état de la conversation
            ConversationState::updateOrCreate(
                [
                    'client_id' => $clientId,
                    'profile_id' => $profileId
                ],
                [
                    'last_read_message_id' => $message->id,
                    'has_been_opened' => true,
                    'awaiting_reply' => false
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Message envoyé avec succès',
                'messageData' => [
                    'id' => $message->id,
                    'content' => $message->content,
                    'isOutgoing' => true,
                    'time' => $message->created_at->format('H:i'),
                    'date' => $message->created_at->format('Y-m-d'),
                    'created_at' => $message->created_at->toISOString(),
                    'attachment' => $attachmentData
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

    /**
     * Récupère toutes les conversations actives du client
     */
    public function getActiveConversations()
    {
        $clientId = Auth::id();

        // Récupérer tous les profils avec qui le client a échangé des messages
        $conversations = Message::where('client_id', $clientId)
            ->select('profile_id')
            ->distinct()
            ->with(['profile' => function ($query) {
                $query->select('id', 'name', 'main_photo_path', 'gender', 'created_at');
            }])
            ->get();

        $conversationsData = $conversations->map(function ($conversation) use ($clientId) {
            $profileId = $conversation->profile_id;

            // Compter les messages non lus pour ce profil
            $unreadCount = Message::where('client_id', $clientId)
                ->where('profile_id', $profileId)
                ->where('is_from_client', false)
                ->whereNull('read_at')
                ->count();

            // Vérifier si le dernier message nécessite une réponse
            $lastMessage = Message::where('client_id', $clientId)
                ->where('profile_id', $profileId)
                ->latest()
                ->first();

            $awaitingReply = $lastMessage && !$lastMessage->is_from_client;

            // Récupérer ou créer l'état de la conversation
            $state = ConversationState::firstOrCreate(
                [
                    'client_id' => $clientId,
                    'profile_id' => $profileId
                ],
                [
                    'awaiting_reply' => $awaitingReply
                ]
            );

            return [
                'profile_id' => $profileId,
                'profile' => $conversation->profile,
                'unread_count' => $unreadCount,
                'awaiting_reply' => $awaitingReply,
                'has_been_opened' => $state->has_been_opened,
                'last_read_message_id' => $state->last_read_message_id
            ];
        });

        return response()->json([
            'conversations' => $conversationsData
        ]);
    }

    /**
     * Mark messages as read for a specific profile
     */
    public function markAsRead(Request $request)
    {
        $request->validate([
            'profile_id' => 'required|exists:profiles,id',
            'last_message_id' => 'required|exists:messages,id'
        ]);

        $clientId = Auth::id();
        $profileId = $request->profile_id;

        // Marquer tous les messages non lus comme lus
        Message::where('client_id', $clientId)
            ->where('profile_id', $profileId)
            ->where('is_from_client', false)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Mettre à jour l'état de la conversation
        ConversationState::updateOrCreate(
            [
                'client_id' => $clientId,
                'profile_id' => $profileId
            ],
            [
                'last_read_message_id' => $request->last_message_id,
                'has_been_opened' => true,
                'awaiting_reply' => true
            ]
        );

        return response()->json(['success' => true]);
    }
}
