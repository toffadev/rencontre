<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfileReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProfileReportController extends Controller
{
    public function index()
    {
        return Inertia::render('Reports/Index');
    }

    public function getReports(Request $request)
    {
        try {
            DB::enableQueryLog();

            $query = ProfileReport::query()
                ->with(['reporter:id,name,email', 'reportedUser:id,name,email', 'reportedProfile:id,name,gender,main_photo_path'])
                ->select('profile_reports.*')
                ->latest();

            // Filtrage par statut
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Recherche
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('reporter', fn($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('reportedProfile', fn($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Log de la requête avant exécution
            Log::info('Requête SQL:', [
                'query' => $query->toSql(),
                'bindings' => $query->getBindings()
            ]);

            // Exécuter la requête avec pagination
            $reports = $query->paginate(10)->through(function ($report) {
                return [
                    'id' => $report->id,
                    'reason' => $report->reason,
                    'description' => $report->description,
                    'status' => $report->status,
                    'created_at' => $report->created_at,
                    'reviewed_at' => $report->reviewed_at,
                    'reporter' => [
                        'id' => $report->reporter->id,
                        'name' => $report->reporter->name,
                        'email' => $report->reporter->email,
                        'profile_photo_url' => $report->reporter->profile_photo_url ?? null,
                    ],
                    'reported_profile' => [
                        'id' => $report->reportedProfile->id,
                        'name' => $report->reportedProfile->name,
                        'gender' => $report->reportedProfile->gender,
                        'main_photo_path' => $report->reportedProfile->main_photo_path,
                    ],
                    'reported_user' => $report->reportedUser ? [
                        'id' => $report->reportedUser->id,
                        'name' => $report->reportedUser->name,
                        'email' => $report->reportedUser->email,
                        'profile_photo_url' => $report->reportedUser->profile_photo_url ?? null,
                    ] : null,
                ];
            });

            // Log des résultats
            Log::info('Résultats de la requête:', [
                'total' => $reports->total(),
                'current_page' => $reports->currentPage(),
                'per_page' => $reports->perPage(),
                'data' => $reports->items()
            ]);

            // Retourner la réponse
            return response()->json([
                'data' => $reports->items(),
                'meta' => [
                    'current_page' => $reports->currentPage(),
                    'from' => $reports->firstItem(),
                    'last_page' => $reports->lastPage(),
                    'path' => $reports->path(),
                    'per_page' => $reports->perPage(),
                    'to' => $reports->lastItem(),
                    'total' => $reports->total(),
                ],
                'links' => $reports->linkCollection()
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors du chargement des signalements:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Une erreur est survenue lors du chargement des signalements'
            ], 500);
        }
    }

    public function accept($id)
    {
        $report = ProfileReport::findOrFail($id);

        $report->update([
            'status' => 'accepted',
            'reviewed_at' => now()
        ]);

        // Ici, vous pouvez ajouter d'autres actions à effectuer lorsqu'un signalement est accepté
        // Par exemple, bloquer le profil signalé, envoyer des notifications, etc.

        return response()->json(['success' => true]);
    }

    public function dismiss($id)
    {
        $report = ProfileReport::findOrFail($id);

        $report->update([
            'status' => 'dismissed',
            'reviewed_at' => now()
        ]);

        return response()->json(['success' => true]);
    }

    public function show($id)
    {
        try {
            $report = ProfileReport::with(['reporter', 'reportedUser', 'reportedProfile'])
                ->findOrFail($id);

            return Inertia::render('Reports/Show', [
                'report' => [
                    'id' => $report->id,
                    'reason' => $report->reason,
                    'description' => $report->description,
                    'status' => $report->status,
                    'created_at' => $report->created_at,
                    'reviewed_at' => $report->reviewed_at,
                    'reporter' => [
                        'id' => $report->reporter->id,
                        'name' => $report->reporter->name,
                        'email' => $report->reporter->email,
                    ],
                    'reported_profile' => [
                        'id' => $report->reportedProfile->id,
                        'name' => $report->reportedProfile->name,
                        'gender' => $report->reportedProfile->gender,
                        'main_photo_path' => $report->reportedProfile->main_photo_path,
                    ],
                    'reported_user' => $report->reportedUser ? [
                        'id' => $report->reportedUser->id,
                        'name' => $report->reportedUser->name,
                        'email' => $report->reportedUser->email,
                    ] : null,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors du chargement du signalement: ' . $e->getMessage());
            return redirect()->route('admin.reports.index')
                ->with('error', 'Le signalement demandé n\'existe pas ou n\'est plus accessible.');
        }
    }
}
