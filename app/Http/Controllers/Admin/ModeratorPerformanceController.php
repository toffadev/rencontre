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
                    // Par défaut, on prend le mois en cours
                    $startDate = now()->startOfMonth();
                    $endDate = now()->endOfMonth();
            }

            // Log pour le débogage
            Log::debug('Période sélectionnée:', [
                'period' => $request->period,
                'start_date' => $startDate->format('Y-m-d H:i:s'),
                'end_date' => $endDate->format('Y-m-d H:i:s')
            ]);

            // Déterminer le mois précédent pour les paiements
            $lastMonthStart = now()->subMonth()->startOfMonth();
            $lastMonthEnd = now()->subMonth()->endOfMonth();

            // Déterminer le type de revenus à afficher
            $revenueType = $request->revenue_type ?? 'all';

            // Récupérer les données de performance
            $moderatorsQuery = User::where('type', 'moderateur')
                ->when($request->moderator_id, function ($query) use ($request) {
                    return $query->where('id', $request->moderator_id);
                })
                ->with(['moderatorProfileAssignments.profile']);

            // Pagination des modérateurs
            $page = $request->input('page', 1);
            $perPage = 10;
            $totalModerators = $moderatorsQuery->count();
            $moderators = $moderatorsQuery->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            $totalMessages = 0;
            $totalReceivedMessages = 0;
            $totalPoints = 0;
            $totalProfilePoints = 0;
            $totalEarnings = 0;
            $formattedModerators = [];
            $globalAvgResponseTime = 0;
            $responseTimeCount = 0;

            foreach ($moderators as $moderator) {
                // Statistiques de base pour la période sélectionnée
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

                // Initialiser les valeurs par défaut
                $totalShortMessages = $stats ? ($stats->total_short_messages ?? 0) : 0;
                $totalLongMessages = $stats ? ($stats->total_long_messages ?? 0) : 0;
                $totalPointsReceived = $stats ? ($stats->total_points_received ?? 0) : 0;
                $sentMessages = $totalShortMessages + $totalLongMessages;

                // Récupérer les messages reçus pour la période sélectionnée
                $receivedMessagesQuery = DB::table('messages')
                    ->join('moderator_profile_assignments', function ($join) use ($moderator) {
                        $join->on('messages.profile_id', '=', 'moderator_profile_assignments.profile_id')
                            ->where('moderator_profile_assignments.user_id', '=', $moderator->id);
                    })
                    ->where('messages.is_from_client', true)
                    ->whereBetween('messages.created_at', [$startDate, $endDate]);

                if ($request->profile_id) {
                    $receivedMessagesQuery->where('messages.profile_id', $request->profile_id);
                }

                $receivedMessages = $receivedMessagesQuery->count();
                $messageEarnings = $receivedMessages * 50;

                // Récupérer les points reçus par les profils pour la période sélectionnée
                $profilePointsQuery = DB::table('profile_point_transactions')
                    ->where('moderator_id', $moderator->id)
                    ->whereBetween('created_at', [$startDate, $endDate]);

                if ($request->profile_id) {
                    $profilePointsQuery->where('profile_id', $request->profile_id);
                }

                $profilePoints = $profilePointsQuery->sum('points_amount');
                $moderatorShare = $profilePoints / 2; // Le modérateur ne reçoit que la moitié des points

                // Récupérer les paiements du mois précédent
                $lastMonthReceivedMessages = DB::table('messages')
                    ->join('moderator_profile_assignments', function ($join) use ($moderator) {
                        $join->on('messages.profile_id', '=', 'moderator_profile_assignments.profile_id')
                            ->where('moderator_profile_assignments.user_id', '=', $moderator->id);
                    })
                    ->where('messages.is_from_client', true)
                    ->whereBetween('messages.created_at', [$lastMonthStart, $lastMonthEnd])
                    ->count();

                $lastMonthMessageEarnings = $lastMonthReceivedMessages * 50;

                $lastMonthProfilePoints = DB::table('profile_point_transactions')
                    ->where('moderator_id', $moderator->id)
                    ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
                    ->sum('points_amount');

                $lastMonthModeratorShare = $lastMonthProfilePoints / 2;
                $lastMonthTotalEarnings = $lastMonthMessageEarnings + $lastMonthModeratorShare;

                // Vérifier si le paiement du mois précédent a été effectué
                $paymentStatus = $this->checkPaymentStatus($moderator->id, $lastMonthStart->month, $lastMonthStart->year);

                // Calculer le temps de réponse moyen
                $avgResponseTimeQuery = DB::table('messages as client_messages')
                    ->join('messages as mod_messages', function ($join) use ($moderator) {
                        $join->on('client_messages.client_id', '=', 'mod_messages.client_id')
                            ->where('mod_messages.moderator_id', '=', $moderator->id)
                            ->whereRaw('mod_messages.created_at > client_messages.created_at');
                    })
                    ->where('client_messages.is_from_client', true)
                    ->where('mod_messages.is_from_client', false)
                    ->whereBetween('client_messages.created_at', [$startDate, $endDate])
                    ->select(DB::raw('AVG(TIMESTAMPDIFF(SECOND, client_messages.created_at, mod_messages.created_at)) as avg_time'));

                $avgResponseTimeResult = $avgResponseTimeQuery->first();
                $avgResponseTime = $avgResponseTimeResult ? $avgResponseTimeResult->avg_time : 0;

                if ($avgResponseTime > 0) {
                    $globalAvgResponseTime += $avgResponseTime;
                    $responseTimeCount++;
                }

                // Récupérer les profils assignés au modérateur
                $assignedProfiles = $moderator->moderatorProfileAssignments->map(function ($assignment) {
                    return [
                        'id' => $assignment->profile->id,
                        'name' => $assignment->profile->name,
                        'photo' => $assignment->profile->main_photo_path ?? null,
                        'is_primary' => $assignment->is_primary
                    ];
                });

                // Calculer le total des gains selon le type de revenus
                if ($revenueType === 'messages') {
                    $totalModeratorEarnings = $messageEarnings;
                } elseif ($revenueType === 'points') {
                    $totalModeratorEarnings = $moderatorShare;
                } else {
                    $totalModeratorEarnings = $messageEarnings + $moderatorShare;
                }

                // Mettre à jour les totaux globaux
                $totalMessages += $sentMessages;
                $totalReceivedMessages += $receivedMessages;
                $totalPoints += $totalPointsReceived;
                $totalProfilePoints += $profilePoints;
                $totalEarnings += $totalModeratorEarnings;

                // Statistiques du modérateur
                $moderatorStats = [
                    'messages_sent' => (int)$sentMessages,
                    'messages_received' => (int)$receivedMessages,
                    'total_messages' => (int)($sentMessages + $receivedMessages),
                    'avgResponseTime' => (int)$avgResponseTime,
                    'points_received' => (int)$totalPointsReceived,
                    'profile_points' => (int)$profilePoints,
                    'moderator_share' => (int)$moderatorShare,
                    'message_earnings' => (float)$messageEarnings,
                    'earnings' => (float)$totalModeratorEarnings,
                    'last_month_earnings' => (float)$lastMonthTotalEarnings,
                    'payment_status' => $paymentStatus
                ];

                $moderatorStats['performance'] = $this->calculatePerformanceLevel($moderatorStats);

                $formattedModerators[] = [
                    'id' => $moderator->id,
                    'name' => $moderator->name,
                    'email' => $moderator->email,
                    'avatar' => null,
                    'stats' => $moderatorStats,
                    'profiles' => $assignedProfiles
                ];
            }

            // Calculer la moyenne globale du temps de réponse
            $finalGlobalAvgResponseTime = $responseTimeCount > 0 ? $globalAvgResponseTime / $responseTimeCount : 0;

            // Filtrer par niveau de performance si demandé
            if ($request->performance_level && $request->performance_level !== 'all') {
                $formattedModerators = array_filter($formattedModerators, function ($moderator) use ($request) {
                    return $this->matchesPerformanceLevel($moderator['stats']['performance'], $request->performance_level);
                });
            }

            // Calculer les totaux en fonction du type de revenus
            $totalDisplayedEarnings = $totalEarnings;
            $totalDisplayedModeratorShare = $totalProfilePoints / 2;

            if ($revenueType === 'messages') {
                $totalDisplayedEarnings = $totalReceivedMessages * 50;
                $totalDisplayedModeratorShare = 0;
            } elseif ($revenueType === 'points') {
                $totalDisplayedEarnings = $totalDisplayedModeratorShare;
                $totalReceivedMessages = 0; // Ne pas afficher les messages reçus dans ce mode
            }

            // Générer les données pour les graphiques
            $chartData = $this->generateChartData($formattedModerators, $revenueType);

            return response()->json([
                'stats' => [
                    'totalMessages' => (int)($totalMessages + $totalReceivedMessages),
                    'sentMessages' => (int)$totalMessages,
                    'receivedMessages' => (int)$totalReceivedMessages,
                    'avgResponseTime' => (int)$finalGlobalAvgResponseTime,
                    'totalPoints' => (int)$totalPoints,
                    'totalProfilePoints' => (int)$totalProfilePoints,
                    'totalModeratorShare' => (int)$totalDisplayedModeratorShare,
                    'totalEarnings' => (float)$totalDisplayedEarnings
                ],
                'trends' => [
                    'messages' => $this->calculateTrend($totalMessages + $totalReceivedMessages),
                    'responseTime' => $this->calculateTrend($finalGlobalAvgResponseTime),
                    'points' => $this->calculateTrend($totalPoints + ($totalProfilePoints / 2)),
                    'earnings' => $this->calculateTrend($totalEarnings)
                ],
                'chartData' => $chartData,
                'moderators' => array_values($formattedModerators),
                'pagination' => [
                    'currentPage' => (int)$page,
                    'lastPage' => ceil($totalModerators / $perPage),
                    'perPage' => $perPage,
                    'total' => $totalModerators
                ],
                'period' => [
                    'current_month' => now()->format('F Y'),
                    'last_month' => now()->subMonth()->format('F Y'),
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d')
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans getData: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['error' => 'Une erreur est survenue lors du chargement des données: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Vérifie si le paiement a été effectué pour un modérateur pour un mois spécifique
     * 
     * @param int $moderatorId
     * @param int $month
     * @param int $year
     * @return string
     */
    private function checkPaymentStatus($moderatorId, $month, $year)
    {
        // Simulation: les paiements sont effectués pour les mois passés
        // Dans une implémentation réelle, vous devriez vérifier dans votre base de données
        $currentMonth = now()->month;
        $currentYear = now()->year;

        if ($year < $currentYear || ($year == $currentYear && $month < $currentMonth)) {
            return 'Payé';
        }

        return 'En attente';
    }

    /**
     * Récupère les détails d'un modérateur spécifique
     *
     * @param Request $request
     * @param int $moderatorId
     * @return JsonResponse
     */
    public function getModeratorDetails(Request $request, $moderatorId)
    {
        try {
            $moderator = User::where('id', $moderatorId)
                ->where('type', 'moderateur')
                ->with(['moderatorProfileAssignments.profile'])
                ->firstOrFail();

            // Définir la période en fonction du filtre
            $startDate = now()->startOfMonth();
            $endDate = now()->endOfMonth();

            if ($request->period === 'year') {
                $startDate = now()->startOfYear();
                $endDate = now()->endOfYear();
            } elseif ($request->period === 'custom' && $request->start_date && $request->end_date) {
                $startDate = Carbon::parse($request->start_date)->startOfDay();
                $endDate = Carbon::parse($request->end_date)->endOfDay();
            }

            // Récupérer les statistiques mensuelles
            $monthlyStats = [];
            $currentDate = Carbon::parse($startDate);

            while ($currentDate->lessThanOrEqualTo($endDate)) {
                $monthStart = Carbon::parse($currentDate)->startOfMonth();
                $monthEnd = Carbon::parse($currentDate)->endOfMonth();

                // Messages envoyés
                $sentMessages = ModeratorStatistic::where('user_id', $moderatorId)
                    ->whereBetween('stats_date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
                    ->when($request->profile_id, function ($query) use ($request) {
                        return $query->where('profile_id', $request->profile_id);
                    })
                    ->selectRaw('
                        COALESCE(SUM(short_messages_count), 0) as total_short_messages,
                        COALESCE(SUM(long_messages_count), 0) as total_long_messages
                    ')
                    ->first();

                // Messages reçus
                $receivedMessages = DB::table('messages')
                    ->join('moderator_profile_assignments', function ($join) use ($moderatorId) {
                        $join->on('messages.profile_id', '=', 'moderator_profile_assignments.profile_id')
                            ->where('moderator_profile_assignments.user_id', '=', $moderatorId);
                    })
                    ->where('messages.is_from_client', true)
                    ->whereBetween('messages.created_at', [$monthStart, $monthEnd])
                    ->when($request->profile_id, function ($query) use ($request) {
                        return $query->where('messages.profile_id', $request->profile_id);
                    })
                    ->count();

                // Points reçus
                $profilePoints = DB::table('profile_point_transactions')
                    ->where('moderator_id', $moderatorId)
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->when($request->profile_id, function ($query) use ($request) {
                        return $query->where('profile_id', $request->profile_id);
                    })
                    ->sum('points_amount');

                $moderatorShare = $profilePoints / 2;
                $messageEarnings = $receivedMessages * 50;
                $totalEarnings = $messageEarnings + $moderatorShare;

                // Statut de paiement
                $paymentStatus = $this->checkPaymentStatus($moderatorId, $monthStart->month, $monthStart->year);

                $monthlyStats[] = [
                    'month' => $monthStart->format('F Y'),
                    'messages_sent' => ($sentMessages->total_short_messages ?? 0) + ($sentMessages->total_long_messages ?? 0),
                    'messages_received' => $receivedMessages,
                    'profile_points' => $profilePoints,
                    'moderator_share' => $moderatorShare,
                    'message_earnings' => $messageEarnings,
                    'total_earnings' => $totalEarnings,
                    'payment_status' => $paymentStatus
                ];

                $currentDate->addMonth();
            }

            // Récupérer les profils assignés au modérateur
            $assignedProfiles = $moderator->moderatorProfileAssignments()
                ->with('profile')
                ->get()
                ->map(function ($assignment) {
                    return [
                        'id' => $assignment->profile->id,
                        'name' => $assignment->profile->name,
                        'photo' => $assignment->profile->main_photo_path,
                        'is_primary' => $assignment->is_primary,
                        'is_active' => $assignment->is_active
                    ];
                });

            // Récupérer les statistiques globales
            $totalStats = [
                'messages_sent' => collect($monthlyStats)->sum('messages_sent'),
                'messages_received' => collect($monthlyStats)->sum('messages_received'),
                'profile_points' => collect($monthlyStats)->sum('profile_points'),
                'moderator_share' => collect($monthlyStats)->sum('moderator_share'),
                'message_earnings' => collect($monthlyStats)->sum('message_earnings'),
                'total_earnings' => collect($monthlyStats)->sum('total_earnings')
            ];

            return response()->json([
                'moderator' => [
                    'id' => $moderator->id,
                    'name' => $moderator->name,
                    'email' => $moderator->email,
                    'created_at' => $moderator->created_at->format('Y-m-d'),
                    'profiles' => $assignedProfiles
                ],
                'monthly_stats' => $monthlyStats,
                'total_stats' => $totalStats,
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d')
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans getModeratorDetails: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['error' => 'Une erreur est survenue lors du chargement des détails du modérateur'], 500);
        }
    }

    /**
     * Calcule le niveau de performance d'un modérateur en fonction de ses statistiques
     *
     * @param array $stats
     * @return string
     */
    private function calculatePerformanceLevel($stats)
    {
        // Critères pour déterminer le niveau de performance
        // Ces critères sont arbitraires et peuvent être ajustés selon vos besoins
        $messagesPerDay = ($stats['messages_sent'] + $stats['messages_received']) / 30; // Approximation pour un mois
        $earningsPerDay = $stats['earnings'] / 30;

        if ($messagesPerDay >= 20 && $earningsPerDay >= 500) {
            return 'top';
        } elseif ($messagesPerDay >= 10 && $earningsPerDay >= 250) {
            return 'average';
        } else {
            return 'low';
        }
    }

    /**
     * Vérifie si le niveau de performance correspond au filtre demandé
     *
     * @param string $performance
     * @param string $filter
     * @return bool
     */
    private function matchesPerformanceLevel($performance, $filter)
    {
        if ($filter === 'all') {
            return true;
        }

        return $performance === $filter;
    }

    /**
     * Calcule la tendance pour une métrique
     *
     * @param float $value
     * @return string
     */
    private function calculateTrend($value)
    {
        // Pour l'instant, on retourne une tendance aléatoire
        // À remplacer par un vrai calcul basé sur les données historiques
        $trend = rand(-10, 10);
        return ($trend >= 0 ? '+' : '') . $trend . '%';
    }

    /**
     * Génère les données pour les graphiques
     *
     * @param array $moderators
     * @param string $revenueType
     * @return array
     */
    private function generateChartData($moderators, $revenueType = 'all')
    {
        $labels = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
        $shortMessages = array_fill(0, 7, 0);
        $longMessages = array_fill(0, 7, 0);
        $receivedMessages = array_fill(0, 7, 0);
        $earnings = array_fill(0, 7, 0);
        $responseTimes = array_fill(0, 7, 0);

        // Dans une vraie implémentation, vous devriez agréger les données par jour
        // Pour l'instant, on utilise des données aléatoires
        for ($i = 0; $i < 7; $i++) {
            $shortMessages[$i] = rand(20, 50);
            $longMessages[$i] = rand(15, 35);
            $receivedMessages[$i] = rand(25, 60); // Messages reçus (généralement plus nombreux)
            $earnings[$i] = rand(500, 1500); // Revenus journaliers
            $responseTimes[$i] = rand(20, 40) / 10;
        }

        // Adapter les données en fonction du type de revenus
        if ($revenueType === 'messages') {
            // Ne montrer que les messages reçus
            $shortMessages = array_fill(0, 7, 0);
            $longMessages = array_fill(0, 7, 0);
        } elseif ($revenueType === 'points') {
            // Ne montrer que les points des profils (pas de messages)
            $receivedMessages = array_fill(0, 7, 0);
            $shortMessages = array_fill(0, 7, 0);
            $longMessages = array_fill(0, 7, 0);
        }

        return [
            'labels' => $labels,
            'shortMessages' => $shortMessages,
            'longMessages' => $longMessages,
            'receivedMessages' => $receivedMessages,
            'earnings' => $earnings,
            'responseTimes' => $responseTimes
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
        try {
            // TODO: Implémenter l'exportation réelle des données
            // Par exemple, utiliser une bibliothèque comme Laravel Excel pour générer un fichier Excel

            // Pour l'instant, nous retournons un message d'erreur
            return response()->json([
                'message' => 'Export not implemented yet',
                'filters' => [
                    'period' => $request->period,
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'moderator_id' => $request->moderator_id,
                    'profile_id' => $request->profile_id,
                    'performance_level' => $request->performance_level,
                    'revenue_type' => $request->revenue_type
                ]
            ], 501);
        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'export: ' . $e->getMessage());
            return response()->json(['error' => 'Une erreur est survenue lors de l\'export des données'], 500);
        }
    }
}
