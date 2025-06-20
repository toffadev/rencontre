<?php

namespace App\Jobs;

use App\Models\ModeratorNotificationRound;
use App\Models\PendingClientMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckNotificationRound implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * L'ID du round à vérifier
     */
    protected $roundId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $roundId)
    {
        $this->roundId = $roundId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Vérification du round #{$this->roundId}");

        $round = ModeratorNotificationRound::find($this->roundId);

        if (!$round) {
            Log::error("Round #{$this->roundId} non trouvé");
            return;
        }

        // Vérifier si des messages sont toujours en attente
        $pendingMessagesCount = PendingClientMessage::where('is_processed', false)->count();

        if ($pendingMessagesCount === 0) {
            Log::info("Plus aucun message en attente, fin du processus");
            return;
        }

        // Vérifier si des modérateurs notifiés se sont connectés
        $notifiedModerators = User::whereIn('id', $round->moderator_ids_notified)->get();
        $respondedCount = 0;

        foreach ($notifiedModerators as $moderator) {
            // Si le modérateur s'est connecté après l'envoi de la notification
            if (
                $moderator->is_online ||
                ($moderator->last_online_at && $moderator->last_online_at->gt($round->sent_at))
            ) {
                $respondedCount++;
            }
        }

        Log::info("Nombre de modérateurs ayant répondu au round #{$round->round_number}: {$respondedCount}");

        // Si moins de 2 modérateurs ont répondu, lancer un nouveau round
        if ($respondedCount < 2 && $pendingMessagesCount > 0) {
            Log::info("Réponse insuffisante, lancement d'un nouveau round");
            ProcessPendingMessages::dispatch();
        } else {
            Log::info("Réponse suffisante ou plus de messages en attente, fin du processus");
        }
    }
}
