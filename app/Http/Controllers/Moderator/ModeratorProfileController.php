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

        // Construire la requête de base pour les statistiques du modérateur
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

        // Récupérer les messages reçus (is_from_client = true)
        // Récupérer d'abord les profils gérés par ce modérateur
        $moderatorProfileIds = DB::table('moderator_profile_assignments')
            ->where('user_id', $userId)
            ->pluck('profile_id')
            ->toArray();

        Log::info('Profils gérés par le modérateur', [
            'user_id' => $userId,
            'profile_count' => count($moderatorProfileIds),
            'profile_ids' => $moderatorProfileIds
        ]);

        // Récupérer les messages reçus pour ces profils
        $receivedMessagesQuery = Message::whereIn('profile_id', $moderatorProfileIds)
            ->where('is_from_client', true)
            ->where('created_at', '>=', $startDate);

        if ($profileId) {
            $receivedMessagesQuery->where('profile_id', $profileId);
        }

        // Déboguer la requête SQL
        Log::info('Requête SQL pour les messages reçus', [
            'sql' => $receivedMessagesQuery->toSql(),
            'bindings' => $receivedMessagesQuery->getBindings()
        ]);

        // Compter les messages reçus par jour
        $receivedMessagesStats = $receivedMessagesQuery->select([
            DB::raw('COUNT(*) as received_count'),
            DB::raw('DATE(created_at) as date')
        ])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // Déboguer les résultats
        Log::info('Messages reçus trouvés', [
            'count' => $receivedMessagesStats->count(),
            'stats' => $receivedMessagesStats->toArray()
        ]);

        // Calculer le total des messages reçus
        $totalReceivedMessages = $receivedMessagesStats->sum('received_count');

        Log::info('Total des messages reçus', [
            'total' => $totalReceivedMessages
        ]);

        // Calculer les gains basés sur les messages reçus (50 points par message)
        $receivedEarnings = $totalReceivedMessages * 50;

        // Ajouter les totaux des messages reçus
        $totals['received_messages'] = $totalReceivedMessages;
        $totals['received_earnings'] = $receivedEarnings;

        // Calculer les points reçus par les profils (divisés par 2 car le modérateur ne reçoit que la moitié)
        $profilePointsQuery = DB::table('profile_point_transactions')
            ->where('moderator_id', $userId)
            ->where('created_at', '>=', $startDate);

        if ($profileId) {
            $profilePointsQuery->where('profile_id', $profileId);
        }

        $profilePoints = $profilePointsQuery->sum('points_amount');
        $moderatorShare = $profilePoints / 2; // Le modérateur ne reçoit que la moitié des points

        Log::info('Points reçus par les profils', [
            'total_points' => $profilePoints,
            'moderator_share' => $moderatorShare
        ]);

        // Ajouter les points reçus aux totaux
        $totals['profile_points'] = $profilePoints;
        $totals['moderator_share'] = $moderatorShare;

        // Calculer le total des gains (messages reçus + points reçus)
        $totals['total_earnings'] = $totals['received_earnings'] + $moderatorShare;

        Log::info('Totaux calculés', $totals);

        // Ajouter les messages reçus aux statistiques quotidiennes
        $dailyStats = [];
        $dateMap = [];

        // Créer un tableau associatif des dates pour faciliter la fusion
        foreach ($stats as $stat) {
            $dateMap[$stat->date] = [
                'date' => $stat->date,
                'total_short_messages' => $stat->total_short_messages,
                'total_long_messages' => $stat->total_long_messages,
                'total_points' => $stat->total_points,
                'total_earnings' => $stat->total_earnings,
                'received_messages' => 0,
                'received_earnings' => 0
            ];
        }

        // Ajouter les messages reçus
        foreach ($receivedMessagesStats as $receivedStat) {
            if (isset($dateMap[$receivedStat->date])) {
                $dateMap[$receivedStat->date]['received_messages'] = $receivedStat->received_count;
                $dateMap[$receivedStat->date]['received_earnings'] = $receivedStat->received_count * 50;
            } else {
                $dateMap[$receivedStat->date] = [
                    'date' => $receivedStat->date,
                    'total_short_messages' => 0,
                    'total_long_messages' => 0,
                    'total_points' => 0,
                    'total_earnings' => 0,
                    'received_messages' => $receivedStat->received_count,
                    'received_earnings' => $receivedStat->received_count * 50
                ];
            }
        }

        // Convertir le tableau associatif en tableau indexé
        foreach ($dateMap as $date => $data) {
            $dailyStats[] = $data;
        }

        // Trier par date
        usort($dailyStats, function ($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        // Calculer les moyennes quotidiennes
        $dayCount = max(1, $startDate->diffInDays(now()));
        $averages = [
            'messages_per_day' => ($totals['short_messages'] + $totals['long_messages']) / $dayCount,
            'earnings_per_day' => $totals['earnings'] / $dayCount,
            'received_messages_per_day' => $totalReceivedMessages / $dayCount,
            'received_earnings_per_day' => $receivedEarnings / $dayCount
        ];

        return response()->json([
            'daily_stats' => $dailyStats,
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
        $messageType = $request->input('messageType', 'all');

        // Déterminer la période
        $startDate = match ($dateRange) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfWeek()
        };

        // Récupérer d'abord les profils gérés par ce modérateur
        $moderatorProfileIds = DB::table('moderator_profile_assignments')
            ->where('user_id', $userId)
            ->pluck('profile_id')
            ->toArray();

        Log::info('Profils gérés par le modérateur', [
            'user_id' => $userId,
            'profile_count' => count($moderatorProfileIds),
            'profile_ids' => $moderatorProfileIds
        ]);

        // Construire la requête de base
        $query = Message::whereIn('profile_id', $moderatorProfileIds)
            ->where('created_at', '>=', $startDate)
            ->with(['profile:id,name,main_photo_path', 'client:id,name']);

        // Filtrer par type de message si spécifié (reçu/envoyé)
        if ($messageType === 'received') {
            $query->where('is_from_client', true);
        } elseif ($messageType === 'sent') {
            $query->where('is_from_client', false)
                ->where('moderator_id', $userId); // Pour les messages envoyés, on filtre bien par moderator_id
        }

        $query->orderBy('created_at', 'desc');

        if ($profileId) {
            $query->where('profile_id', $profileId);
        }

        $messages = $query->paginate($limit);

        // Calculer les statistiques des messages
        $totalMessages = $messages->total();
        $receivedMessages = $messages->filter(fn($msg) => $msg->is_from_client)->count();
        $sentMessages = $messages->filter(fn($msg) => !$msg->is_from_client)->count();
        $shortMessages = $messages->filter(fn($msg) => !$msg->is_from_client && strlen($msg->content) < 10)->count();
        $longMessages = $messages->filter(fn($msg) => !$msg->is_from_client && strlen($msg->content) >= 10)->count();

        // L'ancien système de gains (messages envoyés) n'est plus utilisé
        $oldEarnings = 0; // Les messages envoyés ne rapportent plus de points

        // Calcul des gains selon le nouveau système (messages reçus)
        $newEarnings = $receivedMessages * 50;

        // Récupérer les points reçus pour la période
        $pointsQuery = DB::table('profile_point_transactions')
            ->where('moderator_id', $userId)
            ->where('created_at', '>=', $startDate);

        if ($profileId) {
            $pointsQuery->where('profile_id', $profileId);
        }

        $pointsAmount = $pointsQuery->sum('points_amount');
        $moderatorShare = $pointsAmount / 2; // Le modérateur ne reçoit que la moitié des points

        // Calculer le total des gains (messages reçus + points reçus)
        $totalEarnings = $newEarnings + $moderatorShare;

        return response()->json([
            'messages' => $messages->map(function ($message) {
                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'length' => strlen($message->content),
                    'is_long' => strlen($message->content) >= 10,
                    'earnings' => $message->is_from_client ? 50 : 0,
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
                'received_messages' => $receivedMessages,
                'sent_messages' => $sentMessages,
                'short_messages' => $shortMessages,
                'long_messages' => $longMessages,
                'old_earnings' => $oldEarnings,
                'new_earnings' => $newEarnings,
                'points_amount' => $pointsAmount,
                'moderator_share' => $moderatorShare,
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

    public function getMonthlyEarnings(Request $request)
    {
        $userId = Auth::id();
        $year = $request->input('year', now()->year);

        Log::info('Récupération des revenus mensuels', [
            'user_id' => $userId,
            'year' => $year
        ]);

        // Récupérer les statistiques mensuelles des messages reçus
        $query = Message::whereIn('profile_id', function ($query) use ($userId) {
            $query->select('profile_id')
                ->from('moderator_profile_assignments')
                ->where('user_id', $userId);
        })
            ->where('is_from_client', true)
            ->whereYear('created_at', $year);

        // Déboguer la requête SQL
        Log::info('Requête SQL pour les revenus mensuels', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]);

        // Exécuter une requête pour vérifier s'il y a des messages
        $totalMessages = $query->count();
        Log::info('Nombre total de messages reçus pour l\'année', [
            'total' => $totalMessages
        ]);

        $monthlyStats = $query->select([
            DB::raw('MONTH(created_at) as month'),
            DB::raw('COUNT(*) as received_count')
        ])
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->orderBy('month')
            ->get();

        Log::info('Statistiques mensuelles trouvées', [
            'count' => $monthlyStats->count(),
            'stats' => $monthlyStats->toArray()
        ]);

        // Récupérer les points reçus par mois
        $monthlyPoints = DB::table('profile_point_transactions')
            ->where('moderator_id', $userId)
            ->whereYear('created_at', $year)
            ->select([
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(points_amount) as points_amount')
            ])
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->orderBy('month')
            ->get();

        Log::info('Points mensuels trouvés', [
            'count' => $monthlyPoints->count(),
            'points' => $monthlyPoints->toArray()
        ]);

        // Formater les données pour l'affichage
        $months = [];
        $currentMonth = now()->month;
        $frenchMonths = [
            1 => 'Janvier',
            2 => 'Février',
            3 => 'Mars',
            4 => 'Avril',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juillet',
            8 => 'Août',
            9 => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Décembre'
        ];

        // Créer un tableau pour tous les mois de l'année
        for ($i = 1; $i <= 12; $i++) {
            $monthData = $monthlyStats->firstWhere('month', $i);
            $pointsData = $monthlyPoints->firstWhere('month', $i);

            $receivedCount = $monthData ? $monthData->received_count : 0;
            $messageEarnings = $receivedCount * 50;

            $pointsAmount = $pointsData ? $pointsData->points_amount : 0;
            $moderatorShare = $pointsAmount / 2; // Le modérateur ne reçoit que la moitié des points

            $totalEarnings = $messageEarnings + $moderatorShare;

            $months[] = [
                'name' => $frenchMonths[$i] . ' ' . $year,
                'messages' => $receivedCount,
                'message_earnings' => $messageEarnings,
                'points' => $pointsAmount,
                'moderator_share' => $moderatorShare,
                'earnings' => $totalEarnings,
                'status' => $i < $currentMonth ? 'Payé' : 'En attente'
            ];
        }

        // Calculer les totaux
        $totalMessageEarnings = $monthlyStats->sum('received_count') * 50;
        $totalPointsAmount = $monthlyPoints->sum('points_amount');
        $totalModeratorShare = $totalPointsAmount / 2;
        $totalEarnings = $totalMessageEarnings + $totalModeratorShare;

        return response()->json([
            'months' => $months,
            'year' => $year,
            'total_messages' => $monthlyStats->sum('received_count'),
            'total_message_earnings' => $totalMessageEarnings,
            'total_points' => $totalPointsAmount,
            'total_moderator_share' => $totalModeratorShare,
            'total_earnings' => $totalEarnings
        ]);
    }
}
