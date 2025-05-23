<?php

use App\Tasks\ProcessUnassignedMessagesTask;

return [
    // Autres tâches déjà enregistrées

    // Notre tâche pour traiter les messages non assignés
    ProcessUnassignedMessagesTask::class,
];
