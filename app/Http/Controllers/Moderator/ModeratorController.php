<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\ModeratorProfileAssignment;
use App\Models\Profile;
use App\Models\User;
use App\Services\ModeratorAssignmentService;
use App\Events\MessageSent;
use App\Services\WebSocketHealthService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ModeratorStatistic;
use App\Services\MessageAttachmentService;
use Illuminate\Support\Facades\Storage;
use App\Models\MessageAttachment;
use App\Services\ModeratorActivityService;

class ModeratorController extends Controller
{
    /**
     * Le service d'attribution de profils.
     *
     * @var \App\Services\ModeratorAssignmentService
     */
    protected $assignmentService;

    /**
     * Le service de gestion des pièces jointes.
     *
     * @var \App\Services\MessageAttachmentService
     */
    protected $attachmentService;

    /**
     * Le service de surveillance des WebSockets.
     *
     * @var \App\Services\WebSocketHealthService
     */
    protected $webSocketHealthService;

    /**
     * Normalise un chemin de fichier pour éviter les problèmes de double slash
     * 
     * @param string $path
     * @return string
     */
    private function normalizeFilePath($path)
    {
        // Si le chemin commence par /storage/, le convertir en storage/
        if (strpos($path, '/storage/') === 0) {
            return 'storage' . substr($path, 8);
        }

        // Si le chemin commence par storage/ (sans slash), le laisser tel quel
        if (strpos($path, 'storage/') === 0) {
            return $path;
        }

        // Sinon, ajouter storage/ au début si nécessaire
        if (!str_starts_with($path, 'storage/') && !str_starts_with($path, '/storage/')) {
            return 'storage/' . $path;
        }

        return $path;
    }

    /**
     * Créer une nouvelle instance du contrôleur.
     *
     * @param  \App\Services\ModeratorAssignmentService  $assignmentService
     * @param  \App\Services\MessageAttachmentService  $attachmentService
     * @param  \App\Services\WebSocketHealthService  $webSocketHealthService
     * @return void
     */
    public function __construct(
        ModeratorAssignmentService $assignmentService,
        MessageAttachmentService $attachmentService,
        WebSocketHealthService $webSocketHealthService
    ) {
        $this->assignmentService = $assignmentService;
        $this->attachmentService = $attachmentService;
        $this->webSocketHealthService = $webSocketHealthService;
    }

