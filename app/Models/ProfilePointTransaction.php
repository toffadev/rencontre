<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfilePointTransaction extends Model
{
    protected $fillable = [
        'client_id',
        'profile_id',
        'moderator_id',
        'points_amount',
        'money_amount',
        'stripe_payment_id',
        'stripe_session_id',
        'status',
        'credited_at'
    ];

    protected $casts = [
        'points_amount' => 'integer',
        'money_amount' => 'decimal:2',
        'credited_at' => 'datetime'
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}
