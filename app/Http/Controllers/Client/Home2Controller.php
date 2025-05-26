<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class HomeController extends Controller
{
    /**
     * Display the home page with active profiles
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        // Récupérer tous les profils sauf celui de l'utilisateur connecté
        $profiles = User::where('id', '!=', Auth::id())
            ->where('type', 'client')
            ->where('status', 'active')
            ->with(['clientInfo' => function ($query) {
                $query->select('user_id', 'age', 'ville', 'quartier', 'profession', 'orientation');
            }])
            ->select('id', 'name', 'type', 'status', 'points')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'age' => $user->clientInfo?->age,
                    'ville' => $user->clientInfo?->ville,
                    'quartier' => $user->clientInfo?->quartier,
                    'profession' => $user->clientInfo?->profession,
                    'orientation' => $user->clientInfo?->orientation,
                    'points' => $user->points,
                ];
            });

        return Inertia::render('Home', [
            'profiles' => $profiles
        ]);
    }
}
