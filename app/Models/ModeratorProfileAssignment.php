<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ModeratorProfileAssignment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'profile_id',
        'is_active',
        'is_primary',
        'is_exclusive',
        'is_currently_active', // Nouveau
        'last_activity',
        'last_message_sent',   // Nouveau
        'last_typing',         // Nouveau
        'priority_score',      // Nouveau
        'conversation_ids',    // Nouveau
        'active_conversations_count', // Nouveau
        'locked_clients',
        'queue_position',
        'assigned_at',
        'last_activity_check',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_primary' => 'boolean',
        'is_exclusive' => 'boolean',
        'is_currently_active' => 'boolean',
        'last_activity' => 'datetime',
        'last_message_sent' => 'datetime',
        'last_typing' => 'datetime',
        'conversation_ids' => 'array',
        'active_conversations_count' => 'integer',
        'priority_score' => 'integer',
        'locked_clients' => 'array',
        'queue_position' => 'integer',
        'assigned_at' => 'datetime',
        'last_activity_check' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        // Validation lors de la création d'un nouvel enregistrement
        static::creating(function ($assignment) {
            if ($assignment->is_active && $assignment->is_primary) {
                self::validateUniquePrimaryProfile($assignment);
            }
        });

        // Validation lors de la mise à jour d'un enregistrement existant
        static::updating(function ($assignment) {
            // Vérifier si les attributs is_active ou is_primary sont modifiés
            if ($assignment->isDirty(['is_active', 'is_primary'])) {
                if ($assignment->is_active && $assignment->is_primary) {
                    self::validateUniquePrimaryProfile($assignment);
                }
            }
            return true;
        });
    }

    /**
     * Valide qu'un utilisateur n'a qu'un seul profil principal actif.
     *
     * @param ModeratorProfileAssignment $assignment
     * @return void
     * @throws \Exception
     */
    private static function validateUniquePrimaryProfile($assignment)
    {
        $exists = self::where('user_id', $assignment->user_id)
            ->where('is_active', true)
            ->where('is_primary', true)
            ->when($assignment->exists, function ($query) use ($assignment) {
                // Exclure l'enregistrement actuel s'il est en cours de mise à jour
                return $query->where('id', '!=', $assignment->id);
            })
            ->exists();

        if ($exists) {
            Log::warning("Tentative de définir plusieurs profils principaux pour l'utilisateur", [
                'user_id' => $assignment->user_id,
                'profile_id' => $assignment->profile_id
            ]);
            throw new \Exception('Un modérateur ne peut avoir qu\'un seul profil principal actif.');
        }
    }

    /**
     * Ajouter un client à la liste des conversations avec vérification du verrouillage
     */
    public function addConversation($clientId)
    {
        // Vérifier si le client est déjà dans les conversations
        $conversations = $this->conversation_ids ?? [];
        if (in_array($clientId, $conversations)) {
            Log::info("Client déjà dans les conversations", [
                'client_id' => $clientId,
                'assignment_id' => $this->id
            ]);
            return true; // Déjà ajouté, considéré comme un succès
        }

        // NOUVELLE VÉRIFICATION: Vérifier si ce client est déjà attribué à un autre modérateur pour ce même profil
        $otherAssignments = ModeratorProfileAssignment::where('profile_id', $this->profile_id)
            ->where('user_id', '!=', $this->user_id)
            ->where('is_active', true)
            ->get();

        foreach ($otherAssignments as $assignment) {
            $otherConversations = $assignment->conversation_ids ?? [];
            if (in_array($clientId, $otherConversations)) {
                Log::warning("Client déjà attribué à un autre modérateur pour ce profil", [
                    'client_id' => $clientId,
                    'profile_id' => $this->profile_id,
                    'current_moderator' => $this->user_id,
                    'other_moderator' => $assignment->user_id
                ]);
                return false; // Ne pas ajouter ce client car il est déjà attribué à un autre modérateur
            }
        }

        // Ajouter le client à la liste des conversations
        $conversations[] = $clientId;
        $this->conversation_ids = $conversations;
        $this->active_conversations_count = count($conversations);

        // Verrouiller le client immédiatement après l'avoir ajouté à la conversation
        $this->lockClient($clientId);

        // Sauvegarder les modifications
        $result = $this->save();

        if ($result) {
            Log::info("Client ajouté avec succès à la conversation", [
                'client_id' => $clientId,
                'assignment_id' => $this->id,
                'conversation_count' => $this->active_conversations_count
            ]);
        } else {
            Log::error("Échec de l'ajout du client à la conversation", [
                'client_id' => $clientId,
                'assignment_id' => $this->id
            ]);
        }

        return $result;
    }
    /* public function addConversation($clientId)
    {
        // Vérifier si le client est déjà verrouillé par un autre modérateur
        if ($this->isClientLocked($clientId)) {
            Log::warning("Tentative d'ajout d'un client verrouillé", [
                'client_id' => $clientId,
                'assignment_id' => $this->id
            ]);
            return false;
        }

        $conversations = $this->conversation_ids ?? [];
        if (!in_array($clientId, $conversations)) {
            $conversations[] = $clientId;
            $this->conversation_ids = $conversations;
            $this->active_conversations_count = count($conversations);

            // Verrouiller le client immédiatement après l'avoir ajouté à la conversation
            $this->lockClient($clientId);

            $this->save();
            return true;
        }
        return false;
    } */


    /**
     * Retirer un client de la liste des conversations et le déverrouiller
     */
    public function removeConversation($clientId)
    {
        $conversations = $this->conversation_ids ?? [];
        $conversations = array_filter($conversations, function ($id) use ($clientId) {
            return $id != $clientId;
        });
        $this->conversation_ids = array_values($conversations);
        $this->active_conversations_count = count($conversations);

        // Déverrouiller le client lors de sa suppression de la conversation
        $this->unlockClient($clientId);

        $this->save();
        return true;
    }


    // Méthode pour mettre à jour l'activité de frappe
    public function updateTypingActivity()
    {
        $this->last_typing = now();
        $this->last_activity = now();
        $this->save();
    }

    /**
     * Get the user (moderator) associated with this assignment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the profile associated with this assignment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Verrouiller un client pour éviter un double atterrissage
     */
    public function lockClient($clientId, $duration = 30)
    {
        $lockedClients = $this->locked_clients ?? [];
        $lockedClients[$clientId] = [
            'locked_at' => now()->toIso8601String(),
            'expires_at' => now()->addSeconds($duration)->toIso8601String()
        ];

        $this->locked_clients = $lockedClients;
        $this->save();

        Log::info("Client verrouillé", [
            'client_id' => $clientId,
            'assignment_id' => $this->id,
            'duration' => $duration
        ]);

        return true;
    }

    /**
     * Déverrouiller un client
     */
    public function unlockClient($clientId)
    {
        $lockedClients = $this->locked_clients ?? [];
        if (isset($lockedClients[$clientId])) {
            unset($lockedClients[$clientId]);
            $this->locked_clients = $lockedClients;
            $this->save();

            Log::info("Client déverrouillé", [
                'client_id' => $clientId,
                'assignment_id' => $this->id
            ]);

            return true;
        }
        return false;
    }

    /**
     * Vérifier si un client est verrouillé
     */
    public function isClientLocked($clientId)
    {
        $this->cleanExpiredLocks();

        $lockedClients = $this->locked_clients ?? [];
        if (isset($lockedClients[$clientId])) {
            $expiresAt = Carbon::parse($lockedClients[$clientId]['expires_at']);
            return $expiresAt->isFuture();
        }
        return false;
    }

    /**
     * Récupérer tous les clients verrouillés
     */
    public function getLockedClients()
    {
        $this->cleanExpiredLocks();
        return $this->locked_clients ?? [];
    }

    /**
     * Nettoyer les verrous expirés
     */
    public function cleanExpiredLocks()
    {
        $lockedClients = $this->locked_clients ?? [];
        $now = now();

        foreach ($lockedClients as $clientId => $lockInfo) {
            $expiresAt = Carbon::parse($lockInfo['expires_at']);
            if ($expiresAt->isPast()) {
                unset($lockedClients[$clientId]);
            }
        }

        if ($lockedClients != $this->locked_clients) {
            $this->locked_clients = $lockedClients;
            $this->save();
        }

        return true;
    }
}
