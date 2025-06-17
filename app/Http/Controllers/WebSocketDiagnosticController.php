<?php

namespace App\Http\Controllers;

use App\Services\WebSocketHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebSocketDiagnosticController extends Controller
{
    /**
     * Le service de santé des WebSockets
     *
     * @var \App\Services\WebSocketHealthService
     */
    protected $healthService;

    /**
     * Créer une nouvelle instance du contrôleur
     *
     * @param \App\Services\WebSocketHealthService $healthService
     * @return void
     */
    public function __construct(WebSocketHealthService $healthService)
    {
        $this->healthService = $healthService;
    }

    /**
     * Affiche le statut de santé des WebSockets
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function health()
    {
        try {
            $healthData = $this->healthService->checkHealth();

            return response()->json($healthData);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la vérification de la santé des WebSockets: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la vérification de la santé des WebSockets',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Nettoie les connexions fantômes
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function cleanup()
    {
        try {
            $cleaned = $this->healthService->cleanupGhostConnections();

            return response()->json([
                'status' => 'success',
                'message' => "{$cleaned} connexions fantômes nettoyées",
                'cleaned' => $cleaned
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors du nettoyage des connexions fantômes: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors du nettoyage des connexions fantômes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Affiche les statistiques des WebSockets
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics()
    {
        try {
            $stats = $this->healthService->collectStatistics();

            return response()->json($stats);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la collecte des statistiques WebSocket: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la collecte des statistiques WebSocket',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rafraîchit l'authentification WebSocket
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refreshAuth(Request $request)
    {
        try {
            // Générer un nouveau jeton CSRF
            $token = csrf_token();

            return response()->json([
                'status' => 'success',
                'message' => 'Authentification rafraîchie avec succès',
                'csrf_token' => $token
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors du rafraîchissement de l\'authentification: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors du rafraîchissement de l\'authentification',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
