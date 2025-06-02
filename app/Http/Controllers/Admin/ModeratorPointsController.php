<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PointConsumption;
use App\Models\Message;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ModeratorPointsController extends Controller
{
    public function index(Request $request)
    {
        $query = User::where('type', 'moderateur')
            ->with(['pointConsumptions', 'moderatorProfileAssignments.profile']);

        // Filtrage par modérateur
        if ($request->has('moderator_id')) {
            $query->where('id', $request->moderator_id);
        }

        // Récupérer les modérateurs avec leurs statistiques
        $moderators = $query->get()->map(function ($moderator) use ($request) {
            $pointsQuery = $moderator->pointConsumptions();

            // Filtrage par période
            if ($request->has('start_date') && $request->has('end_date')) {
                $pointsQuery->whereBetween('created_at', [
                    Carbon::parse($request->start_date)->startOfDay(),
                    Carbon::parse($request->end_date)->endOfDay()
                ]);
            }

            // Filtrage par type de source
            if ($request->has('source_type')) {
                $pointsQuery->where('type', $request->source_type);
            }

            $points = $pointsQuery->get();
            $totalPoints = $points->sum('points_spent');

            // Calculer l'équivalent monétaire (exemple: 1 point = 0.01€)
            $monetaryEquivalent = $totalPoints * 0.01;

            return [
                'id' => $moderator->id,
                'name' => $moderator->name,
                'total_points' => $totalPoints,
                'monetary_equivalent' => $monetaryEquivalent,
                'points_details' => $points->groupBy('type')->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'total_points' => $group->sum('points_spent')
                    ];
                }),
                'profiles' => $moderator->moderatorProfileAssignments->map(function ($assignment) {
                    return [
                        'id' => $assignment->profile->id,
                        'name' => $assignment->profile->name
                    ];
                })
            ];
        });

        // Statistiques globales
        $globalStats = [
            'total_points_attributed' => $moderators->sum('total_points'),
            'total_monetary_equivalent' => $moderators->sum('monetary_equivalent'),
            'average_points_per_moderator' => $moderators->avg('total_points'),
            'moderators_count' => $moderators->count()
        ];

        return Inertia::render('ModeratorPointsAttribution', [
            'moderators' => $moderators,
            'stats' => $globalStats,
            'filters' => $request->all()
        ]);
    }

    public function getModeratorStats(Request $request, $moderatorId)
    {
        $moderator = User::findOrFail($moderatorId);
        $period = $request->get('period', '30days');

        $startDate = match ($period) {
            '7days' => now()->subDays(7),
            '30days' => now()->subDays(30),
            '90days' => now()->subDays(90),
            'year' => now()->subYear(),
            default => now()->subDays(30)
        };

        $pointsByDay = $moderator->pointConsumptions()
            ->where('created_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(points_spent) as total_points'),
                'type'
            )
            ->groupBy('date', 'type')
            ->orderBy('date')
            ->get()
            ->groupBy('date');

        $messageStats = Message::where('moderator_id', $moderatorId)
            ->where('created_at', '>=', $startDate)
            ->select(
                DB::raw('COUNT(*) as total_messages'),
                DB::raw('AVG(LENGTH(content)) as avg_message_length')
            )
            ->first();

        return response()->json([
            'pointsByDay' => $pointsByDay,
            'messageStats' => $messageStats
        ]);
    }

    public function addBonus(Request $request)
    {
        $validated = $request->validate([
            'moderator_id' => 'required|exists:users,id',
            'points' => 'required|integer|min:1',
            'description' => 'required|string'
        ]);

        $moderator = User::findOrFail($validated['moderator_id']);

        try {
            DB::transaction(function () use ($moderator, $validated) {
                // Incrémenter les points du modérateur
                $moderator->increment('points', $validated['points']);

                // TODO: À modifier après mise à jour de la base de données
                // - Ajouter 'bonus_admin' dans l'enum 'type' de la table point_consumptions
                // - Remplacer 'message_sent' par 'bonus_admin' ci-dessous
                $pointConsumption = $moderator->pointConsumptions()->create([
                    'type' => 'message_sent', // Temporaire: utilisation du type existant
                    'points_spent' => $validated['points'],
                    'description' => '[BONUS] ' . $validated['description'], // Préfixe pour identifier les bonus
                    'consumable_type' => 'App\\Models\\User',
                    'consumable_id' => $moderator->id
                ]);
            });

            return response()->json([
                'message' => 'Bonus ajouté avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'ajout du bonus: ' . $e->getMessage()
            ], 500);
        }
    }

    public function export(Request $request)
    {
        $query = PointConsumption::with(['user'])
            ->whereHas('user', function ($q) {
                $q->where('type', 'moderateur');
            });

        // Appliquer les filtres
        if ($request->has('moderator_id')) {
            $query->where('user_id', $request->moderator_id);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay()
            ]);
        }

        $pointConsumptions = $query->get();

        // Générer le CSV
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=moderator-points.csv',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function () use ($pointConsumptions) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'ID',
                'Date',
                'Modérateur',
                'Type',
                'Points',
                'Description'
            ]);

            foreach ($pointConsumptions as $consumption) {
                fputcsv($file, [
                    $consumption->id,
                    $consumption->created_at->format('Y-m-d H:i:s'),
                    $consumption->user->name,
                    $consumption->type,
                    $consumption->points_spent,
                    $consumption->description
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
