<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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
        'last_activity',
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
        'last_activity' => 'datetime',
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
}
