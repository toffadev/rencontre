<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModeratorStatistic extends Model
{
    protected $fillable = [
        'user_id',
        'profile_id',
        'short_messages_count',
        'long_messages_count',
        'points_received',
        'earnings',
        'stats_date'
    ];

    protected $casts = [
        'stats_date' => 'date',
        'earnings' => 'decimal:2'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    // Méthodes utilitaires
    public function getTotalMessagesAttribute(): int
    {
        return $this->short_messages_count + $this->long_messages_count;
    }

    public function getMessageQualityRateAttribute(): float
    {
        $total = $this->getTotalMessagesAttribute();
        if ($total === 0) return 0;
        return ($this->long_messages_count / $total) * 100;
    }
}
