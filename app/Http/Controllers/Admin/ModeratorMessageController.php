<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use App\Models\Profile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ModeratorMessageController extends Controller
{
    public function index(Request $request, $moderator_id)
    {
        // Vérifier que le modérateur existe
        $moderator = User::where('id', $moderator_id)
            ->where('type', 'moderateur')
            ->firstOrFail();

        return Inertia::render('ModeratorMessages', [
            'moderator' => $moderator,
            'filters' => $this->getFilters($moderator_id)
        ]);
    }

    public function getMessages(Request $request, $moderator_id)
    {
        try {
            // Vérifier que le modérateur existe
            $moderator = User::where('id', $moderator_id)
                ->where('type', 'moderateur')
                ->firstOrFail();

            // Base query
            $baseQuery = Message::with(['client', 'profile'])
                ->where('moderator_id', $moderator_id)
                ->where('is_from_client', false);

            // Clone pour les statistiques
            $statsQuery = clone $baseQuery;

            // Filtres
            if ($request->filled('start_date')) {
                $baseQuery->whereDate('created_at', '>=', $request->start_date);
                $statsQuery->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $baseQuery->whereDate('created_at', '<=', $request->end_date);
                $statsQuery->whereDate('created_at', '<=', $request->end_date);
            }

            if ($request->filled('profile_id')) {
                $baseQuery->where('profile_id', $request->profile_id);
                $statsQuery->where('profile_id', $request->profile_id);
            }

            if ($request->filled('client_id')) {
                $baseQuery->where('client_id', $request->client_id);
                $statsQuery->where('client_id', $request->client_id);
            }

            if ($request->filled('length')) {
                if ($request->length === 'short') {
                    $baseQuery->whereRaw('LENGTH(content) < ?', [10]);
                    $statsQuery->whereRaw('LENGTH(content) < ?', [10]);
                } else {
                    $baseQuery->whereRaw('LENGTH(content) >= ?', [10]);
                    $statsQuery->whereRaw('LENGTH(content) >= ?', [10]);
                }
            }

            // Tri
            $sortField = $request->input('sort_field', 'created_at');
            $sortDirection = $request->input('sort_direction', 'desc');
            $baseQuery->orderBy($sortField, $sortDirection);

            // Pagination
            $paginator = $baseQuery->paginate(20);

            // Transform messages
            $transformedMessages = collect($paginator->items())->map(function ($message) {
                return [
                    'id' => $message->id,
                    'created_at' => $message->created_at,
                    'content' => $message->content,
                    'length' => strlen($message->content),
                    'points_earned' => strlen($message->content) >= 10 ? 50 : 25,
                    'profile' => [
                        'id' => $message->profile->id,
                        'name' => $message->profile->name,
                        'photo' => $message->profile->main_photo_path
                    ],
                    'client' => [
                        'id' => $message->client->id,
                        'name' => $message->client->name
                    ]
                ];
            });

            // Statistiques
            $totalMessages = $statsQuery->count();
            $shortMessages = $statsQuery->whereRaw('LENGTH(content) < ?', [10])->count();
            $longMessages = $statsQuery->whereRaw('LENGTH(content) >= ?', [10])->count();
            $totalPoints = $statsQuery->selectRaw('COALESCE(SUM(CASE WHEN LENGTH(content) >= 10 THEN 50 ELSE 25 END), 0) as total_points')
                ->value('total_points');

            $stats = [
                'total_messages' => $totalMessages,
                'short_messages' => $shortMessages,
                'long_messages' => $longMessages,
                'total_points' => $totalPoints
            ];

            return response()->json([
                'messages' => [
                    'data' => $transformedMessages,
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                    'links' => $paginator->linkCollection()->toArray()
                ],
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Une erreur est survenue lors de la récupération des messages',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function getFilters($moderator_id)
    {
        // Récupérer les profils utilisés par ce modérateur
        $profiles = Profile::whereHas('messages', function ($query) use ($moderator_id) {
            $query->where('moderator_id', $moderator_id);
        })->select('id', 'name', 'main_photo_path')->get();

        // Récupérer les clients avec qui ce modérateur a interagi
        $clients = User::whereHas('messages', function ($query) use ($moderator_id) {
            $query->where('moderator_id', $moderator_id);
        })->where('type', 'client')->select('id', 'name')->get();

        return [
            'profiles' => $profiles,
            'clients' => $clients
        ];
    }

    public function getConversation(Request $request)
    {
        $request->validate([
            'moderator_id' => 'required|exists:users,id',
            'client_id' => 'required|exists:users,id',
            'profile_id' => 'required|exists:profiles,id'
        ]);

        $messages = Message::with(['client', 'profile', 'moderator'])
            ->where('client_id', $request->client_id)
            ->where('profile_id', $request->profile_id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'content' => $message->content,
                    'is_from_client' => $message->is_from_client,
                    'created_at' => $message->created_at,
                    'read_at' => $message->read_at,
                    'moderator' => $message->moderator ? [
                        'id' => $message->moderator->id,
                        'name' => $message->moderator->name
                    ] : null
                ];
            });

        return response()->json([
            'messages' => $messages
        ]);
    }
}
