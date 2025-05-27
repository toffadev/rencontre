<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use App\Models\Profile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class GlobalMessageController extends Controller
{
    public function index()
    {
        return Inertia::render('GlobalMessage');
    }

    public function getMessages(Request $request)
    {
        $query = Message::with(['client:id,name', 'profile:id,name,main_photo_path', 'moderator:id,name'])
            ->select('messages.*');

        // Filtrage par date
        if ($request->date_range) {
            $range = $request->date_range;
            $query->when($range === 'today', function ($q) {
                return $q->whereDate('created_at', today());
            })->when($range === 'yesterday', function ($q) {
                return $q->whereDate('created_at', today()->subDay());
            })->when($range === 'week', function ($q) {
                return $q->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            })->when($range === 'custom' && $request->start_date && $request->end_date, function ($q) use ($request) {
                return $q->whereBetween('created_at', [$request->start_date, $request->end_date]);
            });
        }

        // Filtrage par client
        if ($request->client_id) {
            $query->where('client_id', $request->client_id);
        }

        // Filtrage par profil
        if ($request->profile_id) {
            $query->where('profile_id', $request->profile_id);
        }

        // Filtrage par modérateur
        if ($request->moderator_id) {
            $query->where('moderator_id', $request->moderator_id);
        }

        // Filtrage par type de message
        if ($request->type) {
            $query->where('is_from_client', $request->type === 'client');
        }

        // Filtrage par statut de lecture
        if ($request->read_status) {
            if ($request->read_status === 'read') {
                $query->whereNotNull('read_at');
            } else {
                $query->whereNull('read_at');
            }
        }

        // Tri
        $sortField = $request->sort_field ?? 'created_at';
        $sortDirection = $request->sort_direction ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $messages = $query->paginate($request->per_page ?? 15);

        // Ajouter les points consommés pour chaque message
        $messages->getCollection()->transform(function ($message) {
            $message->points_consumed = $message->pointConsumption ? $message->pointConsumption->points_spent : 0;
            return $message;
        });

        // Statistiques globales
        $stats = [
            'total_messages' => Message::count(),
            'total_unread' => Message::whereNull('read_at')->count(),
            'messages_today' => Message::whereDate('created_at', today())->count(),
            'points_consumed_today' => DB::table('point_consumptions')
                ->whereDate('created_at', today())
                ->sum('points_spent')
        ];

        return response()->json([
            'messages' => $messages,
            'stats' => $stats
        ]);
    }

    public function markAsRead(Request $request)
    {
        $request->validate([
            'message_ids' => 'required|array',
            'message_ids.*' => 'exists:messages,id'
        ]);

        Message::whereIn('id', $request->message_ids)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function markAsUnread(Request $request)
    {
        $request->validate([
            'message_ids' => 'required|array',
            'message_ids.*' => 'exists:messages,id'
        ]);

        Message::whereIn('id', $request->message_ids)
            ->update(['read_at' => null]);

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'message_ids' => 'required|array',
            'message_ids.*' => 'exists:messages,id'
        ]);

        Message::whereIn('id', $request->message_ids)->delete();

        return response()->json(['success' => true]);
    }

    public function getFilters()
    {
        $clients = User::where('type', 'client')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $profiles = Profile::select('id', 'name', 'main_photo_path')
            ->orderBy('name')
            ->get();

        $moderators = User::where('type', 'moderateur')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'clients' => $clients,
            'profiles' => $profiles,
            'moderators' => $moderators
        ]);
    }
}
