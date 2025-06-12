<?php

use App\Tasks\ProcessUnassignedMessagesTask;
use App\Tasks\ProcessUrgentMessagesTask;

return [
    // Autres tâches déjà enregistrées

    // Notre tâche pour traiter les messages non assignés (toutes les 30 secondes)
    ProcessUnassignedMessagesTask::class,

    // Notre tâche pour traiter les messages urgents (non répondus depuis 2+ minutes)
    ProcessUrgentMessagesTask::class,
];
