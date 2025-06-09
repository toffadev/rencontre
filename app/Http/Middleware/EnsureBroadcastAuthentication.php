<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EnsureBroadcastAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // Log de debug pour comprendre la requête
            Log::info('[BROADCAST_AUTH] Requête d\'autorisation reçue', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'headers' => $request->headers->all(),
                'data' => $request->all(),
                'user_id' => Auth::id(),
                'is_authenticated' => Auth::check(),
            ]);

            // Vérifier l'authentification
            if (!Auth::check()) {
                Log::warning('[BROADCAST_AUTH] Utilisateur non authentifié');
                return response()->json([
                    'error' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Vérifier la présence des données requises
            if (!$request->has(['socket_id', 'channel_name'])) {
                Log::warning('[BROADCAST_AUTH] Données manquantes', [
                    'socket_id' => $request->input('socket_id'),
                    'channel_name' => $request->input('channel_name')
                ]);
                return response()->json([
                    'error' => 'Données d\'autorisation manquantes'
                ], 400);
            }

            // Vérifier le format du channel_name
            $channelName = $request->input('channel_name');
            if (!str_starts_with($channelName, 'private-')) {
                Log::warning('[BROADCAST_AUTH] Format de canal invalide', [
                    'channel_name' => $channelName
                ]);
                return response()->json([
                    'error' => 'Format de canal invalide'
                ], 400);
            }

            $response = $next($request);

            // Log de la réponse
            Log::info('[BROADCAST_AUTH] Réponse envoyée', [
                'status' => $response->status(),
                'content' => $response->content()
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('[BROADCAST_AUTH] Erreur lors de l\'authentification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Erreur interne du serveur',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
