<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'points_amount',
        'money_amount',
        'stripe_payment_id',
        'stripe_session_id',
        'description',
        'status'
    ];

    protected $casts = [
        'points_amount' => 'integer',
        'money_amount' => 'decimal:2'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
