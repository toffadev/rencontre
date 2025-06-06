<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ClientOnlyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check() || Auth::user()->type !== 'client') {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Accès non autorisé'], 403);
            }

            if (Auth::check() && Auth::user()->type === 'moderateur') {
                return redirect()->route('moderator.chat');
            }

            return redirect()->route('login')->with('error', 'Cette page est réservée aux clients.');
        }

        return $next($request);
    }
}
