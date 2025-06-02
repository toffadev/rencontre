<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModeratorStatistic;
use App\Models\User;
use App\Models\ModeratorProfileAssignment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Http\JsonResponse;

class ModeratorPerformanceController extends Controller
{
    /**
     * Display the moderator performance page
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        return Inertia::render('ModeratorPerformance');
    }

    /**
     * Get moderator performance data
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getData(Request $request): JsonResponse
    {
        try {
            // Définir la période en fonction du filtre
            $startDate = now();
            $endDate = now();

            switch ($request->period) {
                case 'today':
                    $startDate = now()->startOfDay();
                    $endDate = now()->endOfDay();
                    break;
                case 'yesterday':
                    $startDate = now()->subDay()->startOfDay();
                    $endDate = now()->subDay()->endOfDay();
                    break;
                case 'week':
                    $startDate = now()->startOfWeek();
                    $endDate = now()->endOfWeek();
                    break;
                case 'month':
                    $startDate = now()->startOfMonth();
                    $endDate = now()->endOfMonth();
                    break;
                case 'custom':
                    // Si des dates personnalisées sont fournies
                    if ($request->start_date && $request->end_date) {
                        $startDate = Carbon::parse($request->start_date)->startOfDay();
                        $endDate = Carbon::parse($request->end_date)->endOfDay();
                    }
                    break;
                default:
                    // Par défaut, on prend les 30 derniers jours
                    $startDate = now()->subDays(30)->startOfDay();
                    $endDate = now()->endOfDay();
            }

            // Log pour le débogage
            Log::debug('Période sélectionnée:', [
                'period' => $request->period,
                'start_date' => $startDate->format('Y-m-d H:i:s'),
                'end_date' => $endDate->format('Y-m-d H:i:s')
            ]);

            // Récupérer les données de performance
            $moderators = User::where('type', 'moderateur')
                ->when($request->moderator_id, function ($query) use ($request) {
                    return $query->where('id', $request->moderator_id);
                })
                ->with(['moderatorProfileAssignments.profile'])
                ->get();

            $totalMessages = 0;
            $totalPoints = 0;
            $totalEarnings = 0;
            $formattedModerators = [];

            foreach ($moderators as $moderator) {
                // Statistiques de base
                $stats = ModeratorStatistic::where('user_id', $moderator->id)
                    ->whereBetween('stats_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                    ->when($request->profile_id, function ($query) use ($request) {
                        return $query->where('profile_id', $request->profile_id);
                    })
                    ->selectRaw('
                        COALESCE(SUM(short_messages_count), 0) as total_short_messages,
                        COALESCE(SUM(long_messages_count), 0) as total_long_messages,
                        COALESCE(SUM(points_received), 0) as total_points_received,
                        COALESCE(SUM(earnings), 0) as total_earnings
                    ')
                    ->first();

                // Calculer le temps de réponse moyen à partir de la table messages
                $avgResponseTime = DB::table('messages as client_messages')
                    ->join('messages as mod_messages', function ($join) use ($moderator) {
                        $join->on('client_messages.client_id', '=', 'mod_messages.client_id')
                            ->where('mod_messages.moderator_id', '=', $moderator->id)
                            ->whereRaw('mod_messages.created_at > client_messages.created_at');
                    })
                    ->where('client_messages.is_from_client', true)
                    ->where('mod_messages.is_from_client', false)
                    ->whereBetween('client_messages.created_at', [$startDate, $endDate])
                    ->avg(DB::raw('TIMESTAMPDIFF(SECOND, client_messages.created_at, mod_messages.created_at)')) ?? 0;

                if ($stats) {
                    $totalMessages += ($stats->total_short_messages + $stats->total_long_messages);
                    $totalPoints += $stats->total_points_received;
                    $totalEarnings += $stats->total_earnings;

                    $moderatorStats = [
                        'messages' => (int)($stats->total_short_messages + $stats->total_long_messages),
                        'avgResponseTime' => (int)$avgResponseTime,
                        'points' => (int)$stats->total_points_received,
                        'earnings' => (float)$stats->total_earnings,
                    ];

                    $moderatorStats['performance'] = $this->calculatePerformanceLevel($moderatorStats);

                    $formattedModerators[] = [
                        'id' => $moderator->id,
                        'name' => $moderator->name,
                        'email' => $moderator->email,
                        'avatar' => null,
                        'stats' => $moderatorStats,
                        'profiles' => $moderator->moderatorProfileAssignments->map(function ($assignment) {
                            return [
                                'id' => $assignment->profile->id,
                                'name' => $assignment->profile->name,
                                'photo' => $assignment->profile->main_photo_path,
                                'is_primary' => $assignment->is_primary
                            ];
                        })
                    ];
                }
            }

            // Calculer la moyenne globale du temps de réponse
            $globalAvgResponseTime = collect($formattedModerators)->avg('stats.avgResponseTime');

            // Filtrer par niveau de performance si demandé
            if ($request->performance_level) {
                $formattedModerators = array_filter($formattedModerators, function ($moderator) use ($request) {
                    return $this->matchesPerformanceLevel($moderator['stats']['performance'], $request->performance_level);
                });
            }

            return response()->json([
                'stats' => [
                    'totalMessages' => (int)$totalMessages,
                    'avgResponseTime' => (int)$globalAvgResponseTime,
                    'totalPoints' => (int)$totalPoints,
                    'totalEarnings' => (float)$totalEarnings
                ],
                'trends' => [
                    'messages' => $this->calculateTrend($totalMessages),
                    'responseTime' => $this->calculateTrend($globalAvgResponseTime),
                    'points' => $this->calculateTrend($totalPoints),
                    'earnings' => $this->calculateTrend($totalEarnings)
                ],
                'chartData' => $this->generateChartData($formattedModerators),
                'moderators' => array_values($formattedModerators),
                'pagination' => [
                    'currentPage' => (int)$request->input('page', 1),
                    'lastPage' => ceil(count($formattedModerators) / 10),
                    'perPage' => 10,
                    'total' => count($formattedModerators)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans getData: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['error' => 'Une erreur est survenue lors du chargement des données'], 500);
        }
    }

    private function matchesPerformanceLevel($performance, $level)
    {
        $levels = [
            'top' => ['Excellent'],
            'average' => ['Bon'],
            'low' => ['Moyen', 'Faible']
        ];

        return in_array($performance, $levels[$level] ?? []);
    }

    private function calculatePerformanceLevel($stats)
    {
        $score = 0;

        // Points basés sur le nombre de messages
        if ($stats['messages'] > 500) $score += 3;
        elseif ($stats['messages'] > 300) $score += 2;
        elseif ($stats['messages'] > 100) $score += 1;

        // Points basés sur le temps de réponse moyen (en secondes)
        if ($stats['avgResponseTime'] < 120) $score += 3;
        elseif ($stats['avgResponseTime'] < 300) $score += 2;
        elseif ($stats['avgResponseTime'] < 600) $score += 1;

        // Points basés sur les points gagnés
        if ($stats['points'] > 5000) $score += 3;
        elseif ($stats['points'] > 3000) $score += 2;
        elseif ($stats['points'] > 1000) $score += 1;

        // Déterminer le niveau de performance
        if ($score >= 7) return 'Excellent';
        if ($score >= 5) return 'Bon';
        if ($score >= 3) return 'Moyen';
        return 'Faible';
    }

    private function calculateTrend($value)
    {
        // Pour l'instant, on retourne une tendance aléatoire
        // À remplacer par un vrai calcul basé sur les données historiques
        $trend = rand(-10, 10);
        return ($trend >= 0 ? '+' : '') . $trend . '%';
    }

    private function generateChartData($moderators)
    {
        $labels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
        $shortMessages = array_fill(0, 7, 0);
        $longMessages = array_fill(0, 7, 0);
        $responseTimes = array_fill(0, 7, 0);

        // Dans une vraie implémentation, vous devriez agréger les données par jour
        // Pour l'instant, on utilise des données aléatoires
        for ($i = 0; $i < 7; $i++) {
            $shortMessages[$i] = rand(20, 50);
            $longMessages[$i] = rand(15, 35);
            $responseTimes[$i] = rand(20, 40) / 10;
        }

        return [
            'messages' => [
                'labels' => $labels,
                'short' => $shortMessages,
                'long' => $longMessages
            ],
            'responseTime' => [
                'labels' => $labels,
                'data' => $responseTimes
            ]
        ];
    }

    /**
     * Export moderator performance data
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function export(Request $request): JsonResponse
    {
        // Pour l'instant, nous retournons un message d'erreur
        return response()->json(['message' => 'Export not implemented yet'], 501);
    }

    public function getPerformanceData(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'moderator_id' => 'nullable|exists:users,id',
            'profile_id' => 'nullable|exists:profiles,id',
            'performance_level' => 'nullable|in:top,average,low'
        ]);

        // Définir la période
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::now()->subDays(30);
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::now();

        // Requête de base pour les modérateurs
        $moderatorsQuery = User::where('type', 'moderateur')
            ->with(['moderatorProfileAssignments.profile']);

        // Filtrer par modérateur spécifique si demandé
        if ($request->moderator_id) {
            $moderatorsQuery->where('id', $request->moderator_id);
        }

        $moderators = $moderatorsQuery->get();

        // Collecter les données de performance pour chaque modérateur
        $performanceData = [];
        foreach ($moderators as $moderator) {
            // Statistiques de base
            $stats = ModeratorStatistic::where('user_id', $moderator->id)
                ->whereBetween('stats_date', [$startDate, $endDate]);

            // Filtrer par profil si demandé
            if ($request->profile_id) {
                $stats->where('profile_id', $request->profile_id);
            }

            $stats = $stats->select([
                DB::raw('SUM(short_messages_count) as total_short_messages'),
                DB::raw('SUM(long_messages_count) as total_long_messages'),
                DB::raw('SUM(points_received) as total_points_received'),
                DB::raw('SUM(earnings) as total_earnings'),
                DB::raw('AVG(
                    CASE 
                        WHEN (short_messages_count + long_messages_count) > 0 
                        THEN (long_messages_count * 100.0) / (short_messages_count + long_messages_count)
                        ELSE 0 
                    END
                ) as quality_rate')
            ])->first();

            // Temps de réponse moyen
            $responseTime = DB::table('messages as client_messages')
                ->join('messages as mod_messages', function ($join) use ($moderator) {
                    $join->on('client_messages.client_id', '=', 'mod_messages.client_id')
                        ->where('mod_messages.moderator_id', '=', $moderator->id)
                        ->whereRaw('mod_messages.created_at > client_messages.created_at');
                })
                ->where('client_messages.is_from_client', true)
                ->where('mod_messages.is_from_client', false)
                ->whereBetween('client_messages.created_at', [$startDate, $endDate])
                ->select(DB::raw('AVG(TIMESTAMPDIFF(SECOND, client_messages.created_at, mod_messages.created_at)) as avg_response_time'))
                ->first();

            // Clients simultanés maximum
            $maxSimultaneousClients = DB::table('messages')
                ->where('moderator_id', $moderator->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(DB::raw('COUNT(DISTINCT client_id) as client_count'))
                ->groupBy(DB::raw('DATE(created_at), HOUR(created_at)'))
                ->orderByDesc('client_count')
                ->first();

            // Profils assignés
            $assignedProfiles = $moderator->moderatorProfileAssignments()
                ->with('profile')
                ->where('is_active', true)
                ->get();

            $performanceData[] = [
                'moderator' => [
                    'id' => $moderator->id,
                    'name' => $moderator->name,
                ],
                'profiles' => $assignedProfiles->map(fn($assignment) => [
                    'id' => $assignment->profile->id,
                    'name' => $assignment->profile->name,
                    'photo' => $assignment->profile->main_photo_path,
                    'is_primary' => $assignment->is_primary
                ]),
                'statistics' => [
                    'total_messages' => ($stats->total_short_messages ?? 0) + ($stats->total_long_messages ?? 0),
                    'short_messages' => $stats->total_short_messages ?? 0,
                    'long_messages' => $stats->total_long_messages ?? 0,
                    'points_earned_short' => ($stats->total_short_messages ?? 0) * 25,
                    'points_earned_long' => ($stats->total_long_messages ?? 0) * 50,
                    'points_received' => $stats->total_points_received ?? 0,
                    'total_earnings' => $stats->total_earnings ?? 0,
                    'quality_rate' => round($stats->quality_rate ?? 0, 2),
                    'avg_response_time' => round($responseTime->avg_response_time ?? 0),
                    'max_simultaneous_clients' => $maxSimultaneousClients->client_count ?? 0
                ]
            ];
        }

        // Filtrer par niveau de performance si demandé
        if ($request->performance_level) {
            $performanceData = $this->filterByPerformanceLevel($performanceData, $request->performance_level);
        }

        // Trier par total de messages par défaut
        usort($performanceData, function ($a, $b) {
            return $b['statistics']['total_messages'] - $a['statistics']['total_messages'];
        });

        return response()->json([
            'data' => $performanceData,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ]
        ]);
    }

    private function filterByPerformanceLevel($data, $level)
    {
        // Calculer les seuils basés sur le total des messages
        $messagesCounts = array_map(fn($item) => $item['statistics']['total_messages'], $data);
        sort($messagesCounts);
        $count = count($messagesCounts);

        $thresholds = [
            'top' => $messagesCounts[floor($count * 0.7)], // Top 30%
            'average' => $messagesCounts[floor($count * 0.3)], // Moyenne 40%
        ];

        return array_filter($data, function ($item) use ($level, $thresholds) {
            $messages = $item['statistics']['total_messages'];
            return match ($level) {
                'top' => $messages >= $thresholds['top'],
                'average' => $messages >= $thresholds['average'] && $messages < $thresholds['top'],
                'low' => $messages < $thresholds['average'],
                default => true
            };
        });
    }

    public function updateAssignment(Request $request)
    {
        $request->validate([
            'moderator_id' => 'required|exists:users,id',
            'profile_id' => 'required|exists:profiles,id',
            'is_active' => 'required|boolean',
            'is_primary' => 'required|boolean'
        ]);

        try {
            $assignment = ModeratorProfileAssignment::updateOrCreate(
                [
                    'user_id' => $request->moderator_id,
                    'profile_id' => $request->profile_id,
                ],
                [
                    'is_active' => $request->is_active,
                    'is_primary' => $request->is_primary,
                    'last_activity' => now()
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Assignment updated successfully',
                'assignment' => $assignment
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
