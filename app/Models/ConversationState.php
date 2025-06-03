<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationState extends Model
{
    protected $fillable = [
        'client_id',
        'profile_id',
        'last_read_message_id',
        'has_been_opened',
        'awaiting_reply'
    ];

    protected $casts = [
        'has_been_opened' => 'boolean',
        'awaiting_reply' => 'boolean'
    ];

    /**
     * Get the client that owns the conversation state.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Get the profile that owns the conversation state.
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Get the last read message.
     */
    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_read_message_id');
    }
}
