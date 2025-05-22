<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();

                // Vérifier si la route demandée est une route d'authentification (login, register)
                // Dans ce cas, rediriger vers le tableau de bord approprié
                if ($request->routeIs('login', 'register', 'password.*', 'admin.login', 'admin.login.submit')) {
                    if ($user->type === 'admin') {
                        return redirect()->route('admin.dashboard');
                    } elseif ($user->type === 'moderateur') {
                        return redirect()->route('moderator.dashboard');
                    }

                    return redirect()->route('home');
                }

                // Si ce n'est pas une route d'authentification, laisser l'utilisateur continuer
                return $next($request);
            }
        }

        return $next($request);
    }
}
