<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PointConsumption extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'points_spent',
        'description',
        'consumable_type',
        'consumable_id'
    ];

    protected $casts = [
        'points_spent' => 'integer'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function consumable(): MorphTo
    {
        return $this->morphTo();
    }
}
