<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\ModeratorProfileAssignment;
use App\Models\Profile;
use App\Models\User;
use App\Services\ModeratorAssignmentService;
use App\Events\MessageSent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixModeratorController extends Controller
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
        // Au lieu d'attribuer automatiquement un profil unique,
        // on vérifie si le modérateur a au moins un profil attribué
        $assignedProfiles = $this->assignmentService->getAllAssignedProfiles(Auth::user());

        // Si aucun profil n'est attribué, essayer d'en attribuer un
        if ($assignedProfiles->isEmpty()) {
            $assignment = $this->assignmentService->assignProfileToModerator(Auth::user());
            if ($assignment) {
                $assignedProfiles = $this->assignmentService->getAllAssignedProfiles(Auth::user());
            }
        } else {
            // Mettre à jour la dernière activité pour tous les profils
            $this->assignmentService->updateLastActivity(Auth::user());
        }

        // Traitement de tous les messages non assignés
        // Cette étape est importante pour distribuer équitablement les messages entre modérateurs
        $this->assignmentService->processUnassignedMessages();

        return Inertia::render('Moderator');
    }

    /**
     * Récupère les clients attribués au modérateur pour discussion
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClients()
    {
        $currentModeratorId = Auth::id();

        Log::info("[DEBUG] Récupération des clients pour le modérateur", [
            'moderator_id' => $currentModeratorId
        ]);

        // Récupérer tous les profils attribués à ce modérateur
        $assignedProfileIds = ModeratorProfileAssignment::where('user_id', $currentModeratorId)
            ->where('is_active', true)
            ->pluck('profile_id')
            ->toArray();

        Log::info("[DEBUG] Profils attribués au modérateur", [
            'profile_ids' => $assignedProfileIds
        ]);

        if (empty($assignedProfileIds)) {
            Log::warning("[DEBUG] Aucun profil attribué au modérateur");
            return response()->json([
                'clients' => []
            ]);
        }

        // Trouver les clients qui ont interagi avec ces profils
        // et qui attendent une réponse (leur dernier message est sans réponse)
        $clientsNeedingResponse = [];

        foreach ($assignedProfileIds as $profileId) {
            // Pour chaque profil, trouver les clients qui ont besoin d'une réponse
            $latestMessages = DB::table('messages as m1')
                ->select('m1.*')
                ->where('m1.profile_id', $profileId)
                ->whereIn(DB::raw('(m1.client_id, m1.id)'), function ($query) use ($profileId) {
                    $query->select(DB::raw('client_id, MAX(id)'))
                        ->from('messages')
                        ->where('profile_id', $profileId)
                        ->groupBy('client_id');
                })
                ->where('m1.is_from_client', true)
                ->orderBy('m1.created_at', 'desc')
                ->get();

            Log::info("[DEBUG] Messages récents pour le profil", [
                'profile_id' => $profileId,
                'message_count' => $latestMessages->count()
            ]);

            foreach ($latestMessages as $message) {
                $client = User::find($message->client_id);

                if (!$client) continue;

                // Récupérer le nombre de messages non lus pour ce client et ce profil
                $unreadCount = Message::where('profile_id', $profileId)
                    ->where('client_id', $client->id)
                    ->where('is_from_client', true)
                    ->whereNull('read_at')
                    ->count();

                // Récupérer les informations du profil
                $profile = Profile::find($profileId);

                if (!$profile) continue;

                // Ajouter ce client à la liste
                $clientsNeedingResponse[] = [
                    'id' => $client->id,
                    'name' => $client->name,
                    'avatar' => null, // À compléter si vous avez des avatars
                    'lastMessage' => $message->content,
                    'unreadCount' => $unreadCount,
                    'createdAt' => $message->created_at,
                    'profileId' => $profileId,
                    'profileName' => $profile->name,
                    'profilePhoto' => $profile->main_photo_path,
                ];
            }
        }

        // Trier par ordre chronologique (les plus anciens messages en premier)
        usort($clientsNeedingResponse, function ($a, $b) {
            return strtotime($a['createdAt']) - strtotime($b['createdAt']);
        });

        Log::info("[DEBUG] Clients nécessitant une réponse", [
            'client_count' => count($clientsNeedingResponse)
        ]);

        return response()->json([
            'clients' => $clientsNeedingResponse
        ]);
    }

    /**
     * Récupère le profil actuellement attribué au modérateur comme profil principal
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAssignedProfile()
    {
        // Récupérer tous les profils attribués
        $profiles = $this->assignmentService->getAllAssignedProfiles(Auth::user());

        // Trouver le profil principal (si existe)
        $primaryAssignment = ModeratorProfileAssignment::where('user_id', Auth::id())
            ->where('is_active', true)
            ->where('is_primary', true)
            ->first();

        $primaryProfileId = $primaryAssignment ? $primaryAssignment->profile_id : null;

        // Si aucun profil n'est attribué, en attribuer un automatiquement
        if ($profiles->isEmpty()) {
            $assignment = $this->assignmentService->assignProfileToModerator(Auth::user());
            if ($assignment) {
                $profiles = $this->assignmentService->getAllAssignedProfiles(Auth::user());
                $primaryProfileId = $assignment->profile_id;
            }
        } else {
            // Mettre à jour la dernière activité
            $this->assignmentService->updateLastActivity(Auth::user());
        }

        // Préparer les données pour la réponse
        $profilesData = $profiles->map(function ($profile) use ($primaryProfileId) {
            return [
                'id' => $profile->id,
                'name' => $profile->name,
                'gender' => $profile->gender,
                'bio' => $profile->bio,
                'main_photo_path' => $profile->main_photo_path,
                'photos' => $profile->photos,
                'isPrimary' => $profile->id === $primaryProfileId
            ];
        });

        $primaryProfile = $profiles->firstWhere('id', $primaryProfileId);

        // Log pour debugging
        Log::info("[DEBUG] Profils attribués au modérateur", [
            'moderator_id' => Auth::id(),
            'profiles_count' => $profiles->count(),
            'primary_profile_id' => $primaryProfileId,
            'has_primary_profile' => $primaryProfile ? true : false
        ]);

        return response()->json([
            'profiles' => $profilesData,
            'primaryProfile' => $primaryProfile ? [
                'id' => $primaryProfile->id,
                'name' => $primaryProfile->name,
                'gender' => $primaryProfile->gender,
                'bio' => $primaryProfile->bio,
                'main_photo_path' => $primaryProfile->main_photo_path,
                'photos' => $primaryProfile->photos,
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
            'profile_id' => 'required|integer|exists:profiles,id',
        ]);

        $currentModeratorId = Auth::id();

        // Vérifier que ce modérateur a bien accès à ce profil
        $hasAccess = ModeratorProfileAssignment::where('user_id', $currentModeratorId)
            ->where('profile_id', $request->profile_id)
            ->where('is_active', true)
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                'error' => 'Accès non autorisé à ce profil'
            ], 403);
        }

        // Récupérer les messages entre ce client et ce profil
        $messages = Message::where('profile_id', $request->profile_id)
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
        Message::where('profile_id', $request->profile_id)
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
            'profile_id' => 'required|integer|exists:profiles,id',
            'content' => 'required|string|max:1000',
        ]);

        $currentModeratorId = Auth::id();

        // Vérifier que ce modérateur a bien accès à ce profil
        $hasAccess = ModeratorProfileAssignment::where('user_id', $currentModeratorId)
            ->where('profile_id', $request->profile_id)
            ->where('is_active', true)
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé à ce profil'
            ], 403);
        }

        // Mettre à jour la dernière activité pour ce profil spécifique
        $this->assignmentService->updateLastActivity(Auth::user(), $request->profile_id);

        // Créer le nouveau message
        $message = Message::create([
            'client_id' => $request->client_id,
            'profile_id' => $request->profile_id,
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

    /**
     * Récupère la liste des clients disponibles qui ne sont pas déjà en conversation
     * avec un autre modérateur
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableClients()
    {
        // Récupérer tous les profils attribués à ce modérateur
        $assignedProfiles = $this->assignmentService->getAllAssignedProfiles(Auth::user());

        if ($assignedProfiles->isEmpty()) {
            return response()->json([
                'availableClients' => []
            ]);
        }

        // Récupérer les IDs des profils
        $profileIds = $assignedProfiles->pluck('id')->toArray();

        // Trouver les clients actifs qui pourraient être disponibles pour discussion
        // sans modifier la base de données, on utilise les données existantes
        $availableClients = User::where('type', 'client')
            ->where('status', 'active')
            ->whereDoesntHave('messages', function ($query) {
                // Filtrer les clients qui ont des messages récents (moins de 30min)
                // avec d'autres profils que ceux actuellement assignés au modérateur
                $query->where('created_at', '>', now()->subMinutes(30))
                    ->where('is_from_client', false); // Messages envoyés par des modérateurs
            })
            ->limit(10)
            ->get();

        $result = [];

        foreach ($availableClients as $client) {
            // Pour chaque client disponible, vérifier s'il a déjà discuté avec un des profils du modérateur
            $clientHistory = [];

            foreach ($profileIds as $profileId) {
                $lastMessage = Message::where('client_id', $client->id)
                    ->where('profile_id', $profileId)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($lastMessage) {
                    $profile = Profile::find($profileId);

                    if ($profile) {
                        $clientHistory[] = [
                            'profile_id' => $profileId,
                            'profile_name' => $profile->name,
                            'profile_photo' => $profile->main_photo_path,
                            'last_message' => $lastMessage->content,
                            'last_activity' => $lastMessage->created_at->diffForHumans()
                        ];
                    }
                }
            }

            $result[] = [
                'id' => $client->id,
                'name' => $client->name,
                'avatar' => null, // À compléter si vous avez des avatars
                'history' => $clientHistory,
                'hasHistory' => !empty($clientHistory),
                'lastActivity' => $client->updated_at->diffForHumans()
            ];
        }

        return response()->json([
            'availableClients' => $result
        ]);
    }

    /**
     * Initie une conversation avec un client spécifique
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function startConversation(Request $request)
    {
        $request->validate([
            'client_id' => 'required|integer|exists:users,id',
            'profile_id' => 'required|integer|exists:profiles,id',
        ]);

        $currentModeratorId = Auth::id();

        // Vérifier que ce modérateur a bien accès à ce profil
        $hasAccess = ModeratorProfileAssignment::where('user_id', $currentModeratorId)
            ->where('profile_id', $request->profile_id)
            ->where('is_active', true)
            ->exists();

        if (!$hasAccess) {
            // Si le modérateur n'a pas ce profil, essayer de le lui attribuer
            $profile = Profile::find($request->profile_id);
            if ($profile) {
                $assignment = $this->assignmentService->assignProfileToModerator(Auth::user(), $profile, false);
                if (!$assignment) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Impossible d\'attribuer ce profil actuellement'
                    ], 400);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Profil introuvable'
                ], 404);
            }
        }

        // Mettre à jour la dernière activité pour ce profil
        $this->assignmentService->updateLastActivity(Auth::user(), $request->profile_id);

        // Vérifier si le client existe et est disponible
        $client = User::where('id', $request->client_id)
            ->where('type', 'client')
            ->where('status', 'active')
            ->first();

        if (!$client) {
            return response()->json([
                'success' => false,
                'message' => 'Client non disponible'
            ], 404);
        }

        // Récupérer les détails du client et du profil
        $profile = Profile::find($request->profile_id);

        $clientDetails = [
            'id' => $client->id,
            'name' => $client->name,
            'avatar' => null, // À compléter si vous avez des avatars
            'profileId' => $profile->id,
            'profileName' => $profile->name,
            'profilePhoto' => $profile->main_photo_path
        ];

        // Charger l'historique des messages si disponible
        $messages = Message::where('client_id', $client->id)
            ->where('profile_id', $request->profile_id)
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
        Message::where('client_id', $client->id)
            ->where('profile_id', $request->profile_id)
            ->where('is_from_client', true)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'client' => $clientDetails,
            'messages' => $messages,
        ]);
    }

    /**
     * Définit un profil comme profil principal du modérateur
     * 
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function setPrimaryProfile(Request $request)
    {
        $request->validate([
            'profile_id' => 'required|integer|exists:profiles,id',
        ]);

        $currentModeratorId = Auth::id();

        // Vérifier que ce modérateur a bien ce profil
        $hasProfile = ModeratorProfileAssignment::where('user_id', $currentModeratorId)
            ->where('profile_id', $request->profile_id)
            ->where('is_active', true)
            ->exists();

        if (!$hasProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Ce profil ne vous est pas attribué'
            ], 400);
        }

        // Réinitialiser tous les profils à non-primaire
        ModeratorProfileAssignment::where('user_id', $currentModeratorId)
            ->where('is_active', true)
            ->update(['is_primary' => false]);

        // Définir ce profil comme primaire
        ModeratorProfileAssignment::where('user_id', $currentModeratorId)
            ->where('profile_id', $request->profile_id)
            ->where('is_active', true)
            ->update(['is_primary' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Profil principal mis à jour avec succès'
        ]);
    }
}
