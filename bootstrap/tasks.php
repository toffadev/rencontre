<?php

use App\Tasks\ProcessUnassignedMessagesTask;
use App\Tasks\ProcessUrgentMessagesTask;
use App\Tasks\RotateModeratorProfilesTask;
use App\Tasks\ProfileAssignmentMonitoringTask;


return [
    // Autres tâches déjà enregistrées

    // Notre tâche pour traiter les messages non assignés (toutes les 30 secondes)
    ProcessUnassignedMessagesTask::class,

    // Notre tâche pour traiter les messages urgents (non répondus depuis 2+ minutes)
    ProcessUrgentMessagesTask::class,

    // Notre tâche pour tourner les profils des modérateurs (toutes les 60 secondes)
    RotateModeratorProfilesTask::class,

    // Notre tâche pour surveiller les assignations de profils (toutes les 15 secondes)
    ProfileAssignmentMonitoringTask::class,
];
