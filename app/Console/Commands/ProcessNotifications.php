<?php

namespace App\Console\Commands;

use App\Jobs\SendAwaitingReplyNotification;
use App\Jobs\SendReactivationNotification;
use App\Jobs\SendUnreadMessageNotification;
use App\Jobs\ProcessPendingMessages;
use App\Jobs\CheckNotificationRound;
use Illuminate\Console\Command;

class ProcessNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Traite et envoie les notifications aux clients';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Démarrage du traitement des notifications...');

        // Traiter les notifications pour messages non lus (30 min)
        $this->info('Traitement des notifications pour messages non lus...');
        SendUnreadMessageNotification::dispatch();

        // Traiter les notifications pour messages en attente de réponse (2h)
        $this->info('Traitement des notifications pour messages en attente de réponse...');
        SendAwaitingReplyNotification::dispatch();

        // Traiter les notifications de réactivation (48h)
        $this->info('Traitement des notifications de réactivation...');
        SendReactivationNotification::dispatch();

        // Traiter les notifications pour messages en attente chez le modérateur (30 min)
        $this->info('Traitement des notifications pour messages en attente chez le modérateur...');
        ProcessPendingMessages::dispatch();

        // Vérifier les rounds de notification (30 min)
        $this->info('Vérification des rounds de notification...');
        $lastRound = \App\Models\ModeratorNotificationRound::latest()->first();
        if ($lastRound) {
            CheckNotificationRound::dispatch($lastRound->id);
        } else {
            $this->info('Aucun round de notification à vérifier.');
        }

        $this->info('Traitement des notifications terminé !');
    }
}
