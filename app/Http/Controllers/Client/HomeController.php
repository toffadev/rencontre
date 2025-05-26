<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\Request;
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
        // Get active profiles with their photos and user (moderator)
        $profiles = Profile::with(['photos', 'mainPhoto', 'user'])
            ->where('status', 'active')
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

        return Inertia::render('Home', [
            'profiles' => $profiles
        ]);
    }
}
