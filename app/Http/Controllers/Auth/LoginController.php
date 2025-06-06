<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LoginController extends Controller
{
    /**
     * Show the login form
     *
     * @return \Inertia\Response
     */
    public function showLoginForm()
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Handle a login request
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            $user = Auth::user();

            // Generate token for API access
            $token = $user->createToken('auth_token')->plainTextToken;

            // Redirection selon le type d'utilisateur
            if ($user->type === 'moderateur') {
                return redirect()->route('moderator.chat');
            } elseif ($user->type === 'client') {
                return redirect()->route('client.home');
            }

            // Si c'est un admin qui essaie de se connecter via le formulaire client
            if ($user->type === 'admin') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('admin.login')
                    ->withErrors(['email' => 'Les administrateurs doivent utiliser le formulaire de connexion administrateur.']);
            }

            return redirect()->route('home');
        }

        return back()->withErrors([
            'email' => 'Les identifiants fournis ne correspondent pas à nos enregistrements.',
        ])->withInput($request->except('password'));
    }

    /**
     * Log the user out
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function logout(Request $request)
    {
        $userType = Auth::user()->type;

        // Revoke all user's tokens
        if (Auth::check()) {
            Auth::user()->tokens()->delete();
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Rediriger vers la page de connexion appropriée
        if ($userType === 'admin') {
            return redirect()->route('admin.login');
        }

        return redirect()->route('login');
    }
}
