<?php

namespace App\Services;

use App\Models\User;
use App\Models\Profile;
use App\Models\ProfilePointTransaction;
use App\Models\ProfileClientInteraction;
use App\Models\ModeratorProfileAssignment;
use App\Events\ProfilePointsPurchased;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Log;

class ProfilePointService
{
    /**
     * Crée une transaction de points pour un profil
     */
    public function createProfilePointTransaction(User $client, Profile $profile, int $points, array $transactionData): ?ProfilePointTransaction
    {
        try {
            return DB::transaction(function () use ($client, $profile, $points, $transactionData) {
                // Créer la transaction avec le statut 'pending'
                $transaction = ProfilePointTransaction::create([
                    'client_id' => $client->id,
                    'profile_id' => $profile->id,
                    'points_amount' => $points,
                    'money_amount' => $transactionData['money_amount'],
                    'stripe_payment_id' => $transactionData['stripe_payment_id'] ?? null,
                    'stripe_session_id' => $transactionData['stripe_session_id'] ?? null,
                    'status' => 'pending'
                ]);

                return $transaction;
            });
        } catch (Exception $e) {
            Log::error('Erreur lors de la création de la transaction de points pour le profil:', [
                'error' => $e->getMessage(),
                'client_id' => $client->id,
                'profile_id' => $profile->id,
                'points' => $points
            ]);
            return null;
        }
    }

    /**
     * Traite une transaction de points réussie
     */
    public function processSuccessfulTransaction(ProfilePointTransaction $transaction): bool
    {
        try {
            return DB::transaction(function () use ($transaction) {
                // 1. Trouver le dernier modérateur ayant interagi avec ce client via ce profil
                $interaction = ProfileClientInteraction::where('profile_id', $transaction->profile_id)
                    ->where('client_id', $transaction->client_id)
                    ->first();

                $moderatorId = null;

                if ($interaction && $interaction->last_moderator_id) {
                    $moderatorId = $interaction->last_moderator_id;
                } else {
                    // Si pas d'interaction, chercher le modérateur actuellement assigné au profil
                    $assignment = ModeratorProfileAssignment::where('profile_id', $transaction->profile_id)
                        ->where('is_active', true)
                        ->first();

                    if ($assignment) {
                        $moderatorId = $assignment->user_id;
                    }
                }

                // 2. Si un modérateur est trouvé, lui attribuer les points
                if ($moderatorId) {
                    $moderator = User::find($moderatorId);
                    if ($moderator) {
                        $moderator->increment('points', $transaction->points_amount);
                        $transaction->moderator_id = $moderatorId;
                    }
                }

                // 3. Mettre à jour la transaction
                $transaction->status = 'completed';
                $transaction->credited_at = now();
                $transaction->save();

                // 4. Mettre à jour ou créer l'interaction
                if (!$interaction) {
                    $interaction = new ProfileClientInteraction([
                        'profile_id' => $transaction->profile_id,
                        'client_id' => $transaction->client_id,
                        'last_moderator_id' => $moderatorId,
                        'total_points_received' => $transaction->points_amount
                    ]);
                } else {
                    $interaction->total_points_received += $transaction->points_amount;
                    if ($moderatorId) {
                        $interaction->last_moderator_id = $moderatorId;
                    }
                }
                $interaction->save();

                // 5. Dispatch l'événement de transaction réussie
                event(new ProfilePointsPurchased($transaction));

                return true;
            });
        } catch (Exception $e) {
            Log::error('Erreur lors du traitement de la transaction de points:', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id
            ]);
            return false;
        }
    }

    /**
     * Récupère l'historique des transactions pour un profil
     */
    public function getProfileTransactionHistory(Profile $profile)
    {
        return ProfilePointTransaction::where('profile_id', $profile->id)
            ->where('status', 'completed')
            ->with(['client', 'moderator'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Récupère l'historique des transactions pour un modérateur
     */
    public function getModeratorTransactionHistory(User $moderator)
    {
        return ProfilePointTransaction::where('moderator_id', $moderator->id)
            ->where('status', 'completed')
            ->with(['client', 'profile'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Récupère l'historique des transactions pour un client
     */
    public function getClientTransactionHistory(User $client)
    {
        return ProfilePointTransaction::where('client_id', $client->id)
            ->with(['profile', 'moderator'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
