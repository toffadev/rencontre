<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ModeratorQueueService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class QueueController extends Controller
{
    protected $queueService;

    public function __construct(ModeratorQueueService $queueService)
    {
        $this->queueService = $queueService;
    }

    /**
     * Obtenir le statut actuel de la file d'attente
     */
    public function getQueueStatus()
    {
        try {
            $moderator = Auth::user();
            $position = $this->queueService->getQueuePosition($moderator->id);

            $response = [
                'in_queue' => $position !== null,
                'position' => $position,
                'queue_status' => $this->queueService->getQueueStatus()
            ];

            if ($position !== null) {
                $queuedModerator = \App\Models\ModeratorQueue::where('moderator_id', $moderator->id)->first();

                if ($queuedModerator) {
                    $response['estimated_wait_time'] = $queuedModerator->estimated_wait_time;
                    $response['queued_at'] = $queuedModerator->queued_at;
                }
            }

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Erreur dans getQueueStatus: ' . $e->getMessage());

            // Retourner une réponse par défaut en cas d'erreur
            return response()->json([
                'in_queue' => false,
                'position' => null,
                'queue_status' => ['total' => 0, 'active' => 0]
            ]);
        }
    }

    /**
     * Demander un changement de priorité dans la file d'attente
     */
    public function requestPriorityChange(Request $request)
    {
        $request->validate([
            'reason' => 'required|string|max:255'
        ]);

        $moderator = Auth::user();
        $currentPosition = $this->queueService->getQueuePosition($moderator->id);

        if ($currentPosition === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vous n\'êtes pas dans la file d\'attente'
            ], 400);
        }

        // Ajouter à la file d'attente avec une priorité plus élevée (10)
        $this->queueService->addToQueue($moderator->id, 10);

        // Réorganiser la file d'attente
        $this->queueService->reorderQueue();

        $newPosition = $this->queueService->getQueuePosition($moderator->id);

        return response()->json([
            'status' => 'success',
            'previous_position' => $currentPosition,
            'new_position' => $newPosition,
            'message' => 'Votre demande de priorité a été prise en compte'
        ]);
    }

    /**
     * Quitter la file d'attente
     */
    public function leaveQueue()
    {
        $moderator = Auth::user();
        $currentPosition = $this->queueService->getQueuePosition($moderator->id);

        if ($currentPosition === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vous n\'êtes pas dans la file d\'attente'
            ], 400);
        }

        // Retirer de la file d'attente
        $this->queueService->removeFromQueue($moderator->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Vous avez quitté la file d\'attente'
        ]);
    }
}
