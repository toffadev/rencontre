<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Services\ModeratorAssignmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class MessageListener implements ShouldQueue
{
    /**
     * The moderator assignment service.
     *
     * @var \App\Services\ModeratorAssignmentService
     */
    protected $assignmentService;

    /**
     * Create the event listener.
     */
    public function __construct(ModeratorAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    /**
     * Handle the event.
     */
    public function handle(MessageSent $event): void
    {
        $message = $event->message;

        Log::info("[DEBUG] MessageListener: Message reçu", [
            'message_id' => $message->id,
            'client_id' => $message->client_id,
            'profile_id' => $message->profile_id,
            'is_from_client' => $message->is_from_client,
            'content' => $message->content
        ]);

        // Only process messages from clients
        if (!$message->is_from_client) {
            Log::info("[DEBUG] MessageListener: Message ignoré (non client)");
            return;
        }

        Log::info("[DEBUG] MessageListener: Tentative d'attribution à un modérateur");

        // Try to assign this client message to a moderator
        $moderator = $this->assignmentService->assignClientToModerator(
            $message->client_id,
            $message->profile_id
        );

        if ($moderator) {
            Log::info("[DEBUG] MessageListener: Message attribué au modérateur", [
                'moderator_id' => $moderator->id,
                'moderator_name' => $moderator->name
            ]);
        } else {
            Log::warning("[DEBUG] MessageListener: Aucun modérateur disponible pour ce message");
        }
    }
}
