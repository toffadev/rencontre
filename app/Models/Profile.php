<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    protected $fillable = [
        'name',
        'gender',
        'bio',
        'main_photo_path',
        'status',
        'user_id',
    ];

    public function photos(): HasMany
    {
        return $this->hasMany(ProfilePhoto::class);
    }

    public function mainPhoto(): HasOne
    {
        return $this->hasOne(ProfilePhoto::class)
            ->where('path', $this->main_photo_path)
            ->orWhere(function ($query) {
                $query->orderBy('order')->limit(1);
            });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get messages associated with this profile
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'profile_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'profile_id')->where('is_from_client', true);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'profile_id')->where('is_from_client', false);
    }

    public function moderatorProfileAssignments(): HasMany
    {
        return $this->hasMany(ModeratorProfileAssignment::class);
    }

    /**
     * Get reports for this profile
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ProfileReport::class, 'reported_profile_id');
    }

    /**
     * Get the locks for the profile.
     */
    public function locks()
    {
        return $this->hasMany(ProfileLock::class);
    }

    /**
     * Check if the profile is currently locked.
     */
    public function isLocked()
    {
        return $this->locks()->where('expires_at', '>', now())->exists();
    }
}
