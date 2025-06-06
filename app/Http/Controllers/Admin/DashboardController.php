<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Message;
use App\Models\Profile;
use App\Models\PointTransaction;
use App\Models\ProfilePointTransaction;
use App\Models\ModeratorProfileAssignment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard');
    }

    public function getStats()
    {
        try {
            $today = Carbon::today();
            $last30Days = Carbon::now()->subDays(30);

            // 1. Statistiques générales
            $generalStats = [
                'total_clients' => User::where('type', 'client')->count(),
                'active_clients' => User::where('type', 'client')
                    ->where('last_login_at', '>=', $last30Days)
                    ->count(),
                'total_moderators' => User::where('type', 'moderateur')->count(),
                'active_profiles' => Profile::where('status', 'active')->count(),
            ];

            // 2. Statistiques des messages
            $messageStats = [
                'total_messages' => Message::count(),
                'messages_today' => Message::whereDate('created_at', $today)->count(),
                'client_messages' => Message::where('is_from_client', true)->count(),
                'moderator_messages' => Message::where('is_from_client', false)->count(),
                'response_rate' => $this->calculateGlobalResponseRate(),
                'avg_response_time' => $this->calculateAverageResponseTime(),
            ];

            // 3. Statistiques financières (combinaison des deux types de transactions)
            $pointTransactions = PointTransaction::where('status', 'completed')
                ->selectRaw('SUM(money_amount) as total_revenue, SUM(points_amount) as total_points')
                ->first();

            $profileTransactions = ProfilePointTransaction::where('status', 'completed')
                ->selectRaw('SUM(money_amount) as total_revenue, SUM(points_amount) as total_points')
                ->first();

            $todayPointTransactions = PointTransaction::where('status', 'completed')
                ->whereDate('created_at', $today)
                ->selectRaw('SUM(money_amount) as revenue, SUM(points_amount) as points')
                ->first();

            $todayProfileTransactions = ProfilePointTransaction::where('status', 'completed')
                ->whereDate('created_at', $today)
                ->selectRaw('SUM(money_amount) as revenue, SUM(points_amount) as points')
                ->first();

            $financialStats = [
                'total_revenue' => ($pointTransactions->total_revenue ?? 0) + ($profileTransactions->total_revenue ?? 0),
                'revenue_today' => ($todayPointTransactions->revenue ?? 0) + ($todayProfileTransactions->revenue ?? 0),
                'total_points_sold' => ($pointTransactions->total_points ?? 0) + ($profileTransactions->total_points ?? 0),
                'points_sold_today' => ($todayPointTransactions->points ?? 0) + ($todayProfileTransactions->points ?? 0),
            ];

            // 4. Top modérateurs (avec leurs performances des 30 derniers jours)
            $topModerators = User::where('type', 'moderateur')
                ->withCount(['messages as messages_count' => function ($query) use ($last30Days) {
                    $query->where('created_at', '>=', $last30Days);
                }])
                ->with(['moderatorProfileAssignments' => function ($query) {
                    $query->where('is_active', true)->with('profile:id,name');
                }])
                ->orderByDesc('messages_count')
                ->limit(5)
                ->get()
                ->map(function ($moderator) use ($last30Days) {
                    // Calculer les points gagnés sur les 30 derniers jours
                    $pointsEarned = ProfilePointTransaction::where('moderator_id', $moderator->id)
                        ->where('status', 'completed')
                        ->where('created_at', '>=', $last30Days)
                        ->sum('points_amount');

                    return [
                        'id' => $moderator->id,
                        'name' => $moderator->name,
                        'messages_count' => $moderator->messages_count,
                        'points_earned' => $pointsEarned,
                        'response_rate' => $this->calculateModeratorResponseRate($moderator->id, $last30Days),
                        'profiles' => $moderator->moderatorProfileAssignments->map(fn($a) => [
                            'id' => $a->profile->id,
                            'name' => $a->profile->name
                        ])
                    ];
                });

            // 5. Top profils (avec leurs performances des 30 derniers jours)
            $topProfiles = Profile::withCount(['messages as messages_count' => function ($query) use ($last30Days) {
                $query->where('created_at', '>=', $last30Days);
            }])
                ->with(['moderatorProfileAssignments' => function ($query) {
                    $query->where('is_active', true)->with('user:id,name');
                }])
                ->orderByDesc('messages_count')
                ->limit(5)
                ->get()
                ->map(function ($profile) use ($last30Days) {
                    // Calculer les points gagnés sur les 30 derniers jours
                    $pointsEarned = ProfilePointTransaction::where('profile_id', $profile->id)
                        ->where('status', 'completed')
                        ->where('created_at', '>=', $last30Days)
                        ->sum('points_amount');

                    return [
                        'id' => $profile->id,
                        'name' => $profile->name,
                        'photo' => $profile->main_photo_path,
                        'messages_count' => $profile->messages_count,
                        'points_earned' => $pointsEarned,
                        'conversion_rate' => $this->calculateProfileConversionRate($profile->id, $last30Days),
                        'moderators' => $profile->moderatorProfileAssignments->map(fn($a) => [
                            'id' => $a->user->id,
                            'name' => $a->user->name
                        ])
                    ];
                });

            // 6. Transactions récentes (combinaison des deux types de transactions)
            $recentPointTransactions = PointTransaction::with(['user:id,name'])
                ->where('status', 'completed')
                ->select('id', 'user_id', 'points_amount', 'money_amount', 'created_at')
                ->orderByDesc('created_at')
                ->limit(3)
                ->get()
                ->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'type' => 'points',
                        'client_name' => $transaction->user->name,
                        'points_amount' => $transaction->points_amount,
                        'money_amount' => $transaction->money_amount,
                        'created_at' => $transaction->created_at
                    ];
                });

            $recentProfileTransactions = ProfilePointTransaction::with(['client:id,name', 'profile:id,name'])
                ->where('status', 'completed')
                ->select('id', 'client_id', 'profile_id', 'points_amount', 'money_amount', 'created_at')
                ->orderByDesc('created_at')
                ->limit(3)
                ->get()
                ->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'type' => 'profile',
                        'client_name' => $transaction->client->name,
                        'profile_name' => $transaction->profile->name,
                        'points_amount' => $transaction->points_amount,
                        'money_amount' => $transaction->money_amount,
                        'created_at' => $transaction->created_at
                    ];
                });

            $recentTransactions = $recentPointTransactions->concat($recentProfileTransactions)
                ->sortByDesc('created_at')
                ->take(5)
                ->values();

            return response()->json([
                'general' => $generalStats,
                'messages' => $messageStats,
                'financial' => $financialStats,
                'top_moderators' => $topModerators,
                'top_profiles' => $topProfiles,
                'recent_transactions' => $recentTransactions
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans DashboardController@getStats', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Une erreur est survenue lors de la récupération des statistiques',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    private function calculateModeratorResponseRate($moderatorId, $since)
    {
        $clientMessages = Message::where('is_from_client', true)
            ->whereHas('profile', function ($query) use ($moderatorId) {
                $query->whereHas('moderatorProfileAssignments', function ($q) use ($moderatorId) {
                    $q->where('user_id', $moderatorId)
                        ->where('is_active', true);
                });
            })
            ->where('created_at', '>=', $since)
            ->count();

        $moderatorResponses = Message::where('moderator_id', $moderatorId)
            ->where('is_from_client', false)
            ->where('created_at', '>=', $since)
            ->count();

        return $clientMessages > 0 ? round(($moderatorResponses / $clientMessages) * 100) : 0;
    }

    private function calculateProfileConversionRate($profileId, $since)
    {
        $totalClients = Message::where('profile_id', $profileId)
            ->where('is_from_client', true)
            ->where('created_at', '>=', $since)
            ->distinct('client_id')
            ->count();

        $convertedClients = ProfilePointTransaction::where('profile_id', $profileId)
            ->where('status', 'completed')
            ->where('created_at', '>=', $since)
            ->distinct('client_id')
            ->count();

        return $totalClients > 0 ? round(($convertedClients / $totalClients) * 100) : 0;
    }

    private function calculateGlobalResponseRate()
    {
        $clientMessages = Message::where('is_from_client', true)->count();
        $moderatorResponses = Message::where('is_from_client', false)->count();

        return $clientMessages > 0 ? round(($moderatorResponses / $clientMessages) * 100) : 0;
    }

    private function calculateAverageResponseTime()
    {
        return Message::where('is_from_client', false)
            ->whereNotNull('response_time')
            ->avg('response_time') ?? 0;
    }
}
