<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\Message;
use App\Models\ModeratorProfileAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ProfilePerformanceController extends Controller
{
    public function index()
    {
        return Inertia::render('ProfilePerformance');
    }

    public function getData(Request $request)
    {
        try {
            // Active SQL query logging
            DB::enableQueryLog();

            // Récupération des statistiques globales
            $globalStats = [
                'activeProfiles' => Profile::where('status', 'active')->count(),
                'todayMessages' => Message::whereDate('created_at', Carbon::today())->count(),
                'pointsGenerated' => 0,
                'averageResponseRate' => $this->calculateGlobalResponseRate()
            ];

            $query = Profile::with(['user', 'moderatorProfileAssignments.user'])
                ->select('profiles.*')
                ->withCount(['receivedMessages as received_messages'])
                ->withCount(['sentMessages as sent_messages']);

            // Application des filtres
            if ($request->filled('search')) {
                $query->where('name', 'like', "%{$request->search}%");
            }

            if ($request->filled('moderator_id')) {
                $query->whereHas('moderatorProfileAssignments', function ($q) use ($request) {
                    $q->where('user_id', $request->moderator_id)
                        ->where('is_active', true);
                });
            }

            if ($request->filled('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            if ($request->filled('period') && $request->period !== 'all') {
                $date = match ($request->period) {
                    'today' => Carbon::today(),
                    'week' => Carbon::now()->subWeek(),
                    'month' => Carbon::now()->subMonth(),
                    'quarter' => Carbon::now()->subQuarter(),
                    default => null
                };

                if ($date) {
                    $query->whereHas('messages', function ($q) use ($date) {
                        $q->where('created_at', '>=', $date);
                    });
                }
            }

            // Tri
            if ($request->filled('sort')) {
                $sortField = $request->input('sort.field');
                $sortDirection = $request->input('sort.direction', 'desc');

                if ($sortField === 'messages_received') {
                    $query->orderBy('received_messages', $sortDirection);
                } else if ($sortField === 'messages_sent') {
                    $query->orderBy('sent_messages', $sortDirection);
                } else {
                    $query->orderBy($sortField, $sortDirection);
                }
            }

            // Log the query before execution
            \Log::info('Query before execution:', ['sql' => $query->toSql(), 'bindings' => $query->getBindings()]);

            $profiles = $query->paginate(10);

            // Log executed queries
            \Log::info('Executed queries:', DB::getQueryLog());

            $formattedProfiles = $profiles->through(function ($profile) {
                // Log raw profile data
                \Log::info('Raw profile data:', [
                    'id' => $profile->id,
                    'received_messages_count' => $profile->received_messages,
                    'sent_messages_count' => $profile->sent_messages,
                    'all_messages' => $profile->messages()->count()
                ]);

                return [
                    'id' => $profile->id,
                    'name' => $profile->name,
                    'photo' => $profile->main_photo_path,
                    'status' => $profile->status,
                    'moderators' => $profile->moderatorProfileAssignments->map(fn($a) => [
                        'id' => $a->user->id,
                        'name' => $a->user->name,
                        'is_primary' => $a->is_primary
                    ]),
                    'stats' => [
                        'messages_received' => (int) $profile->received_messages,
                        'messages_sent' => (int) $profile->sent_messages,
                        'average_response_time' => $this->calculateAverageResponseTime($profile->messages),
                        'retention_rate' => $this->calculateRetentionRate($profile->messages)
                    ]
                ];
            });

            return response()->json([
                'data' => $formattedProfiles->items(),
                'current_page' => $profiles->currentPage(),
                'last_page' => $profiles->lastPage(),
                'from' => $profiles->firstItem(),
                'to' => $profiles->lastItem(),
                'total' => $profiles->total(),
                'stats' => $globalStats
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur dans getData:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Une erreur est survenue lors du chargement des données',
                'details' => $e->getMessage()
            ], 500);
        } finally {
            // Disable query logging
            DB::disableQueryLog();
        }
    }

    public function getMessages(Request $request, Profile $profile)
    {
        $query = Message::with(['client', 'moderator'])
            ->where('profile_id', $profile->id);

        // Filtres
        if ($request->filled('type')) {
            $query->where('is_from_client', $request->type === 'received');
        }

        if ($request->filled('moderator')) {
            $query->where('moderator_id', $request->moderator);
        }

        if ($request->filled('date')) {
            $dates = explode(',', $request->date);
            if (count($dates) === 2) {
                $query->whereBetween('created_at', $dates);
            }
        }

        $messages = $query->orderBy('created_at', 'desc')
            ->paginate(50)
            ->through(function ($message) {
                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'is_from_client' => $message->is_from_client,
                    'created_at' => $message->created_at,
                    'author_name' => $message->is_from_client ? $message->client->name : $message->moderator->name,
                    'author_avatar' => $message->is_from_client ? $message->client->profile_photo_url : null,
                    'moderator_name' => $message->is_from_client ? null : $message->moderator->name,
                    'points' => $message->points_generated,
                    'conversation_id' => $message->conversation_id
                ];
            });

        if ($request->wantsJson()) {
            return response()->json($messages);
        }

        return Inertia::render('ProfilePerformance/Messages', [
            'messages' => $messages,
            'profile' => $profile->only('id', 'name', 'main_photo_path')
        ]);
    }

    public function getCharts(Profile $profile)
    {
        // Cache des données pour 1 heure
        $cacheKey = "profile_charts_{$profile->id}";

        return Cache::remember($cacheKey, 3600, function () use ($profile) {
            return [
                'activity' => $this->getActivityData($profile),
                'responseTime' => $this->getResponseTimeData($profile),
                'retention' => $this->getRetentionData($profile),
                'points' => $this->getPointsData($profile)
            ];
        });
    }

    public function getTopClients(Profile $profile)
    {
        return Message::where('profile_id', $profile->id)
            ->where('is_from_client', true)
            ->select('client_id')
            ->selectRaw('COUNT(*) as message_count')
            ->selectRaw('SUM(points_cost) as points_spent')
            ->with(['client:id,name,profile_photo_url'])
            ->groupBy('client_id')
            ->orderByDesc('message_count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->client_id,
                    'name' => $item->client->name,
                    'avatar' => $item->client->profile_photo_url,
                    'message_count' => $item->message_count,
                    'points_spent' => $item->points_spent,
                    'last_activity' => $item->client->messages()
                        ->where('profile_id', $item->profile_id)
                        ->latest()
                        ->first()->created_at
                ];
            });
    }

    private function calculateAverageResponseTime($messages)
    {
        if (!$messages) return 0;

        $responseTimes = [];
        $clientMessages = $messages->where('is_from_client', true)->sortBy('created_at');

        foreach ($clientMessages as $clientMessage) {
            $response = $messages
                ->where('is_from_client', false)
                ->where('created_at', '>', $clientMessage->created_at)
                ->sortBy('created_at')
                ->first();

            if ($response) {
                $responseTime = $response->created_at->diffInMinutes($clientMessage->created_at);
                $responseTimes[] = $responseTime;
            }
        }

        return empty($responseTimes) ? 0 : round(array_sum($responseTimes) / count($responseTimes));
    }

    private function calculateRetentionRate($messages)
    {
        if (!$messages) return 0;

        $clients = $messages->where('is_from_client', true)
            ->groupBy('client_id');

        $returningClients = 0;
        $totalClients = $clients->count();

        foreach ($clients as $clientMessages) {
            $firstMessage = $clientMessages->first()->created_at;
            $lastMessage = $clientMessages->last()->created_at;

            if ($lastMessage->diffInDays($firstMessage) >= 7) {
                $returningClients++;
            }
        }

        return $totalClients > 0 ? round(($returningClients / $totalClients) * 100) : 0;
    }

    private function calculateGlobalResponseRate()
    {
        $totalResponses = Message::where('is_from_client', false)->count();
        $totalMessages = Message::where('is_from_client', true)->count();

        return $totalMessages > 0 ? round(($totalResponses / $totalMessages) * 100) : 0;
    }

    private function getActivityData(Profile $profile)
    {
        return Message::where('profile_id', $profile->id)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->limit(30)
            ->get();
    }

    private function getResponseTimeData(Profile $profile)
    {
        // Calcul du temps de réponse moyen par jour
        return Message::where('profile_id', $profile->id)
            ->where('is_from_client', false)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('AVG(response_time) as time'))
            ->groupBy('date')
            ->orderBy('date')
            ->limit(30)
            ->get();
    }

    private function getRetentionData(Profile $profile)
    {
        // Calcul du taux de rétention par période
        $periods = ['7d', '30d', '90d'];
        $data = [];

        foreach ($periods as $period) {
            $days = (int) rtrim($period, 'd');
            $startDate = Carbon::now()->subDays($days);

            $totalClients = Message::where('profile_id', $profile->id)
                ->where('is_from_client', true)
                ->where('created_at', '>=', $startDate)
                ->distinct('client_id')
                ->count('client_id');

            $returningClients = Message::where('profile_id', $profile->id)
                ->where('is_from_client', true)
                ->where('created_at', '>=', $startDate)
                ->groupBy('client_id')
                ->havingRaw('COUNT(DISTINCT DATE(created_at)) > 1')
                ->count();

            $data[] = [
                'period' => $period,
                'rate' => $totalClients > 0 ? round(($returningClients / $totalClients) * 100) : 0
            ];
        }

        return $data;
    }

    private function getPointsData(Profile $profile)
    {
        return Message::where('profile_id', $profile->id)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(points_generated) as points'))
            ->groupBy('date')
            ->orderBy('date')
            ->limit(30)
            ->get();
    }
}
