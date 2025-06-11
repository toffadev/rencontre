<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ModeratorStatistic;
use App\Models\Message;
use App\Models\ModeratorProfileAssignment;
use Carbon\Carbon;
use App\Models\ProfilePointTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Http\JsonResponse;

class ModeratorDetailsController extends Controller
{
    /**
     * Affiche la page de détails d'un modérateur
     *
     * @param int $id
     * @return \Inertia\Response
     */
    // Dans ModeratorDetailsController.php
    public function show($id)
    {
        $moderator = User::where('id', $id)
            ->where('type', 'moderateur')
            ->firstOrFail();

        return Inertia::render('ModeratorDetails', [
            'moderatorId' => $id,  // Assurez-vous que cette propriété est bien passée
            'initialModerator' => [
                'id' => $moderator->id,
                'name' => $moderator->name,
                'email' => $moderator->email
            ]
        ]);
    }

    /**
     * Récupère les détails d'un modérateur spécifique
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function getDetails(Request $request, $id)
    {
        try {
            $moderator = User::where('id', $id)
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
                $sentMessages = ModeratorStatistic::where('user_id', $id)
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
                    ->join('moderator_profile_assignments', function ($join) use ($id) {
                        $join->on('messages.profile_id', '=', 'moderator_profile_assignments.profile_id')
                            ->where('moderator_profile_assignments.user_id', '=', $id);
                    })
                    ->where('messages.is_from_client', true)
                    ->whereBetween('messages.created_at', [$monthStart, $monthEnd])
                    ->when($request->profile_id, function ($query) use ($request) {
                        return $query->where('messages.profile_id', $request->profile_id);
                    })
                    ->count();

                // Points reçus
                $profilePoints = DB::table('profile_point_transactions')
                    ->where('moderator_id', $id)
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->when($request->profile_id, function ($query) use ($request) {
                        return $query->where('profile_id', $request->profile_id);
                    })
                    ->sum('points_amount');

                $moderatorShare = $profilePoints / 2;
                $messageEarnings = $receivedMessages * 50;
                $totalEarnings = $messageEarnings + $moderatorShare;

                // Statut de paiement
                $paymentStatus = $this->checkPaymentStatus($id, $monthStart->month, $monthStart->year);

                $monthlyStats[] = [
                    'month' => $monthStart->format('F Y'),
                    'month_value' => $monthStart->format('Y-m'),
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
            Log::error('Erreur dans getDetails: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['error' => 'Une erreur est survenue lors du chargement des détails du modérateur'], 500);
        }
    }

    /**
     * Récupère l'historique des messages d'un modérateur
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function getMessages(Request $request, $id)
    {
        try {
            $limit = $request->input('limit', 50);
            $profileId = $request->input('profile_id');
            $startDate = null;
            $endDate = null;

            // Définir la période
            if ($request->period === 'month') {
                $startDate = now()->startOfMonth();
                $endDate = now()->endOfMonth();
            } elseif ($request->period === 'year') {
                $startDate = now()->startOfYear();
                $endDate = now()->endOfYear();
            } elseif ($request->period === 'custom' && $request->start_date && $request->end_date) {
                $startDate = Carbon::parse($request->start_date)->startOfDay();
                $endDate = Carbon::parse($request->end_date)->endOfDay();
            } else {
                // Par défaut, on prend le mois en cours
                $startDate = now()->startOfMonth();
                $endDate = now()->endOfMonth();
            }

            // Récupérer les profils gérés par ce modérateur
            $moderatorProfileIds = DB::table('moderator_profile_assignments')
                ->where('user_id', $id)
                ->pluck('profile_id')
                ->toArray();

            // Construire la requête avec le modèle Eloquent
            $query = Message::whereIn('profile_id', $moderatorProfileIds)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->with(['profile:id,name,main_photo_path', 'client:id,name']);

            // Filtrer par type de message si spécifié
            if ($request->message_type === 'received') {
                $query->where('is_from_client', true);
            } elseif ($request->message_type === 'sent') {
                $query->where('is_from_client', false)
                    ->where('moderator_id', $id);
            }

            // Filtrer par profil si spécifié
            if ($profileId) {
                $query->where('profile_id', $profileId);
            }

            $query->orderBy('created_at', 'desc');

            // Paginer les résultats
            $messages = $query->paginate($limit);

            // Calculer les statistiques
            $totalMessages = $messages->total();
            $receivedMessages = $query->clone()->where('is_from_client', true)->count();
            $sentMessages = $query->clone()->where('is_from_client', false)->where('moderator_id', $id)->count();

            // Formater les messages
            $formattedMessages = $messages->map(function ($message) {
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
                    'moderator' => $message->moderator_id ? [
                        'id' => $message->moderator_id,
                        'name' => $message->moderator ? $message->moderator->name : 'Inconnu'
                    ] : null,
                    'created_at' => $message->created_at->format('Y-m-d H:i:s')
                ];
            });

            return response()->json([
                'messages' => $formattedMessages,
                'statistics' => [
                    'total_messages' => $totalMessages,
                    'received_messages' => $receivedMessages,
                    'sent_messages' => $sentMessages
                ],
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans getMessages: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['error' => 'Une erreur est survenue lors du chargement des messages'], 500);
        }
    }

    /**
     * Récupère l'historique des paiements d'un modérateur
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function getPayments(Request $request, $id)
    {
        try {
            $year = $request->input('year', now()->year);

            // Récupérer les statistiques mensuelles des messages reçus
            $monthlyStats = [];

            for ($month = 1; $month <= 12; $month++) {
                $monthStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
                $monthEnd = Carbon::createFromDate($year, $month, 1)->endOfMonth();

                // Ne pas inclure les mois futurs
                if ($monthStart->isFuture()) {
                    continue;
                }

                // Messages reçus
                $receivedMessages = DB::table('messages')
                    ->join('moderator_profile_assignments', function ($join) use ($id) {
                        $join->on('messages.profile_id', '=', 'moderator_profile_assignments.profile_id')
                            ->where('moderator_profile_assignments.user_id', '=', $id);
                    })
                    ->where('messages.is_from_client', true)
                    ->whereBetween('messages.created_at', [$monthStart, $monthEnd])
                    ->count();

                // Points reçus
                $profilePoints = DB::table('profile_point_transactions')
                    ->where('moderator_id', $id)
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->sum('points_amount');

                $moderatorShare = $profilePoints / 2;
                $messageEarnings = $receivedMessages * 50;
                $totalEarnings = $messageEarnings + $moderatorShare;

                // Statut de paiement
                $paymentStatus = $this->checkPaymentStatus($id, $month, $year);

                // Noms des mois en français
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

                $monthlyStats[] = [
                    'month' => $frenchMonths[$month] . ' ' . $year,
                    'month_value' => $monthStart->format('Y-m'),
                    'messages' => $receivedMessages,
                    'message_earnings' => $messageEarnings,
                    'points' => $profilePoints,
                    'moderator_share' => $moderatorShare,
                    'earnings' => $totalEarnings,
                    'status' => $paymentStatus
                ];
            }

            // Calculer les totaux
            $totalMessageEarnings = collect($monthlyStats)->sum('message_earnings');
            $totalPointsAmount = collect($monthlyStats)->sum('points');
            $totalModeratorShare = collect($monthlyStats)->sum('moderator_share');
            $totalEarnings = collect($monthlyStats)->sum('earnings');

            return response()->json([
                'months' => $monthlyStats,
                'year' => $year,
                'total_message_earnings' => $totalMessageEarnings,
                'total_points' => $totalPointsAmount,
                'total_moderator_share' => $totalModeratorShare,
                'total_earnings' => $totalEarnings,
                'available_years' => $this->getAvailableYears($id)
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans getPayments: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['error' => 'Une erreur est survenue lors du chargement des paiements'], 500);
        }
    }

    /**
     * Met à jour le statut de paiement d'un modérateur pour un mois spécifique
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updatePaymentStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'month' => 'required|string|date_format:Y-m',
                'status' => 'required|in:Payé,En attente'
            ]);

            $month = Carbon::createFromFormat('Y-m', $request->month);

            // Ici, vous devriez mettre à jour le statut de paiement dans votre base de données
            // Pour l'exemple, nous allons simuler une mise à jour réussie

            // Dans une implémentation réelle, vous pourriez avoir une table payments avec des colonnes
            // comme moderator_id, month, year, amount, status, etc.

            return response()->json([
                'success' => true,
                'message' => 'Statut de paiement mis à jour avec succès',
                'data' => [
                    'moderator_id' => $id,
                    'month' => $request->month,
                    'status' => $request->status
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur dans updatePaymentStatus: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['error' => 'Une erreur est survenue lors de la mise à jour du statut de paiement'], 500);
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
     * Récupère les années disponibles pour les paiements d'un modérateur
     * 
     * @param int $moderatorId
     * @return array
     */
    private function getAvailableYears($moderatorId)
    {
        // Récupérer la date d'inscription du modérateur
        $user = User::find($moderatorId);
        $startYear = $user ? $user->created_at->year : now()->year;

        // Créer un tableau des années depuis l'inscription jusqu'à maintenant
        $years = [];
        for ($year = $startYear; $year <= now()->year; $year++) {
            $years[] = $year;
        }

        return $years;
    }
}
