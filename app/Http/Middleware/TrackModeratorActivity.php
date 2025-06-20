<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TrackModeratorActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Passer la requête au middleware suivant
        $response = $next($request);

        // Vérifier si l'utilisateur est authentifié et est un modérateur
        if (Auth::check() && Auth::user()->type === 'moderateur') {
            $moderator = Auth::user();

            // Mettre à jour le statut en ligne et la dernière activité
            $moderator->last_online_at = now();

            // Si le modérateur n'est pas déjà marqué comme en ligne, le faire
            if (!$moderator->is_online) {
                $moderator->is_online = true;
            }

            // Sauvegarder les changements sans déclencher les événements
            $moderator->saveQuietly();
        }

        return $response;
    }
}
