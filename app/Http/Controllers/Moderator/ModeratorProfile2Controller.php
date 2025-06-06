<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\ModeratorStatistic;
use App\Models\Message;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class ModeratorProfileController extends Controller
{
    public function index()
    {
        return Inertia::render('Moderator/Profile');
    }

    public function getStatistics(Request $request)
    {
        $dateRange = $request->input('dateRange', 'week');
        $profileId = $request->input('profileId');
        $userId = Auth::id();

        Log::info('Récupération des statistiques', [
            'user_id' => $userId,
            'profile_id' => $profileId,
            'date_range' => $dateRange
        ]);

        // Déterminer la période
        $startDate = match ($dateRange) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfWeek()
        };

        Log::info('Période de recherche', [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d')
        ]);

        // Construire la requête de base
        $query = ModeratorStatistic::where('user_id', $userId)
            ->where('stats_date', '>=', $startDate);

        // Filtrer par profil si spécifié
        if ($profileId) {
            $query->where('profile_id', $profileId);
        }

        // Récupérer les statistiques agrégées
        $stats = $query->select([
            DB::raw('SUM(short_messages_count) as total_short_messages'),
            DB::raw('SUM(long_messages_count) as total_long_messages'),
            DB::raw('SUM(points_received) as total_points'),
            DB::raw('SUM(earnings) as total_earnings'),
            DB::raw('DATE(stats_date) as date')
        ])
            ->groupBy('stats_date')
            ->orderBy('stats_date')
            ->get();

        Log::info('Statistiques brutes trouvées', [
            'stats_count' => $stats->count(),
            'raw_stats' => $stats->toArray()
        ]);

        // Calculer les totaux
        $totals = [
            'short_messages' => $stats->sum('total_short_messages'),
            'long_messages' => $stats->sum('total_long_messages'),
            'points_received' => $stats->sum('total_points'),
            'earnings' => $stats->sum('total_earnings'),
        ];

        Log::info('Totaux calculés', $totals);

        // Calculer les moyennes quotidiennes
        $dayCount = max(1, $startDate->diffInDays(now()));
        $averages = [
            'messages_per_day' => ($totals['short_messages'] + $totals['long_messages']) / $dayCount,
            'earnings_per_day' => $totals['earnings'] / $dayCount,
        ];

        return response()->json([
            'daily_stats' => $stats,
            'totals' => $totals,
            'averages' => $averages,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => now()->format('Y-m-d')
            ]
        ]);
    }

    public function getMessageHistory(Request $request)
    {
        $userId = Auth::id();
        $limit = $request->input('limit', 50);
        $profileId = $request->input('profileId');
        $dateRange = $request->input('dateRange', 'week');

        // Déterminer la période
        $startDate = match ($dateRange) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfWeek()
        };

        $query = Message::where('moderator_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->with(['profile:id,name,main_photo_path', 'client:id,name'])
            ->orderBy('created_at', 'desc');

        if ($profileId) {
            $query->where('profile_id', $profileId);
        }

        $messages = $query->paginate($limit);

        // Calculer les statistiques des messages
        $totalMessages = $messages->total();
        $shortMessages = $messages->filter(fn($msg) => strlen($msg->content) < 10)->count();
        $longMessages = $messages->filter(fn($msg) => strlen($msg->content) >= 10)->count();
        $totalEarnings = ($shortMessages * 25) + ($longMessages * 50);

        return response()->json([
            'messages' => $messages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'length' => strlen($message->content),
                    'is_long' => strlen($message->content) >= 10,
                    'earnings' => strlen($message->content) >= 10 ? 50 : 25,
                    'is_from_client' => $message->is_from_client,
                    'profile' => [
                        'id' => $message->profile->id,
                        'name' => $message->profile->name,
                        'photo' => $message->profile->main_photo_path
                    ],
                    'client' => [
                        'id' => $message->client->id,
                        'name' => $message->client->name
                    ],
                    'created_at' => $message->created_at->format('Y-m-d H:i:s')
                ];
            }),
            'statistics' => [
                'total_messages' => $totalMessages,
                'short_messages' => $shortMessages,
                'long_messages' => $longMessages,
                'total_earnings' => $totalEarnings
            ],
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total()
            ]
        ]);
    }

    public function getPointsReceived(Request $request)
    {
        $userId = Auth::id();
        $dateRange = $request->input('dateRange', 'week');
        $profileId = $request->input('profileId');

        // Déterminer la période
        $startDate = match ($dateRange) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfWeek()
        };

        // Requête pour les points reçus
        $query = DB::table('profile_point_transactions')
            ->where('moderator_id', $userId)
            ->where('created_at', '>=', $startDate);

        if ($profileId) {
            $query->where('profile_id', $profileId);
        }

        $pointsData = $query->select([
            'profile_id',
            DB::raw('SUM(points_amount) as total_points'),
            DB::raw('COUNT(DISTINCT client_id) as unique_clients'),
            DB::raw('DATE(created_at) as date')
        ])
            ->groupBy('profile_id', 'date')
            ->orderBy('date')
            ->get();

        // Organiser les données par profil
        $profileStats = [];
        foreach ($pointsData as $data) {
            if (!isset($profileStats[$data->profile_id])) {
                $profileStats[$data->profile_id] = [
                    'total_points' => 0,
                    'unique_clients' => 0,
                    'daily_points' => []
                ];
            }

            $profileStats[$data->profile_id]['total_points'] += $data->total_points;
            $profileStats[$data->profile_id]['unique_clients'] = max(
                $profileStats[$data->profile_id]['unique_clients'],
                $data->unique_clients
            );
            $profileStats[$data->profile_id]['daily_points'][] = [
                'date' => $data->date,
                'points' => $data->total_points
            ];
        }

        return response()->json([
            'profile_stats' => $profileStats,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => now()->format('Y-m-d')
            ]
        ]);
    }
}