    /**
     * Affiche la page principale des modérateurs
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        // Enregistrer la connexion WebSocket si disponible
        if (request()->header('X-Socket-ID')) {
            $this->webSocketHealthService->registerConnection(
                Auth::id(),
                Auth::user()->type,
                request()->header('X-Socket-ID') ?? uniqid('conn_')
            );

            // Mettre à jour le statut en ligne du modérateur
            User::where('id', Auth::id())->update([
                'is_online' => true,
                'last_online_at' => now()
            ]);
        }

        // Passer explicitement l'utilisateur à Inertia
        $user = Auth::user();

        return Inertia::render('Moderator', [
            'user' => $user // Ajouter explicitement l'utilisateur
        ]);
    }

    /**
     * Récupère les clients attribués au modérateur
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClients()
    {
        $currentModeratorId = Auth::id();

        Log::info("[DEBUG] Récupération des clients pour le modérateur", [
            'moderator_id' => $currentModeratorId
        ]);

        // Récupérer le profil actif du modérateur
        $assignment = ModeratorProfileAssignment::where('user_id', $currentModeratorId)
            ->where('is_active', true)
            ->first();

        if (!$assignment) {
            Log::warning("[DEBUG] Aucun profil actif trouvé pour le modérateur");
            return response()->json([
                'clients' => []
            ]);
        }

        $profileId = $assignment->profile_id;

        // Vérifier si des clients sont explicitement assignés
        $conversationIds = $assignment->conversation_ids ?? [];

        // Si aucun client n'est explicitement assigné, chercher les clients avec des messages récents
        if (empty($conversationIds)) {
            // Trouver les clients qui ont des messages non lus pour ce profil
            $clientsWithMessages = Message::where('profile_id', $profileId)
                ->where('is_from_client', true)
                ->whereNull('read_at')
                ->select('client_id')
                ->distinct()
                ->pluck('client_id')
                ->toArray();

            // Ajouter ces clients à l'assignation
            if (!empty($clientsWithMessages)) {
                foreach ($clientsWithMessages as $clientId) {
                    $assignment->addConversation($clientId);
                }
                $conversationIds = $assignment->conversation_ids ?? [];
            }

            // Si toujours pas de clients, chercher les clients avec des conversations existantes
            if (empty($conversationIds)) {
                $clientsWithConversation = Message::where('profile_id', $profileId)
                    ->select('client_id')
                    ->distinct()
                    ->pluck('client_id')
                    ->toArray();

                if (!empty($clientsWithConversation)) {
                    foreach ($clientsWithConversation as $clientId) {
                        $assignment->addConversation($clientId);
                    }
                    $conversationIds = $assignment->conversation_ids ?? [];
                }
            }
        }

        // Si toujours pas de clients, retourner une liste vide
        if (empty($conversationIds)) {
            return response()->json([
                'clients' => []
            ]);
        }

        // Récupérer les informations des clients
        $clientsNeedingResponse = [];

        foreach ($conversationIds as $clientId) {
            // Récupérer le client
            $client = User::find($clientId);
            if (!$client) continue;

            // Récupérer le dernier message échangé
            $lastMessage = Message::where('client_id', $clientId)
                ->where('profile_id', $profileId)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$lastMessage) continue;

            // Vérifier s'il y a des messages non lus
            $unreadCount = Message::where('client_id', $clientId)
                ->where('profile_id', $profileId)
                ->where('is_from_client', true)
                ->whereNull('read_at')
                ->count();

            // Ajouter le client à la liste
            $clientsNeedingResponse[] = [
                'id' => $client->id,
                'name' => $client->name,
                'avatar' => $client->profile_photo_url ?? null,
                'lastMessage' => $lastMessage->content,
                'lastMessageTime' => $lastMessage->created_at->diffForHumans(),
                'unreadCount' => $unreadCount,
                'isTyping' => false, // Par défaut, le client n'est pas en train de taper
                'profileId' => $profileId
            ];
        }

        return response()->json([
            'clients' => $clientsNeedingResponse
        ]);
    }

    /* public function getClients()
    {
        $currentModeratorId = Auth::id();

        Log::info("[DEBUG] Récupération des clients pour le modérateur", [
            'moderator_id' => $currentModeratorId
        ]);

        // Récupérer le profil principal actif du modérateur
        $primaryAssignment = ModeratorProfileAssignment::where('user_id', $currentModeratorId)
            ->where('is_active', true)
            ->where('is_primary', true)
            ->first();

        if (!$primaryAssignment) {
            Log::warning("[DEBUG] Aucun profil principal actif trouvé pour le modérateur");
            return response()->json([
                'clients' => []
            ]);
        }

        $primaryProfileId = $primaryAssignment->profile_id;

        // Vérifier si des clients sont explicitement assignés
        $conversationIds = $primaryAssignment->conversation_ids ?? [];

        // Si aucun client n'est explicitement assigné, chercher les clients avec des messages récents
        if (empty($conversationIds)) {
            // Trouver les clients qui ont des messages non lus pour ce profil
            $clientsWithMessages = Message::where('profile_id', $primaryProfileId)
                ->where('is_from_client', true)
                ->whereNull('read_at')
                ->select('client_id')
                ->distinct()
                ->pluck('client_id')
                ->toArray();

            // Ajouter ces clients à l'assignation
            if (!empty($clientsWithMessages)) {
                foreach ($clientsWithMessages as $clientId) {
                    $primaryAssignment->addConversation($clientId);
                }
                $conversationIds = $primaryAssignment->conversation_ids ?? [];
            }
        }

        // Si toujours pas de clients, retourner une liste vide
        if (empty($conversationIds)) {
            return response()->json([
                'clients' => []
            ]);
        }

        // Le reste de la méthode reste inchangé...
        // Continuer avec la récupération des informations des clients
        $clientsNeedingResponse = [];

        foreach ($conversationIds as $clientId) {
            // Code existant pour récupérer les informations des clients...
        }

        return response()->json([
            'clients' => $clientsNeedingResponse
        ]);
    } */

