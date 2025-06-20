<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class Message extends Model
{
    use HasFactory;

    /**
     * Les attributs qui sont assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'client_id',
        'profile_id',
        'moderator_id',
        'content',
        'is_from_client',
        'read_at',
    ];

    /**
     * Les attributs à caster.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_from_client' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * Récupère le client associé à ce message.
     */
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Récupère le profil associé à ce message.
     */
    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Récupère le modérateur qui a envoyé ce message (si applicable).
     */
    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    /**
     * Get the notifications associated with this message
     */
    public function notifications()
    {
        return $this->hasMany(ClientNotification::class);
    }

    /**
     * Mark as notification sent
     */
    public function markNotificationSent()
    {
        $this->notification_sent_at = now();
        $this->notification_count = $this->notification_count + 1;
        $this->save();
        return $this;
    }

    /**
     * Get pending client message record if exists
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function pendingClientMessage()
    {
        return $this->hasOne(PendingClientMessage::class);
    }

    /**
     * Create a pending message entry
     *
     * @return PendingClientMessage|null
     */
    public function createPendingEntry()
    {
        // Vérifier si c'est un message du client et sans modérateur assigné
        if (!$this->is_from_client || $this->moderator_id) {
            return null;
        }

        // Vérifier si une entrée existe déjà
        if ($this->pendingClientMessage()->exists()) {
            return $this->pendingClientMessage;
        }

        // Créer une nouvelle entrée
        return PendingClientMessage::create([
            'message_id' => $this->id,
            'client_id' => $this->client_id,
            'profile_id' => $this->profile_id,
            'pending_since' => $this->created_at,
            'is_notified' => false,
            'is_processed' => false,
        ]);
    }
}
