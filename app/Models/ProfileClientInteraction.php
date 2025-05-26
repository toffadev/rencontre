<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileClientInteraction extends Model
{
    protected $fillable = [
        'profile_id',
        'client_id',
        'last_moderator_id',
        'last_message_at',
        'total_messages',
        'total_points_received'
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'total_messages' => 'integer',
        'total_points_received' => 'integer'
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function lastModerator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_moderator_id');
    }
}
