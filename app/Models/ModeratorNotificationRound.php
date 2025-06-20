<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class ModeratorNotificationRound extends Model
{
    use HasFactory;

    protected $fillable = [
        'round_number',
        'moderator_ids_notified',
        'sent_at',
        'pending_messages_count',
    ];

    protected $casts = [
        'moderator_ids_notified' => 'array',
        'sent_at' => 'datetime',
    ];
}
