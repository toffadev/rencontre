<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientInfo extends Model
{
    protected $fillable = [
        'user_id',
        'age',
        'ville',
        'quartier',
        'profession',
        'celibataire',
        'situation_residence',
        'orientation',
        'loisirs',
        'preference_negative'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
