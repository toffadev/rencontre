<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PointTransaction;
use App\Models\User;
use App\Models\Profile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\ProfilePointTransaction;

class FinancialTransactionController extends Controller
{
    public function index(Request $request)
    {
        // Requête pour les transactions de points clients
        $query = PointTransaction::with(['user'])
            ->select('point_transactions.*')
            ->leftJoin('users', 'point_transactions.user_id', '=', 'users.id');

        // Filtrage par client
        if ($request->has('client_id')) {
            $query->where('point_transactions.user_id', $request->client_id);
        }

        // Filtrage par période
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('point_transactions.created_at', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay()
            ]);
        }

        // Filtrage par statut
        if ($request->has('status')) {
            $query->where('point_transactions.status', $request->status);
        }

        // Filtrage par montant
        if ($request->has('min_amount')) {
            $query->where('point_transactions.money_amount', '>=', $request->min_amount);
        }
        if ($request->has('max_amount')) {
            $query->where('point_transactions.money_amount', '<=', $request->max_amount);
        }

        // Statistiques globales combinées (points clients + points profils)
        $clientStatsQuery = clone $query;
        $clientStats = [
            'total_revenue' => $clientStatsQuery->sum('point_transactions.money_amount') ?? 0,
            'total_points_sold' => $clientStatsQuery->sum('point_transactions.points_amount') ?? 0,
            'transactions_count' => $clientStatsQuery->count() ?? 0
        ];

        // Requête pour les statistiques des profils
        $profileStatsQuery = ProfilePointTransaction::query();

        if ($request->has('start_date') && $request->has('end_date')) {
            $profileStatsQuery->whereBetween('created_at', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay()
            ]);
        }
        if ($request->has('status')) {
            $profileStatsQuery->where('status', $request->status);
        }

        $profileStats = [
            'total_revenue' => $profileStatsQuery->sum('money_amount') ?? 0,
            'total_points_sold' => $profileStatsQuery->sum('points_amount') ?? 0,
            'transactions_count' => $profileStatsQuery->count() ?? 0
        ];

        $totalTransactions = $clientStats['transactions_count'] + $profileStats['transactions_count'];

        $stats = [
            'total_revenue' => $clientStats['total_revenue'] + $profileStats['total_revenue'],
            'total_points_sold' => $clientStats['total_points_sold'] + $profileStats['total_points_sold'],
            'transactions_count' => $totalTransactions,
            'average_transaction' => $totalTransactions > 0 ?
                ($clientStats['total_revenue'] + $profileStats['total_revenue']) / $totalTransactions : 0
        ];

        // Revenus par jour (derniers 30 jours)
        $revenueByDay = DB::table(function ($query) {
            $query->select(
                DB::raw('DATE(point_transactions.created_at) as date'),
                DB::raw('SUM(point_transactions.money_amount) as total_revenue'),
                DB::raw('COUNT(*) as transactions_count'),
                DB::raw("'client' as type")
            )
                ->from('point_transactions')
                ->groupBy('date')
                ->union(
                    DB::table('profile_point_transactions')
                        ->select(
                            DB::raw('DATE(created_at) as date'),
                            DB::raw('SUM(money_amount) as total_revenue'),
                            DB::raw('COUNT(*) as transactions_count'),
                            DB::raw("'profile' as type")
                        )
                        ->groupBy('date')
                );
        }, 'combined_transactions')
            ->select(
                'date',
                DB::raw('SUM(total_revenue) as total_revenue'),
                DB::raw('SUM(transactions_count) as transactions_count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Pagination des transactions combinées
        $transactions = $query->orderBy('point_transactions.created_at', 'desc')
            ->paginate(15)
            ->through(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'date' => $transaction->created_at->format('Y-m-d H:i:s'),
                    'client' => [
                        'id' => $transaction->user->id,
                        'name' => $transaction->user->name
                    ],
                    'points_amount' => $transaction->points_amount,
                    'money_amount' => $transaction->money_amount,
                    'status' => $transaction->status,
                    'stripe_session_id' => $transaction->stripe_session_id,
                    'description' => $transaction->description,
                    'type' => 'client'
                ];
            });

        return Inertia::render('FinancialTransactions', [
            'transactions' => $transactions,
            'stats' => $stats,
            'revenueByDay' => $revenueByDay,
            'filters' => $request->all()
        ]);
    }

    public function getStats(Request $request)
    {
        $period = $request->get('period', '30days');
        $startDate = match ($period) {
            '7days' => now()->subDays(7),
            '30days' => now()->subDays(30),
            '90days' => now()->subDays(90),
            'year' => now()->subYear(),
            default => now()->subDays(30)
        };

        $stats = PointTransaction::where('created_at', '>=', $startDate)
            ->where('status', 'succeeded')
            ->select(
                DB::raw('SUM(money_amount) as total_revenue'),
                DB::raw('SUM(points_amount) as total_points'),
                DB::raw('COUNT(*) as total_transactions'),
                DB::raw('AVG(money_amount) as avg_transaction_value')
            )
            ->first();

        $revenueByDay = PointTransaction::where('created_at', '>=', $startDate)
            ->where('status', 'succeeded')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(money_amount) as revenue'),
                DB::raw('COUNT(*) as transactions')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'stats' => $stats,
            'revenueByDay' => $revenueByDay
        ]);
    }

    public function export(Request $request)
    {
        $query = PointTransaction::with(['user'])
            ->select('point_transactions.*')
            ->leftJoin('users', 'point_transactions.user_id', '=', 'users.id');

        // Appliquer les mêmes filtres que pour l'index
        if ($request->has('client_id')) {
            $query->where('user_id', $request->client_id);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('point_transactions.created_at', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay()
            ]);
        }

        $transactions = $query->get();

        // Générer le CSV
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=transactions.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function () use ($transactions) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'ID',
                'Date',
                'Client',
                'Points',
                'Montant (€)',
                'Statut',
                'ID Session Stripe',
                'Description'
            ]);

            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    $transaction->id,
                    $transaction->created_at->format('Y-m-d H:i:s'),
                    $transaction->user->name,
                    $transaction->points_amount,
                    $transaction->money_amount,
                    $transaction->status,
                    $transaction->stripe_session_id,
                    $transaction->description
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
