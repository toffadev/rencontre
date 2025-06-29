<?php

namespace App\Services;

use App\Models\ModeratorProfileAssignment;
use App\Models\User;
use App\Models\Profile;
use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Service de gestion de l'équilibrage de charge entre les modérateurs.
 * 
 * Ce service permet de :
 * - Calculer un score de disponibilité pour chaque modérateur en fonction
 *   du nombre de conversations actives qu'il gère.
 * - Détecter les déséquilibres de charge (quand un modérateur a beaucoup plus
 *   de clients qu'un autre).
 * - Redistribuer certains clients d'un modérateur surchargé vers un modérateur
 *   moins occupé, afin d'équilibrer la charge de travail.
 * - Proposer une attribution optimale d'un client à un modérateur, en
 *   privilégiant la continuité de la conversation ou en choisissant le modérateur
 *   le plus disponible.
 * - Générer des rapports pour visualiser l'état global de la charge des modérateurs.
 * 
 * L'objectif est d'améliorer la répartition du travail, d'éviter la surcharge
 * de certains modérateurs, et de maintenir une expérience client fluide.
 */
class LoadBalancingService
{
    /**
     * Calculer les scores de disponibilité des modérateurs
     */
    public function calculateModeratorScores()
    {
        $moderators = User::where('type', 'moderateur')
            ->where('status', 'active')
            ->get();

        $scores = [];

        foreach ($moderators as $moderator) {
            $assignments = ModeratorProfileAssignment::where('user_id', $moderator->id)
                ->where('is_active', true)
                ->get();

            $totalConversations = 0;
            $profileCount = $assignments->count();

            foreach ($assignments as $assignment) {
                $totalConversations += $assignment->active_conversations_count;
            }

            // Calculer le score: 100 - (conversations actives × 20)
            $score = 100 - ($totalConversations * 20);
            $score = max(0, min(100, $score));

            $scores[$moderator->id] = [
                'moderator_id' => $moderator->id,
                'moderator_name' => $moderator->name,
                'score' => $score,
                'active_conversations' => $totalConversations,
                'profile_count' => $profileCount,
                'status' => $score > 50 ? 'disponible' : ($score > 20 ? 'occupé' : 'surchargé')
            ];
        }

        return $scores;
    }

    /**
     * Détecter les déséquilibres de charge
     */
    public function detectImbalance()
    {
        $scores = $this->calculateModeratorScores();

        if (count($scores) < 2) {
            return false; // Pas assez de modérateurs pour un déséquilibre
        }

        // Trier par score (du plus élevé au plus bas)
        uasort($scores, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $scoreValues = array_column($scores, 'score');
        $conversationCounts = array_column($scores, 'active_conversations');

        // Seuil de déséquilibre = 2 clients de différence
        $maxConversations = max($conversationCounts);
        $minConversations = min($conversationCounts);
        $imbalance = $maxConversations - $minConversations;

        // Vérifier si le déséquilibre est suffisant et si la dernière réattribution
        // date d'au moins 5 minutes (délai anti-ping-pong)
        $lastRebalance = Cache::get('last_rebalance_time');
        $canRebalance = !$lastRebalance || Carbon::parse($lastRebalance)->diffInMinutes(now()) >= 5;

        if ($imbalance >= 2 && $canRebalance) {
            $mostBusyModerator = array_keys($scores)[count($scores) - 1];
            $leastBusyModerator = array_keys($scores)[0];

            return [
                'imbalance_detected' => true,
                'difference' => $imbalance,
                'most_busy' => $scores[$mostBusyModerator],
                'least_busy' => $scores[$leastBusyModerator]
            ];
        }

        return false;
    }

    /**
     * Redistribuer les clients
     */
    public function redistributeClients()
    {
        $imbalance = $this->detectImbalance();

        if (!$imbalance || !$imbalance['imbalance_detected']) {
            return false;
        }

        $mostBusyModeratorId = $imbalance['most_busy']['moderator_id'];
        $leastBusyModeratorId = $imbalance['least_busy']['moderator_id'];

        // Obtenir les attributions du modérateur le plus occupé
        $busyAssignments = ModeratorProfileAssignment::where('user_id', $mostBusyModeratorId)
            ->where('is_active', true)
            ->orderBy('active_conversations_count', 'desc')
            ->get();

        if ($busyAssignments->isEmpty()) {
            return false;
        }

        $clientsRedistributed = 0;

        // Parcourir les attributions et redistribuer les clients
        foreach ($busyAssignments as $assignment) {
            // Ne redistribuer que si le modérateur a au moins 2 clients de plus
            if ($assignment->active_conversations_count <= 1) {
                continue;
            }

            // Vérifier si le modérateur moins occupé a déjà ce profil
            $targetAssignment = ModeratorProfileAssignment::where('user_id', $leastBusyModeratorId)
                ->where('profile_id', $assignment->profile_id)
                ->where('is_active', true)
                ->first();

            if (!$targetAssignment) {
                // Si le modérateur n'a pas ce profil, lui attribuer
                $targetAssignment = new ModeratorProfileAssignment([
                    'user_id' => $leastBusyModeratorId,
                    'profile_id' => $assignment->profile_id,
                    'is_active' => true,
                    'is_primary' => false,
                    'conversation_ids' => [],
                    'active_conversations_count' => 0,
                    'last_activity' => now(),
                    'assigned_at' => now(),
                    'last_activity_check' => now()
                ]);

                $targetAssignment->save();
            }

            // Transférer un client du modérateur occupé au modérateur moins occupé
            $conversationIds = $assignment->conversation_ids ?? [];

            if (!empty($conversationIds)) {
                $clientToTransfer = array_pop($conversationIds);

                // Retirer le client du modérateur occupé
                $assignment->conversation_ids = $conversationIds;
                $assignment->active_conversations_count = count($conversationIds);
                $assignment->save();

                // Ajouter le client au modérateur moins occupé
                $targetAssignment->addConversation($clientToTransfer);

                $clientsRedistributed++;

                // Ne redistribuer qu'un client à la fois pour éviter les surcharges
                break;
            }
        }

        if ($clientsRedistributed > 0) {
            // Enregistrer l'heure de la dernière réattribution
            Cache::put('last_rebalance_time', now()->toIso8601String(), 60 * 5); // 5 minutes

            Log::info("Clients redistribués pour équilibrage de charge", [
                'clients_redistributed' => $clientsRedistributed,
                'from_moderator' => $mostBusyModeratorId,
                'to_moderator' => $leastBusyModeratorId
            ]);
        }

        return $clientsRedistributed;
    }

    /**
     * Obtenir l'attribution optimale pour un client
     */
    public function getOptimalAssignment($clientId, $profileId)
    {
        // Vérifier si le client a des messages récents avec ce profil
        $recentMessage = Message::where('client_id', $clientId)
            ->where('profile_id', $profileId)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($recentMessage && $recentMessage->created_at->diffInMinutes(now()) < 30) {
            // Si message récent, privilégier la continuité
            $existingAssignment = ModeratorProfileAssignment::where('profile_id', $profileId)
                ->where('is_active', true)
                ->whereRaw('JSON_CONTAINS(conversation_ids, ?)', [json_encode($clientId)])
                ->first();

            if ($existingAssignment) {
                return [
                    'type' => 'existing',
                    'moderator_id' => $existingAssignment->user_id,
                    'assignment_id' => $existingAssignment->id
                ];
            }
        }

        // Sinon, trouver le modérateur le moins occupé
        $scores = $this->calculateModeratorScores();

        // Trier par score (du plus élevé au plus bas)
        uasort($scores, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        if (empty($scores)) {
            return null;
        }

        // Prendre le modérateur avec le meilleur score
        $bestModeratorId = array_keys($scores)[0];

        return [
            'type' => 'new',
            'moderator_id' => $bestModeratorId,
            'score' => $scores[$bestModeratorId]['score']
        ];
    }

    /**
     * Rééquilibrer les attributions actives
     */
    public function rebalanceActiveAssignments()
    {
        // Détecter les déséquilibres et redistribuer si nécessaire
        $result = $this->redistributeClients();

        if ($result) {
            return [
                'status' => 'rebalanced',
                'clients_moved' => $result
            ];
        }

        return [
            'status' => 'balanced',
            'clients_moved' => 0
        ];
    }

    /**
     * Générer un rapport de charge
     */
    public function generateLoadReport()
    {
        $scores = $this->calculateModeratorScores();
        $totalModerateurs = count($scores);
        $totalConversations = array_sum(array_column($scores, 'active_conversations'));

        $statusCounts = [
            'disponible' => 0,
            'occupé' => 0,
            'surchargé' => 0
        ];

        foreach ($scores as $score) {
            $statusCounts[$score['status']]++;
        }

        return [
            'timestamp' => now()->toIso8601String(),
            'total_moderateurs' => $totalModerateurs,
            'total_conversations' => $totalConversations,
            'moyenne_conversations' => $totalModerateurs ? round($totalConversations / $totalModerateurs, 2) : 0,
            'status_distribution' => $statusCounts,
            'moderateurs' => $scores
        ];
    }
}
