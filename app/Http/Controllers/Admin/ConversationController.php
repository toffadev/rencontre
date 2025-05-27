<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Message;
use App\Models\Profile;
use App\Models\PointConsumption;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ConversationController extends Controller
{
    /**
     * Affiche la page principale du visualiseur de conversations
     */
    public function index()
    {
        return Inertia::render('ConversationViewer');
    }

    /**
     * Récupère la liste des clients avec des conversations
     */
    public function getClients(Request $request)
    {
        try {
            $query = User::where('type', 'client')
                ->whereHas('messages')
                ->with(['clientInfo']);

            if ($request->search) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%")
                        ->orWhere('email', 'like', "%{$request->search}%");
                });
            }

            $clients = $query->orderBy('created_at', 'desc')
                ->paginate(20)
                ->through(function ($client) {
                    try {
                        $lastMessage = $client->messages()->latest()->first();
                        return [
                            'id' => $client->id,
                            'name' => $client->name,
                            'email' => $client->email,
                            'avatar' => $client->clientInfo?->avatar_url,
                            'last_activity' => $lastMessage ? $lastMessage->created_at : null,
                            'total_messages' => $client->messages()->count(),
                            'total_points_spent' => $client->pointConsumptions()->sum('points_spent')
                        ];
                    } catch (\Exception $e) {
                        \Log::error('Error processing client: ' . $e->getMessage());
                        return null;
                    }
                });

            return response()->json($clients);
        } catch (\Exception $e) {
            \Log::error('Error in getClients: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Récupère les profils avec lesquels un client a discuté
     */
    public function getClientProfiles($clientId)
    {
        $profiles = Profile::whereHas('messages', function ($query) use ($clientId) {
            $query->where('client_id', $clientId);
        })
            ->with(['photos'])
            ->get()
            ->map(function ($profile) use ($clientId) {
                $lastMessage = $profile->messages()
                    ->where('client_id', $clientId)
                    ->latest()
                    ->first();

                return [
                    'id' => $profile->id,
                    'name' => $profile->name,
                    'main_photo_path' => $profile->main_photo_path,
                    'last_message' => $lastMessage ? [
                        'content' => $lastMessage->content,
                        'created_at' => $lastMessage->created_at
                    ] : null,
                    'total_messages' => $profile->messages()
                        ->where('client_id', $clientId)
                        ->count()
                ];
            });

        return response()->json($profiles);
    }

    /**
     * Récupère une conversation complète avec statistiques
     */
    public function getConversation($clientId, $profileId)
    {
        // Récupérer les messages avec les relations nécessaires
        $messages = Message::where('client_id', $clientId)
            ->where('profile_id', $profileId)
            ->with(['moderator'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'is_from_client' => $message->is_from_client,
                    'created_at' => $message->created_at,
                    'read_at' => $message->read_at,
                    'moderator' => $message->moderator ? [
                        'id' => $message->moderator->id,
                        'name' => $message->moderator->name
                    ] : null
                ];
            });

        // Calculer les statistiques
        $stats = $this->calculateConversationStats($clientId, $profileId);

        return response()->json([
            'messages' => $messages,
            'statistics' => $stats
        ]);
    }

    /**
     * Calcule les statistiques détaillées d'une conversation
     */
    private function calculateConversationStats($clientId, $profileId)
    {
        $messages = Message::where('client_id', $clientId)
            ->where('profile_id', $profileId)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($messages->isEmpty()) {
            return null;
        }

        $clientMessages = $messages->where('is_from_client', true);
        $profileMessages = $messages->where('is_from_client', false);

        // Calculer les temps de réponse moyens
        $responseDelays = [];
        foreach ($clientMessages as $clientMessage) {
            $nextProfileMessage = $profileMessages
                ->where('created_at', '>', $clientMessage->created_at)
                ->first();

            if ($nextProfileMessage) {
                $responseDelays[] = $nextProfileMessage->created_at->diffInSeconds($clientMessage->created_at);
            }
        }

        // Points dépensés
        $pointsSpent = PointConsumption::where('user_id', $clientId)
            ->where('consumable_type', 'App\Models\Message')
            ->whereIn('consumable_id', $messages->pluck('id'))
            ->sum('points_spent');

        // Modérateurs impliqués
        $moderators = $messages->whereNotNull('moderator_id')
            ->pluck('moderator_id')
            ->unique()
            ->count();

        return [
            'total_messages' => $messages->count(),
            'client_messages' => $clientMessages->count(),
            'profile_messages' => $profileMessages->count(),
            'first_message_at' => $messages->first()->created_at,
            'last_message_at' => $messages->last()->created_at,
            'conversation_duration' => $messages->last()->created_at->diffForHumans($messages->first()->created_at),
            'average_response_time' => count($responseDelays) > 0 ? round(array_sum($responseDelays) / count($responseDelays)) : 0,
            'points_spent' => $pointsSpent,
            'moderators_involved' => $moderators,
            'engagement_rate' => round(($profileMessages->count() / max($clientMessages->count(), 1)) * 100, 2)
        ];
    }
}
