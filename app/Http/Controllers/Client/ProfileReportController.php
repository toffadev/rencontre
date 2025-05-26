<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ProfileReport;
use App\Models\User;
use App\Notifications\NewProfileReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class ProfileReportController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reported_user_id' => 'required|exists:users,id',
            'reason' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            $report = ProfileReport::create([
                'reporter_id' => Auth::id(),
                'reported_user_id' => $validated['reported_user_id'],
                'reason' => $validated['reason'],
                'description' => $validated['description'],
            ]);

            // Envoyer une notification aux administrateurs
            $admins = User::where('role', 'admin')->get();
            Notification::send($admins, new NewProfileReport($report));

            return response()->json([
                'message' => 'Profil signalé avec succès',
                'report' => $report
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Une erreur est survenue lors du signalement'
            ], 500);
        }
    }

    public function getBlockedProfiles()
    {
        $blockedProfileIds = ProfileReport::where('reporter_id', Auth::id())
            ->where('status', 'pending')
            ->pluck('reported_user_id')
            ->toArray();

        return response()->json([
            'blocked_profiles' => $blockedProfileIds
        ]);
    }
}
