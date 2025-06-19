<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientNotification extends Model
{
    use HasFactory;

    /**
     * Les attributs qui sont assignables en masse.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'message_id',
        'sent_at',
        'opened_at',
    ];

    /**
     * Les attributs à caster.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
    ];

    /**
     * Get the user associated with this notification
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the message associated with this notification
     */
    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Mark the notification as opened
     */
    public function markAsOpened()
    {
        $this->opened_at = now();
        $this->save();
    }
}
