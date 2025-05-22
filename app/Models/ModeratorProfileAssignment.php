<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModeratorProfileAssignment extends Model
{
    use HasFactory;

    /**
     * Les attributs qui sont assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'profile_id',
        'is_active',
        'last_activity',
    ];

    /**
     * Les attributs à caster.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'last_activity' => 'datetime',
    ];

    /**
     * Récupère l'utilisateur (modérateur) associé à cette attribution.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Récupère le profil associé à cette attribution.
     */
    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}
