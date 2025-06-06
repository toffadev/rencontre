<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PointTransaction;
use App\Models\PointConsumption;
use App\Models\Message;
use App\Models\ClientInfo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ClientManagementController extends Controller
{
    /**
     * Affiche la liste des clients avec leurs statistiques
     */
    public function index(Request $request)
    {
        $query = User::where('type', 'client')
            ->with(['clientInfo', 'pointConsumptions', 'messages'])
            ->withCount(['messages as total_messages'])
            ->withSum('pointConsumptions as points_spent', 'points_spent')
            ->withSum('pointTransactions as points_bought', 'points_amount');

        // Appliquer les filtres
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        if ($request->filled('registration_date')) {
            $date = Carbon::parse($request->registration_date);
            $query->whereDate('created_at', '>=', $date);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('min_spent')) {
            $minSpent = (int) $request->min_spent;
            $query->whereHas('pointConsumptions', function ($q) use ($minSpent) {
                $q->havingRaw('SUM(points_spent) >= ?', [$minSpent]);
            });
        }

        if ($request->has('max_points')) {
            $query->having('points', '<=', $request->max_points);
        }

        // Récupérer les clients paginés
        $clients = $query->paginate(15)->through(function ($client) {
            return [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'registration_date' => $client->created_at->format('Y-m-d'),
                'last_login' => $client->last_login_at?->diffForHumans(),
                'status' => $client->status,
                'total_messages' => $client->total_messages,
                'points_balance' => $client->points,
                'points_spent' => $client->points_spent ?? 0,
                'points_bought' => $client->points_bought ?? 0,
                'total_spent' => $this->calculateTotalSpent($client->id),
                'last_transaction' => $this->getLastTransaction($client->id),
                'most_frequent_pack' => $this->getMostFrequentPack($client->id),
                'active_conversations' => $this->getActiveConversationsCount($client->id),
                'client_info' => $client->clientInfo
            ];
        });

        // Statistiques globales
        $stats = [
            'total_clients' => User::where('type', 'client')->count(),
            'active_clients' => User::where('type', 'client')->where('status', 'active')->count(),
            'total_revenue' => PointTransaction::sum('money_amount'),
            'average_revenue_per_client' => PointTransaction::avg('money_amount'),
            'total_messages' => Message::where('is_from_client', true)->count(),
            'total_points_sold' => PointTransaction::sum('points_amount')
        ];

        return Inertia::render('ClientManagement', [
            'clients' => $clients,
            'stats' => $stats,
            'filters' => $request->all()
        ]);
    }

    /**
     * Affiche les détails d'un client spécifique
     */
    public function show($id)
    {
        $client = User::where('type', 'client')
            ->with(['clientInfo', 'customInfos', 'messages.profile', 'pointConsumptions', 'pointTransactions', 'clientProfile'])
            ->findOrFail($id);

        // Statistiques du client
        $stats = [
            'total_messages' => $client->messages->count(),
            'total_points_bought' => $client->pointTransactions->sum('points_amount'),
            'total_points_spent' => $client->pointConsumptions->sum('points_spent'),
            'total_spent' => $client->pointTransactions->sum('money_amount'),
            'average_messages_per_day' => $this->calculateAverageMessagesPerDay($client),
            'favorite_profiles' => $this->getFavoriteProfiles($client->id),
            'active_conversations' => $this->getActiveConversations($client->id),
            'last_activity' => $this->getLastActivity($client)
        ];

        // Formater les informations du profil
        $profile = $client->clientProfile;
        $profileData = $profile ? [
            'birth_date' => $profile->birth_date ? $profile->birth_date->format('Y-m-d') : null,
            'age' => $profile->birth_date ? $profile->birth_date->age : null,
            'city' => $profile->city,
            'country' => $profile->country,
            'relationship_status' => $this->getRelationshipStatusLabel($profile->relationship_status),
            'height' => $profile->height,
            'occupation' => $profile->occupation,
            'has_children' => $profile->has_children,
            'wants_children' => $profile->wants_children,
            'sexual_orientation' => $this->getOrientationLabel($profile->sexual_orientation),
            'seeking_gender' => $this->getGenderLabel($profile->seeking_gender),
            'bio' => $profile->bio,
            'profile_photo_url' => $profile->profile_photo_url,
            'profile_completed' => $profile->profile_completed,
        ] : null;

        return Inertia::render('ClientDetail', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'registration_date' => $client->created_at->format('Y-m-d'),
                'status' => $client->status,
                'points' => $client->points,
                'profile' => $profileData,
                'custom_infos' => $client->customInfos
            ],
            'stats' => $stats,
            'conversations' => $this->getConversationsData($client->id),
            'financial_history' => $this->getFinancialHistory($client->id),
            'activity_history' => $this->getActivityHistory($client->id)
        ]);
    }

    /**
     * Ajuste les points d'un client
     */
    public function adjustPoints(Request $request, $id)
    {
        $request->validate([
            'points' => 'required|integer',
            'reason' => 'required|string|max:255'
        ]);

        $client = User::findOrFail($id);

        DB::transaction(function () use ($client, $request) {
            // Mettre à jour les points
            $client->points += $request->points;
            $client->save();

            // Créer une transaction pour tracer l'ajustement
            PointTransaction::create([
                'user_id' => $client->id,
                'type' => 'system_adjustment',
                'points_amount' => $request->points,
                'description' => $request->reason,
                'status' => 'completed'
            ]);
        });

        return back()->with('success', 'Points ajustés avec succès');
    }

    private function calculateTotalSpent($clientId)
    {
        return PointTransaction::where('user_id', $clientId)
            ->where('type', '=', 'purchase')
            ->sum('money_amount');
    }

    private function getLastTransaction($clientId)
    {
        return PointTransaction::where('user_id', $clientId)
            ->latest()
            ->first();
    }

    private function getMostFrequentPack($clientId)
    {
        return PointTransaction::where('user_id', $clientId)
            ->where('type', '=', 'purchase')
            ->select('points_amount', DB::raw('COUNT(*) as count'))
            ->groupBy('points_amount')
            ->orderByDesc('count')
            ->first();
    }

    private function getActiveConversationsCount($clientId)
    {
        return Message::where('client_id', $clientId)
            ->where('created_at', '>=', now()->subDays(7))
            ->distinct('profile_id')
            ->count('profile_id');
    }

    private function calculateAverageMessagesPerDay($client)
    {
        $daysSinceRegistration = $client->created_at->diffInDays(now()) ?: 1;
        return round($client->messages->count() / $daysSinceRegistration, 2);
    }

    private function getFavoriteProfiles($clientId)
    {
        return Message::where('client_id', $clientId)
            ->groupBy('profile_id')
            ->select('profile_id', DB::raw('count(*) as message_count'))
            ->with('profile:id,name,main_photo_path')
            ->orderByDesc('message_count')
            ->limit(5)
            ->get();
    }

    private function getActiveConversations($clientId)
    {
        return Message::where('client_id', $clientId)
            ->where('created_at', '>=', now()->subDays(7))
            ->with('profile:id,name,main_photo_path')
            ->distinct('profile_id')
            ->get(['profile_id']);
    }

    private function getLastActivity($client)
    {
        $lastMessage = $client->messages()->latest()->first();
        $lastTransaction = $client->pointTransactions()->latest()->first();
        $lastLogin = $client->last_login_at;

        return collect([$lastMessage?->created_at, $lastTransaction?->created_at, $lastLogin])
            ->filter()
            ->max();
    }

    private function getConversationsData($clientId)
    {
        return Message::where('client_id', $clientId)
            ->with(['profile:id,name,main_photo_path'])
            ->select('profile_id', 'client_id', DB::raw('count(*) as message_count'))
            ->groupBy('profile_id', 'client_id')
            ->get()
            ->map(function ($conversation) {
                $lastMessage = Message::where('client_id', $conversation->client_id)
                    ->where('profile_id', $conversation->profile_id)
                    ->latest()
                    ->first();

                return [
                    'profile' => $conversation->profile,
                    'message_count' => $conversation->message_count,
                    'points_spent' => $conversation->message_count * 2, // 2 points par message
                    'last_message' => $lastMessage ? $lastMessage->created_at->diffForHumans() : null
                ];
            });
    }

    private function getFinancialHistory($clientId)
    {
        return [
            'transactions' => PointTransaction::where('user_id', $clientId)
                ->orderByDesc('created_at')
                ->get(),
            'consumptions' => PointConsumption::where('user_id', $clientId)
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($consumption) {
                    return [
                        'id' => $consumption->id,
                        'created_at' => $consumption->created_at,
                        'points_spent' => $consumption->points_spent,
                        'action' => $this->getConsumptionActionLabel($consumption->type),
                    ];
                })
        ];
    }

    private function getConsumptionActionLabel($type)
    {
        $actions = [
            'message' => 'Envoi de message',
            'gift' => 'Envoi de cadeau',
            'photo_access' => 'Accès aux photos privées',
            'video_call' => 'Appel vidéo',
            'profile_boost' => 'Boost de profil'
        ];

        return $actions[$type] ?? 'Action inconnue';
    }

    private function getActivityHistory($clientId)
    {
        // Combiner les messages, transactions et connexions
        $activities = collect();

        // Ajouter les messages
        Message::where('client_id', $clientId)
            ->get()
            ->each(function ($message) use ($activities) {
                $activities->push([
                    'type' => 'message',
                    'date' => $message->created_at,
                    'details' => "Message envoyé au profil {$message->profile->name}",
                    'points' => -2
                ]);
            });

        // Ajouter les transactions
        PointTransaction::where('user_id', $clientId)
            ->get()
            ->each(function ($transaction) use ($activities) {
                $activities->push([
                    'type' => 'transaction',
                    'date' => $transaction->created_at,
                    'details' => "Achat de {$transaction->points_amount} points pour {$transaction->money_amount}€",
                    'points' => $transaction->points_amount
                ]);
            });

        // Trier par date décroissante
        return $activities->sortByDesc('date')->values();
    }

    /**
     * Obtenir le libellé du statut relationnel
     */
    private function getRelationshipStatusLabel($status)
    {
        $labels = [
            'single' => 'Célibataire',
            'divorced' => 'Divorcé(e)',
            'widowed' => 'Veuf/Veuve'
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * Obtenir le libellé de l'orientation sexuelle
     */
    private function getOrientationLabel($orientation)
    {
        $labels = [
            'heterosexual' => 'Hétérosexuel(le)',
            'homosexual' => 'Homosexuel(le)'
        ];

        return $labels[$orientation] ?? $orientation;
    }

    /**
     * Obtenir le libellé du genre recherché
     */
    private function getGenderLabel($gender)
    {
        $labels = [
            'male' => 'Homme',
            'female' => 'Femme'
        ];

        return $labels[$gender] ?? $gender;
    }
}
