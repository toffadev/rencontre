<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Services\WebSocketHealthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    /**
     * Le service de surveillance des WebSockets.
     *
     * @var \App\Services\WebSocketHealthService
     */
    protected $webSocketHealthService;

    /**
     * Créer une nouvelle instance du contrôleur.
     *
     * @param  \App\Services\WebSocketHealthService  $webSocketHealthService
     * @return void
     */
    public function __construct(WebSocketHealthService $webSocketHealthService)
    {
        $this->webSocketHealthService = $webSocketHealthService;
    }

    /**
     * Display the home page with active profiles
     *
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $clientProfile = $user->clientProfile;

        // Redirect to profile setup if not completed
        if (!$clientProfile || !$clientProfile->profile_completed) {
            return redirect()->route('profile.setup');
        }

        // Traiter les paramètres UTM pour les notifications
        if ($request->has('notification_id')) {
            $notificationId = $request->input('notification_id');
            $notification = \App\Models\ClientNotification::find($notificationId);

            if ($notification && $notification->user_id === $user->id && !$notification->opened_at) {
                $notification->markAsOpened();

                // Si la notification est liée à un message, rediriger vers ce message
                if ($notification->message_id) {
                    $message = $notification->message;
                    // Stocker l'ID du profil pour l'ouvrir automatiquement
                    session()->flash('open_profile_id', $message->profile_id);
                }
            }
        }

        // Get active profiles with their photos and user (moderator)
        $profiles = Profile::with(['photos', 'mainPhoto', 'user'])
            ->where('status', 'active')
            ->where('gender', $clientProfile->seeking_gender) // Filter by gender preference
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($profile) {
                // S'assurer que toutes les relations sont chargées
                $profile->load(['user']);

                return array_merge($profile->toArray(), [
                    'user' => $profile->user ? [
                        'id' => $profile->user->id,
                        'name' => $profile->user->name,
                        'type' => $profile->user->type
                    ] : null
                ]);
            });

        // Enregistrer la connexion WebSocket du client
        if (Auth::check()) {
            $this->webSocketHealthService->registerConnection(
                Auth::id(),
                Auth::user()->type,
                request()->header('X-Socket-ID') ?? uniqid('conn_')
            );
        }

        return Inertia::render('Home', [
            'profiles' => $profiles,
            'user' => $user,
            'openProfileId' => session('open_profile_id'),
        ]);
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
            Log::error('Erreur lors du rafraîchissement de l\'authentification WebSocket: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Erreur d\'authentification: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérifier l'état de la connexion WebSocket
     */
    public function checkConnectionHealth(Request $request)
    {
        $connectionId = $request->header('X-Socket-ID') ?? $request->input('connection_id');

        if (!$connectionId) {
            return response()->json([
                'success' => false,
                'error' => 'Identifiant de connexion manquant'
            ], 400);
        }

        try {
            // Mettre à jour l'activité de la connexion
            $updated = $this->webSocketHealthService->updateActivity($connectionId);

            return response()->json([
                'success' => $updated,
                'connection_id' => $connectionId,
                'timestamp' => now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la vérification de la santé de la connexion: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Erreur lors de la vérification de la connexion: ' . $e->getMessage()
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

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'type' => $user->type,
                    'name' => $user->name
                ],
                'connections' => $connections,
                'health_status' => $healthCheck['status'],
                'timestamp' => now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors du diagnostic WebSocket: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Erreur de diagnostic: ' . $e->getMessage()
            ], 500);
        }
    }
}
