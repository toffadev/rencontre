<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PendingClientMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'client_id',
        'profile_id',
        'pending_since',
        'is_notified',
        'is_processed',
    ];

    protected $casts = [
        'pending_since' => 'datetime',
        'is_notified' => 'boolean',
        'is_processed' => 'boolean',
    ];

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }
}
