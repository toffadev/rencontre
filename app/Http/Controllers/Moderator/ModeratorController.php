<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Profile;
use App\Models\User;
use App\Services\ModeratorAssignmentService;
use App\Events\MessageSent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ModeratorController extends Controller
{
    /**
     * Le service d'attribution de profils.
     *
     * @var \App\Services\ModeratorAssignmentService
     */
    protected $assignmentService;

    /**
     * Créer une nouvelle instance du contrôleur.
     *
     * @param  \App\Services\ModeratorAssignmentService  $assignmentService
     * @return void
     */
    public function __construct(ModeratorAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    /**
     * Affiche la page principale des modérateurs
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        // Vérifier si le modérateur a déjà un profil attribué et mettre à jour son activité
        $currentProfile = $this->assignmentService->getCurrentAssignedProfile(Auth::user());
        if ($currentProfile) {
            $this->assignmentService->updateLastActivity(Auth::user());
        } else {
            // Si aucun profil n'est attribué, essayer d'en attribuer un
            $assignment = $this->assignmentService->assignProfileToModerator(Auth::user());
            if ($assignment) {
                $currentProfile = $assignment->profile;
            }
        }

        // Le système attribuera automatiquement un client au modérateur
        // lorsqu'il accédera à la page (via les API getClients et getMessages)

        return Inertia::render('Moderator');
    }

    /**
     * Récupère le client attribué au modérateur pour discussion
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClients()
    {
        // Récupérer le profil actuellement attribué au modérateur
        $profile = $this->assignmentService->getCurrentAssignedProfile(Auth::user());

        if (!$profile) {
            return response()->json([
                'clients' => []
            ]);
        }

        // Trouver un client qui a besoin d'une réponse
        // On se concentre sur un seul client à la fois, attribué automatiquement par le système
        $clientNeeding = $this->findClientNeedingResponse($profile->id);

        if ($clientNeeding) {
            return response()->json([
                'clients' => [$clientNeeding]
            ]);
        }

        return response()->json([
            'clients' => []
        ]);
    }

    /**
     * Trouve un client qui a besoin d'une réponse
     * 
     * @param int $profileId L'ID du profil attribué au modérateur
     * @return array|null Les informations du client, ou null si aucun client n'attend de réponse
     */
    protected function findClientNeedingResponse($profileId)
    {
        // Trouver le client le plus prioritaire qui a envoyé un message sans réponse
        // Prioritiser les clients avec des messages non lus et les plus anciens messages

        $latestMessages = DB::table('messages as m1')
            ->select('m1.*')
            ->where('m1.profile_id', $profileId)
            ->whereRaw('m1.id = (SELECT MAX(m2.id) FROM messages m2 WHERE m2.client_id = m1.client_id AND m2.profile_id = m1.profile_id)')
            ->orderBy('m1.created_at', 'desc')
            ->get();

        // Filtrer pour trouver ceux où le dernier message est du client
        $clientsNeedingResponse = $latestMessages->filter(function ($message) {
            return $message->is_from_client;
        });

        if ($clientsNeedingResponse->isEmpty()) {
            return null;
        }

        // Prendre le premier client qui a besoin d'une réponse
        $message = $clientsNeedingResponse->first();

        // Récupérer les détails du client
        $client = User::find($message->client_id);

        if (!$client) {
            return null;
        }

        // Récupérer le dernier message et le nombre de messages non lus
        $unreadCount = Message::where('profile_id', $profileId)
            ->where('client_id', $client->id)
            ->where('is_from_client', true)
            ->whereNull('read_at')
            ->count();

        return [
            'id' => $client->id,
            'name' => $client->name,
            'avatar' => null, // À compléter si vous avez des avatars
            'lastMessage' => $message->content,
            'unreadCount' => $unreadCount
        ];
    }

    /**
     * Récupère le profil actuellement attribué au modérateur
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAssignedProfile()
    {
        $profile = $this->assignmentService->getCurrentAssignedProfile(Auth::user());

        // Si aucun profil n'est attribué, en attribuer un automatiquement
        if (!$profile) {
            $assignment = $this->assignmentService->assignProfileToModerator(Auth::user());
            $profile = $assignment ? $assignment->profile : null;
        } else {
            // Mettre à jour la dernière activité
            $this->assignmentService->updateLastActivity(Auth::user());
        }

        return response()->json([
            'profile' => $profile ? [
                'id' => $profile->id,
                'name' => $profile->name,
                'gender' => $profile->gender,
                'bio' => $profile->bio,
                'main_photo_path' => $profile->main_photo_path,
                'photos' => $profile->photos
            ] : null
        ]);
    }

    /**
     * Récupère l'historique des messages avec un client spécifique
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMessages(Request $request)
    {
        $request->validate([
            'client_id' => 'required|integer|exists:users,id',
        ]);

        $profile = $this->assignmentService->getCurrentAssignedProfile(Auth::user());

        if (!$profile) {
            return response()->json([
                'messages' => []
            ]);
        }

        // Récupérer les messages entre ce client et ce profil
        $messages = Message::where('profile_id', $profile->id)
            ->where('client_id', $request->client_id)
            ->orderBy('created_at')
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'isFromClient' => $message->is_from_client,
                    'time' => $message->created_at->format('H:i'),
                    'date' => $message->created_at->format('Y-m-d'),
                ];
            });

        // Marquer les messages non lus comme lus
        Message::where('profile_id', $profile->id)
            ->where('client_id', $request->client_id)
            ->where('is_from_client', true)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'messages' => $messages
        ]);
    }

    /**
     * Envoie un message à un client
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'client_id' => 'required|integer|exists:users,id',
            'content' => 'required|string|max:1000',
        ]);

        $profile = $this->assignmentService->getCurrentAssignedProfile(Auth::user());

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun profil attribué'
            ], 400);
        }

        // Mettre à jour la dernière activité
        $this->assignmentService->updateLastActivity(Auth::user());

        // Créer le nouveau message
        $message = Message::create([
            'client_id' => $request->client_id,
            'profile_id' => $profile->id,
            'moderator_id' => Auth::id(),
            'content' => $request->content,
            'is_from_client' => false,
        ]);

        // Diffuser l'événement de message
        event(new MessageSent($message));

        return response()->json([
            'success' => true,
            'message' => 'Message envoyé avec succès',
            'messageData' => [
                'id' => $message->id,
                'content' => $message->content,
                'isFromClient' => false,
                'time' => $message->created_at->format('H:i'),
                'date' => $message->created_at->format('Y-m-d'),
            ]
        ]);
    }
}
