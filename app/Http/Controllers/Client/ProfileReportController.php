<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ProfileReport;
use App\Models\User;
use App\Models\Profile;
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
            'reported_user_id' => 'nullable|exists:users,id',
            'reported_profile_id' => 'required|exists:profiles,id',
            'reason' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            // Vérifier si un signalement existe déjà
            $existingReport = ProfileReport::where('reporter_id', Auth::id())
                ->where('reported_profile_id', $validated['reported_profile_id'])
                ->first();

            if ($existingReport) {
                return response()->json([
                    'message' => 'Vous avez déjà signalé ce profil'
                ], 422);
            }

            $report = ProfileReport::create([
                'reporter_id' => Auth::id(),
                'reported_user_id' => $validated['reported_user_id'] ?? null,
                'reported_profile_id' => $validated['reported_profile_id'],
                'reason' => $validated['reason'],
                'description' => $validated['description'] ?? null,
                'status' => 'pending'
            ]);

            // Envoyer une notification aux administrateurs
            $admins = User::where('type', 'admin')->get();
            Notification::send($admins, new NewProfileReport($report));

            return response()->json([
                'message' => 'Profil signalé avec succès',
                'report' => $report
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Une erreur est survenue lors du signalement',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getBlockedProfiles()
    {
        // Récupérer uniquement les profils avec le statut "accepted"
        $blockedProfileIds = ProfileReport::where('reporter_id', Auth::id())
            ->where('status', 'accepted')
            ->pluck('reported_profile_id')
            ->toArray();

        $reportedProfiles = ProfileReport::where('reporter_id', Auth::id())
            ->where('status', 'pending')
            ->with('reportedProfile')
            ->get()
            ->map(function ($report) {
                return [
                    'profile_id' => $report->reported_profile_id,
                    'status' => $report->status
                ];
            });

        return response()->json([
            'blocked_profiles' => $blockedProfileIds,
            'reported_profiles' => $reportedProfiles
        ]);
    }
}
