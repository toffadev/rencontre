<?php

namespace App\Services;

use App\Models\User;
use App\Models\PointTransaction;
use App\Models\PointConsumption;
use Illuminate\Support\Facades\DB;
use Exception;

class PointService
{
    // Coût en points par message
    const POINTS_PER_MESSAGE = 2;

    // Points bonus à l'inscription
    const INITIAL_BONUS_POINTS = 20;

    /**
     * Vérifie si l'utilisateur a assez de points
     */
    public function hasEnoughPoints(User $user, int $points): bool
    {
        return $user->points >= $points;
    }

    /**
     * Ajoute des points à un utilisateur
     */
    public function addPoints(User $user, int $points, array $transactionData): PointTransaction
    {
        return DB::transaction(function () use ($user, $points, $transactionData) {
            // Mettre à jour les points de l'utilisateur
            $user->increment('points', $points);

            // Créer la transaction
            return PointTransaction::create([
                'user_id' => $user->id,
                'points_amount' => $points,
                'type' => $transactionData['type'],
                'money_amount' => $transactionData['money_amount'] ?? null,
                'stripe_payment_id' => $transactionData['stripe_payment_id'] ?? null,
                'stripe_session_id' => $transactionData['stripe_session_id'] ?? null,
                'description' => $transactionData['description'] ?? null,
                'status' => 'completed'
            ]);
        });
    }

    /**
     * Déduit des points pour une action
     */
    public function deductPoints(User $user, string $type, int $points, $consumable = null): bool
    {
        if (!$this->hasEnoughPoints($user, $points)) {
            return false;
        }

        try {
            DB::transaction(function () use ($user, $type, $points, $consumable) {
                // Déduire les points
                $user->decrement('points', $points);

                // Enregistrer la consommation
                $consumption = [
                    'user_id' => $user->id,
                    'type' => $type,
                    'points_spent' => $points,
                    'description' => "Points utilisés pour {$type}"
                ];

                if ($consumable) {
                    $consumption['consumable_type'] = get_class($consumable);
                    $consumption['consumable_id'] = $consumable->id;
                } else {
                    // Si pas de consumable, on met des valeurs par défaut pour satisfaire la contrainte morphs
                    $consumption['consumable_type'] = 'App\\Models\\Message';
                    $consumption['consumable_id'] = 0;
                }

                PointConsumption::create($consumption);
            });

            return true;
        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erreur lors de la déduction des points: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'type' => $type,
                'points' => $points,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Vérifie et déduit les points pour un message
     */
    public function handleMessagePoints(User $user): bool
    {
        return $this->deductPoints($user, 'message_sent', self::POINTS_PER_MESSAGE);
    }

    /**
     * Ajoute les points bonus initiaux à un nouvel utilisateur
     */
    public function addInitialBonus(User $user): void
    {
        $this->addPoints($user, self::INITIAL_BONUS_POINTS, [
            'type' => 'initial_bonus',
            'description' => 'Points bonus de bienvenue'
        ]);
    }

    /**
     * Obtient l'historique des transactions d'un utilisateur
     */
    public function getTransactionHistory(User $user)
    {
        return PointTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Obtient l'historique des consommations d'un utilisateur
     */
    public function getConsumptionHistory(User $user)
    {
        return PointConsumption::where('user_id', $user->id)
            ->with(['consumable' => function ($query) {
                $query->with('profile')->select('id', 'content', 'profile_id', 'read_at');
            }])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    }
}
