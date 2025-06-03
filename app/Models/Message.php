<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
