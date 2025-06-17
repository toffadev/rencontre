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

        // Enregistrer la connexion WebSocket du modérateur
        if (Auth::check()) {
            $this->webSocketHealthService->registerConnection(
                Auth::id(),
                Auth::user()->type,
                request()->header('X-Socket-ID') ?? uniqid('conn_')
            );
        }

        // Passer explicitement l'utilisateur à Inertia
        $user = Auth::user();

        return Inertia::render('Moderator', [
            'user' => $user // Ajouter explicitement l'utilisateur
        ]);
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
                    'avatar' => $client->clientProfile?->profile_photo_url,
                    'lastMessage' => $message->content,
                    'unreadCount' => $unreadCount,
                    'createdAt' => $message->created_at,
                    'lastMessageAt' => $message->created_at, // Ajouté pour le tri
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

        // Mettre à jour l'activité du modérateur dans le service de surveillance
        if (Auth::check()) {
            $this->webSocketHealthService->updateActivity(
                request()->header('X-Socket-ID') ?? uniqid('conn_')
            );
        }

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
        $moderator = Auth::user();

        Log::info("[DEBUG] Récupération du profil pour le modérateur", [
            'moderator_id' => $moderator->id,
            'moderator_name' => $moderator->name,
            'moderator_type' => $moderator->type,
        ]);

        // Récupérer tous les profils attribués
        $profiles = $this->assignmentService->getAllAssignedProfiles($moderator);

        Log::info("[DEBUG] Profils attribués récupérés", [
            'profiles_count' => $profiles->count(),
            'profiles_ids' => $profiles->pluck('id')->toArray(),
        ]);

        // Trouver le profil principal (si existe)
        $primaryAssignment = ModeratorProfileAssignment::where('user_id', $moderator->id)
            ->where('is_active', true)
            ->where('is_primary', true)
            ->first();

        $primaryProfileId = $primaryAssignment ? $primaryAssignment->profile_id : null;

        Log::info("[DEBUG] Recherche du profil principal", [
            'primary_profile_id' => $primaryProfileId,
            'primary_assignment_found' => $primaryAssignment ? true : false
        ]);

        // Si aucun profil principal n'est défini mais des profils sont attribués, en définir un comme principal
        if (!$primaryAssignment && $profiles->isNotEmpty()) {
            Log::info("[DEBUG] Aucun profil principal défini mais des profils sont attribués");

            // Prendre le premier profil disponible et le définir comme principal
            $firstProfileId = $profiles->first()->id;

            try {
                DB::transaction(function () use ($moderator, $firstProfileId) {
                    // Désactiver tous les profils principaux existants (pour être sûr)
                    ModeratorProfileAssignment::where('user_id', $moderator->id)
                        ->where('is_primary', true)
                        ->update(['is_primary' => false]);

                    // Définir ce profil comme principal
                    ModeratorProfileAssignment::where('user_id', $moderator->id)
                        ->where('profile_id', $firstProfileId)
                        ->where('is_active', true)
                        ->update(['is_primary' => true]);
                });

                $primaryProfileId = $firstProfileId;

                Log::info("[DEBUG] Profil défini comme principal", [
                    'profile_id' => $primaryProfileId
                ]);
            } catch (\Exception $e) {
                Log::error("[DEBUG] Erreur lors de la définition du profil principal", [
                    'error' => $e->getMessage()
                ]);
            }

            // Rafraîchir la liste des profils après cette modification
            $profiles = $this->assignmentService->getAllAssignedProfiles($moderator);
        }

        // Si aucun profil n'est attribué, en attribuer un automatiquement et le définir comme principal
        if ($profiles->isEmpty()) {
            Log::info("[DEBUG] Aucun profil attribué, tentative d'attribution automatique");

            $assignment = $this->assignmentService->assignProfileToModerator($moderator, null, true);

            if ($assignment) {
                Log::info("[DEBUG] Profil attribué automatiquement", [
                    'assignment_id' => $assignment->id,
                    'profile_id' => $assignment->profile_id
                ]);

                $profiles = $this->assignmentService->getAllAssignedProfiles($moderator);
                $primaryProfileId = $assignment->profile_id;
            } else {
                Log::warning("[DEBUG] Échec de l'attribution automatique d'un profil");
            }
        } else {
            // Mettre à jour la dernière activité
            $this->assignmentService->updateLastActivity($moderator);
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

        // Log final
        Log::info("[DEBUG] Résultat final de getAssignedProfile", [
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
            broadcast(new MessageSent($message))->toOthers();

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
            broadcast(new MessageSent($message))->toOthers();

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
                'reverb_status' => $this->webSocketHealthService->isReverbRunning() ? 'running' : 'not_running',
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
}
