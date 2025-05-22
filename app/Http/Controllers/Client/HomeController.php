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
        // Get active profiles with their photos
        $profiles = Profile::with(['photos', 'mainPhoto'])
            ->where('status', 'active')
            ->latest()
            ->take(10)
            ->get();

        return Inertia::render('Home', [
            'profiles' => $profiles
        ]);
    }
}
