<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientLock extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'client_id',
        'profile_id',
        'moderator_id',
        'locked_at',
        'expires_at',
        'lock_reason',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the client that owns the lock.
     */
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Get the profile that owns the lock.
     */
    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Get the moderator that owns the lock.
     */
    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    /**
     * Scope a query to only include active locks.
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }
}
