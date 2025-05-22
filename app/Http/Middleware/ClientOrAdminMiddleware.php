<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ClientOrAdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check() || (Auth::user()->type !== 'client' && Auth::user()->type !== 'admin')) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Accès non autorisé'], 403);
            }

            return redirect()->route('login')->with('error', 'Vous devez être connecté en tant que client ou administrateur pour accéder à cette page.');
        }

        return $next($request);
    }
}
