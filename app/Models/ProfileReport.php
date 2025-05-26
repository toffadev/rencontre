<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileReport extends Model
{
    protected $fillable = [
        'reporter_id',
        'reported_user_id',
        'reported_profile_id',
        'reason',
        'description',
        'status',
        'reviewed_at'
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function reportedProfile(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'reported_profile_id');
    }
}
