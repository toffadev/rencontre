<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModeratorQueue extends Model
{
    use HasFactory;

    protected $table = 'moderator_queue';

    protected $fillable = [
        'moderator_id',
        'queued_at',
        'priority',
        'position',
        'estimated_wait_time',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'priority' => 'integer',
        'position' => 'integer',
        'estimated_wait_time' => 'float',
    ];

    /**
     * Get the moderator associated with the queue entry.
     */
    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}
