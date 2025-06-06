<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Support\Facades\Auth;
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
        $user = auth()->user();
        $clientProfile = $user->clientProfile;

        // Redirect to profile setup if not completed
        if (!$clientProfile || !$clientProfile->profile_completed) {
            return redirect()->route('profile.setup');
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

        return Inertia::render('Home', [
            'profiles' => $profiles
        ]);
    }
}