    /**
     * Récupère le profil actuellement attribué au modérateur
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAssignedProfile()
    {
        $moderator = Auth::user();

        Log::info("[DEBUG] Récupération du profil pour le modérateur", [
            'moderator_id' => $moderator->id,
            'moderator_name' => $moderator->name,
            'moderator_type' => $moderator->type,
        ]);

        // Récupérer le profil actif du modérateur (un seul)
        $assignment = ModeratorProfileAssignment::where('user_id', $moderator->id)
            ->where('is_active', true)
            ->first();

        if (!$assignment) {
            Log::info("[DEBUG] Aucun profil attribué, tentative d'attribution automatique");

            // Aucun profil assigné, en attribuer un automatiquement
            $assignment = $this->assignmentService->assignProfileToModerator($moderator->id, null, true);

            if (!$assignment) {
                Log::warning("[DEBUG] Échec de l'attribution automatique d'un profil");

                return response()->json([
                    'profiles' => [],
                    'primaryProfile' => null
                ]);
            }

            Log::info("[DEBUG] Profil attribué automatiquement", [
                'assignment_id' => $assignment->id,
                'profile_id' => $assignment->profile_id
            ]);
        } else {
            // Mettre à jour la dernière activité
            $this->assignmentService->updateLastActivity($moderator);
        }

        // Récupérer les détails du profil
        $profile = Profile::with('photos')->find($assignment->profile_id);

        if (!$profile) {
            Log::warning("[DEBUG] Profil assigné non trouvé dans la base de données", [
                'profile_id' => $assignment->profile_id
            ]);

            return response()->json([
                'profiles' => [],
                'primaryProfile' => null
            ]);
        }

        // Construire la réponse
        $profileData = [
            'id' => $profile->id,
            'name' => $profile->name,
            'gender' => $profile->gender,
            'bio' => $profile->bio,
            'main_photo_path' => $profile->main_photo_path,
            'photos' => $profile->photos,
            'isPrimary' => true
        ];

        // Log final
        Log::info("[DEBUG] Résultat final de getAssignedProfile", [
            'profile_id' => $profile->id,
            'profile_name' => $profile->name
        ]);

        // Maintenir la structure de réponse attendue par le frontend
        return response()->json([
            'profiles' => [$profileData],
            'primaryProfile' => [
                'id' => $profile->id,
                'name' => $profile->name,
                'gender' => $profile->gender,
                'bio' => $profile->bio,
                'main_photo_path' => $profile->main_photo_path,
                'photos' => $profile->photos,
            ]
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
            'client_id' => 'required|exists:users,id',
            'profile_id' => 'required|exists:profiles,id',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:50',
        ]);

        $currentModeratorId = Auth::id();
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 20);

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

        // Récupérer les messages avec pagination et leurs pièces jointes
        $messagesQuery = Message::with('attachments')
            ->where('client_id', $request->client_id)
            ->where('profile_id', $request->profile_id)
            ->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage);

        $messages = $messagesQuery->get()->sortBy('created_at')->values()
            ->map(function ($message) {
                $attachmentData = null;
                if ($message->attachments->isNotEmpty()) {
                    $attachment = $message->attachments->first();
                    $normalizedPath = $this->normalizeFilePath($attachment->file_path);
                    $attachmentData = [
                        'id' => $attachment->id,
                        'file_name' => $attachment->file_name,
                        'mime_type' => $attachment->mime_type,
                        'url' => asset($normalizedPath)
                    ];
                }

                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'isFromClient' => $message->is_from_client,
                    'time' => $message->created_at->format('H:i'),
                    'date' => $message->created_at->format('Y-m-d'),
                    'created_at' => $message->created_at->toISOString(),
                    'attachment' => $attachmentData
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
        try {
            Log::info('Début de sendMessage', [
                'request_data' => $request->all(),
                'has_file' => $request->hasFile('attachment'),
                'auth_id' => Auth::id(),
                'csrf_token' => $request->header('X-CSRF-TOKEN')
            ]);

            $request->validate([
                'client_id' => 'required|exists:users,id',
                'profile_id' => 'required|exists:profiles,id',
                'content' => 'required_without:attachment|string|max:1000',
                'attachment' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:5120',
            ]);

            Log::info('Validation passée');

            $moderatorId = Auth::id();

            if (!$moderatorId) {
                Log::error('Modérateur non authentifié');
                return response()->json([
                    'success' => false,
                    'error' => 'Non authentifié'
                ], 401);
            }

            Log::info('Vérification de l\'accès au profil', [
                'moderator_id' => $moderatorId,
                'profile_id' => $request->profile_id
            ]);

            // Vérifier l'accès au profil
            $hasAccess = ModeratorProfileAssignment::where('user_id', $moderatorId)
                ->where('profile_id', $request->profile_id)
                ->where('is_active', true)
                ->exists();

            if (!$hasAccess) {
                Log::error('Accès non autorisé au profil', [
                    'moderator_id' => $moderatorId,
                    'profile_id' => $request->profile_id
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Accès non autorisé à ce profil'
                ], 403);
            }

            Log::info('Création du message');

            // Créer le message
            $message = Message::create([
                'client_id' => $request->client_id,
                'profile_id' => $request->profile_id,
                'moderator_id' => $moderatorId,
                'content' => $request->content ?? '',
                'is_from_client' => false,
            ]);

            Log::info('Message créé', [
                'message_id' => $message->id,
                'message_data' => $message->toArray()
            ]);

            // Gérer le fichier attaché
            if ($request->hasFile('attachment')) {
                try {
                    Log::info('Début du traitement du fichier', [
                        'file_info' => [
                            'name' => $request->file('attachment')->getClientOriginalName(),
                            'size' => $request->file('attachment')->getSize(),
                            'mime' => $request->file('attachment')->getMimeType()
                        ]
                    ]);

                    $attachment = $this->attachmentService->storeAttachment($message, $request->file('attachment'));

                    Log::info('Fichier uploadé avec succès', [
                        'attachment_id' => $attachment->id,
                        'file_name' => $attachment->file_name,
                        'file_path' => $attachment->file_path
                    ]);

                    // Normaliser le chemin et ajouter l'URL de l'attachement à la réponse
                    $normalizedPath = $this->normalizeFilePath($attachment->file_path);
                    $message->attachment = [
                        'url' => asset($normalizedPath),
                        'file_name' => $attachment->file_name,
                        'mime_type' => $attachment->mime_type,
                    ];
                } catch (\Exception $e) {
                    Log::error('Erreur lors du traitement du fichier', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            }

            // Formater la réponse
            $messageData = [
                'id' => $message->id,
                'content' => $message->content,
                'isFromClient' => false,
                'time' => $message->created_at->format('H:i'),
                'date' => $message->created_at->format('Y-m-d'),
                'created_at' => $message->created_at->toISOString(),
                'attachment' => $message->attachment ?? null,
            ];

            Log::info('Envoi de l\'événement broadcast');

            // Émettre l'événement en temps réel
            //broadcast(new MessageSent($message))->toOthers();
            event(new MessageSent($message)); // Utiliser event() au lieu de broadcast()->toOthers() pour un traitement immédiat

            // Mettre à jour l'activité du modérateur
            if (Auth::check()) {
                $this->webSocketHealthService->updateActivity(
                    request()->header('X-Socket-ID') ?? uniqid('conn_')
                );
            }

            Log::info('Message envoyé avec succès', [
                'message_id' => $message->id,
                'has_attachment' => isset($message->attachment),
                'response_data' => $messageData
            ]);

            return response()->json([
                'success' => true,
                'messageData' => $messageData,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans sendMessage', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
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
                'avatar' => $client->profile_photo_url ?? null,
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
            'avatar' => $client->clientProfile?->profile_photo_url,
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

        try {
            DB::transaction(function () use ($currentModeratorId, $request) {
                // Récupérer l'assignation actuelle qui est primaire
                $currentPrimary = ModeratorProfileAssignment::where('user_id', $currentModeratorId)
                    ->where('is_active', true)
                    ->where('is_primary', true)
                    ->first();

                // Si elle existe, la mettre à false
                if ($currentPrimary) {
                    $currentPrimary->is_primary = false;
                    $currentPrimary->save();
                }

                // Trouver l'assignation à mettre à jour
                $newPrimary = ModeratorProfileAssignment::where('user_id', $currentModeratorId)
                    ->where('profile_id', $request->profile_id)
                    ->where('is_active', true)
                    ->first();

                // La définir comme primaire
                if ($newPrimary) {
                    $newPrimary->is_primary = true;
                    $newPrimary->save();
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Profil principal mis à jour avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la définition du profil principal', [
                'moderator_id' => $currentModeratorId,
                'profile_id' => $request->profile_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la mise à jour du profil principal'
            ], 500);
        }
    }

    /**
     * Envoie une photo de profil à un client
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendProfilePhoto(Request $request)
    {
        try {
            $request->validate([
                'profile_id' => 'required|exists:profiles,id',
                'client_id' => 'required|exists:users,id',
                'photo_id' => 'required|integer',
            ]);

            $moderatorId = Auth::id();

            // Vérifier l'accès au profil
            $hasAccess = ModeratorProfileAssignment::where('user_id', $moderatorId)
                ->where('profile_id', $request->profile_id)
                ->where('is_active', true)
                ->exists();

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'error' => 'Accès non autorisé à ce profil'
                ], 403);
            }

            // Récupérer le profil et vérifier si la photo existe
            $profile = Profile::with('photos')->find($request->profile_id);

            if (!$profile) {
                return response()->json([
                    'success' => false,
                    'error' => 'Profil non trouvé'
                ], 404);
            }

            $photo = $profile->photos->firstWhere('id', $request->photo_id);

            if (!$photo) {
                return response()->json([
                    'success' => false,
                    'error' => 'Photo non trouvée'
                ], 404);
            }

            // Créer un message avec la photo comme pièce jointe
            $message = Message::create([
                'client_id' => $request->client_id,
                'profile_id' => $request->profile_id,
                'moderator_id' => $moderatorId,
                'content' => '',
                'is_from_client' => false,
            ]);

            // Créer une pièce jointe à partir de la photo de profil
            $attachment = MessageAttachment::create([
                'message_id' => $message->id,
                'file_path' => $photo->photo_path,
                'file_name' => basename($photo->photo_path),
                'mime_type' => 'image/jpeg', // Supposons que toutes les photos de profil sont des JPEG
                'size' => 0, // Taille inconnue
            ]);

            // Formater la réponse
            $messageData = [
                'id' => $message->id,
                'content' => '',
                'isFromClient' => false,
                'time' => $message->created_at->format('H:i'),
                'date' => $message->created_at->format('Y-m-d'),
                'created_at' => $message->created_at->toISOString(),
                'attachment' => [
                    'url' => asset($this->normalizeFilePath($photo->photo_path)),
                    'file_name' => basename($photo->photo_path),
                    'mime_type' => 'image/jpeg',
                ],
            ];

            // Émettre l'événement en temps réel
            //broadcast(new MessageSent($message))->toOthers();
            event(new MessageSent($message));

            return response()->json([
                'success' => true,
                'messageId' => $message->id,
                'messageData' => $messageData,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'envoi de la photo de profil', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint pour rafraîchir l'authentification WebSocket
     */
    public function refreshAuth(Request $request)
    {
        try {
            // Régénérer la session
            $request->session()->regenerate();

            // Récupérer l'utilisateur actuel
            $user = Auth::user();

            // Enregistrer la nouvelle connexion dans le service de surveillance
            if ($user) {
                $connectionId = $request->input('connection_id', uniqid('conn_'));
                $this->webSocketHealthService->registerConnection(
                    $user->id,
                    $user->type,
                    $connectionId
                );
            }

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'type' => $user->type
                ],
                'connection_id' => $connectionId ?? null,
                'timestamp' => now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            $this->logAuthError('Erreur lors du rafraîchissement de l\'authentification WebSocket', [
                'exception' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erreur d\'authentification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Diagnostic des connexions WebSocket
     */
    public function diagnosticWebSocket(Request $request)
    {
        try {
            // Récupérer l'utilisateur actuel
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Récupérer les connexions de l'utilisateur
            $connections = $this->webSocketHealthService->getUserConnections($user->id, $user->type);

            // Vérifier la santé globale des WebSockets
            $healthCheck = $this->webSocketHealthService->checkHealth();

            // Collecter des informations supplémentaires pour le diagnostic
            $diagnosticData = [
                'user' => [
                    'id' => $user->id,
                    'type' => $user->type,
                    'name' => $user->name
                ],
                'connections' => $connections,
                'system_health' => $healthCheck,
                'reverb_status' => 'status_check_not_available', // Simplification car la méthode n'est pas accessible
                'server_info' => [
                    'php_version' => phpversion(),
                    'laravel_version' => app()->version(),
                    'memory_usage' => memory_get_usage(true),
                    'server_time' => now()->toDateTimeString()
                ]
            ];

            // Si le serveur Reverb n'est pas en cours d'exécution, tenter de le redémarrer
            if ($healthCheck['status'] === 'unhealthy') {
                $restartResult = $this->webSocketHealthService->restartReverbIfNeeded();
                $diagnosticData['reverb_restart'] = $restartResult ? 'success' : 'failed';
            }

            return response()->json([
                'success' => true,
                'diagnostic' => $diagnosticData
            ]);
        } catch (\Exception $e) {
            $this->logAuthError('Erreur lors du diagnostic WebSocket', [
                'exception' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erreur de diagnostic: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Journalisation détaillée des erreurs d'authentification
     */
    protected function logAuthError($message, $context = [])
    {
        // Récupérer des informations supplémentaires sur la requête
        $requestInfo = [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'timestamp' => now()->toDateTimeString()
        ];

        // Récupérer l'utilisateur si disponible
        $user = Auth::user();
        if ($user) {
            $requestInfo['user'] = [
                'id' => $user->id,
                'type' => $user->type,
                'name' => $user->name
            ];
        }

        // Fusionner les contextes
        $fullContext = array_merge($context, ['request' => $requestInfo]);

        // Journaliser l'erreur avec tous les détails
        Log::error('Erreur d\'authentification WebSocket: ' . $message, $fullContext);

        // Si configuré, envoyer une alerte aux administrateurs
        if (config('app.env') === 'production') {
            // Implémentation d'alertes (email, SMS, Slack, etc.)
        }

        return $fullContext;
    }

    /**
     * Endpoint pour le heartbeat du modérateur
     * Cette méthode permet de suivre l'activité du modérateur en temps réel
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function heartbeat()
    {
        $user = Auth::user();

        if (!$user || $user->type !== 'moderateur') {
            return response()->json([
                'success' => false,
                'error' => 'Non autorisé'
            ], 403);
        }

        // Mettre à jour le statut en ligne et la dernière activité
        User::where('id', $user->id)->update([
            'is_online' => true,
            'last_online_at' => now()
        ]);

        // Mettre à jour l'activité dans le service WebSocket
        $this->webSocketHealthService->updateActivity(
            request()->header('X-Socket-ID') ?? uniqid('conn_')
        );

        return response()->json([
            'success' => true,
            'timestamp' => now()->toIso8601String(),
            'is_online' => true,
            'last_online_at' => now()->toIso8601String()
        ]);
    }

    // Ajouter les nouveaux endpoints

    /**
     * Signale une activité de frappe
     */
    /* public function recordTyping(Request $request)
    {
        $validated = $request->validate([
            'profile_id' => 'required|integer',
            'client_id' => 'required|integer',
        ]);

        $activityService = app(ModeratorActivityService::class);
        $activityService->recordTypingActivity(
            Auth::id(),
            $validated['profile_id'],
            $validated['client_id']
        );

        return response()->json(['status' => 'success']);
    } */

    public function recordTyping(Request $request)
    {
        $validated = $request->validate([
            'profile_id' => 'required|integer',
            'client_id' => 'required|integer',
        ]);

        $activityService = app(ModeratorActivityService::class);
        $activityService->recordTypingActivity(
            Auth::id(),
            $validated['profile_id'],
            $validated['client_id']
        );

        // Retourner immédiatement sans appeler d'autres services
        return response()->json(['status' => 'success']);
    }

    /**
     * Demande un délai avant changement de profil
     */
    public function requestDelay(Request $request)
    {
        $validated = $request->validate([
            'profile_id' => 'required|integer',
            'minutes' => 'integer|min:1|max:15',
        ]);

        $minutes = $validated['minutes'] ?? 5;

        $activityService = app(ModeratorActivityService::class);
        $success = $activityService->requestDelay(
            Auth::id(),
            $validated['profile_id'],
            $minutes
        );

        return response()->json([
            'status' => $success ? 'success' : 'error',
            'message' => $success ? 'Délai accordé' : 'Impossible d\'accorder un délai'
        ]);
    }

    /**
     * Vérifie si un profil est partagé entre plusieurs modérateurs
     *
     * @param int $profileId L'ID du profil à vérifier
     * @return \Illuminate\Http\JsonResponse
     */
    public function isProfileShared($profileId)
    {
        try {
            Log::info("[DEBUG] Vérification si le profil est partagé", [
                'profile_id' => $profileId
            ]);

            $isShared = ModeratorProfileAssignment::where('profile_id', $profileId)
                ->where('is_active', true)
                ->count() > 1;

            $activeModeratorCount = ModeratorProfileAssignment::where('profile_id', $profileId)
                ->where('is_active', true)
                ->count();

            $activeModeratorIds = ModeratorProfileAssignment::where('profile_id', $profileId)
                ->where('is_active', true)
                ->pluck('user_id')
                ->toArray();

            Log::info("[DEBUG] Résultat de la vérification", [
                'is_shared' => $isShared,
                'active_moderator_count' => $activeModeratorCount,
                'active_moderator_ids' => $activeModeratorIds
            ]);

            return response()->json([
                'isShared' => $isShared,
                'activeModeratorCount' => $activeModeratorCount,
                'activeModeratorIds' => $activeModeratorIds
            ]);
        } catch (\Exception $e) {
            Log::error("[DEBUG] Erreur lors de la vérification du partage de profil", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Une erreur est survenue lors de la vérification du partage de profil'
            ], 500);
        }
    }

    /**
     * Met à jour l'activité du modérateur
     */
    public function updateActivity(Request $request)
    {
        $validated = $request->validate([
            'profile_id' => 'required|integer',
            'client_id' => 'required|integer',
            'activity_type' => 'required|string',
        ]);

        try {
            // Mettre à jour l'activité
            $assignment = ModeratorProfileAssignment::where('user_id', auth::id())
                ->where('profile_id', $validated['profile_id'])
                ->where('is_active', true)
                ->first();

            if ($assignment) {
                // Mettre à jour le timestamp de dernière activité
                $assignment->last_activity = now();

                // Si c'est un message envoyé, mettre à jour le timestamp spécifique
                if ($validated['activity_type'] === 'message_sent') {
                    $assignment->last_message_sent = now();
                }

                $assignment->save();

                return response()->json(['success' => true]);
            }

            return response()->json(['success' => false, 'message' => 'Aucune attribution trouvée']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint de diagnostic pour vérifier l'état des assignations de profils et des modérateurs
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function diagnosticStatus()
    {
        try {
            // Récupérer tous les modérateurs actifs
            $moderators = \App\Models\User::where('type', 'moderateur')
                ->where('status', 'active')
                ->where('is_online', true)
                ->get();

            $diagnosticData = [];

            foreach ($moderators as $moderator) {
                // Récupérer les assignations actives du modérateur
                $activeAssignments = \App\Models\ModeratorProfileAssignment::where('user_id', $moderator->id)
                    ->where('is_active', true)
                    ->with('profile')
                    ->get();

                // Récupérer la dernière activité du modérateur
                $lastActivity = $activeAssignments->max('last_activity');

                // Vérifier si le modérateur est inactif (plus de 1 minute sans activité)
                $isInactive = $lastActivity ? $lastActivity->diffInMinutes(now()) > 1 : true;

                // Récupérer les profils avec des messages en attente pour ce modérateur
                $profilesWithPendingMessages = [];

                foreach ($activeAssignments as $assignment) {
                    $hasPendingMessages = \App\Models\Message::where('profile_id', $assignment->profile_id)
                        ->where('is_from_client', true)
                        ->whereNull('read_at')
                        ->exists();

                    if ($hasPendingMessages) {
                        $profilesWithPendingMessages[] = $assignment->profile_id;
                    }
                }

                $diagnosticData[] = [
                    'moderator_id' => $moderator->id,
                    'moderator_name' => $moderator->name,
                    'is_online' => $moderator->is_online,
                    'active_assignments' => $activeAssignments->map(function ($assignment) {
                        return [
                            'assignment_id' => $assignment->id,
                            'profile_id' => $assignment->profile_id,
                            'profile_name' => $assignment->profile->name ?? 'Inconnu',
                            'is_primary' => $assignment->is_primary,
                            'last_activity' => $assignment->last_activity ? $assignment->last_activity->diffForHumans() : 'jamais',
                            'last_activity_timestamp' => $assignment->last_activity,
                        ];
                    }),
                    'is_inactive' => $isInactive,
                    'profiles_with_pending_messages' => $profilesWithPendingMessages,
                    'in_queue' => \App\Models\ModeratorQueue::where('moderator_id', $moderator->id)
                        ->where('status', 'waiting')
                        ->exists(),
                ];
            }

            // Ajouter des informations sur les profils avec des messages en attente
            $pendingProfiles = app(\App\Services\ModeratorAssignmentService::class)->getProfilesWithPendingMessages();

            return response()->json([
                'moderators' => $diagnosticData,
                'pending_profiles' => $pendingProfiles,
                'timestamp' => now()->toDateTimeString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la récupération des données de diagnostic',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }
}
