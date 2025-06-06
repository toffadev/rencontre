<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProfile extends Model
{
    protected $fillable = [
        'user_id',
        'sexual_orientation',
        'seeking_gender',
        'bio',
        'profile_photo_path',
        'birth_date',
        'city',
        'country',
        'relationship_status',
        'height',
        'occupation',
        'has_children',
        'wants_children',
        'profile_completed'
    ];

    protected $casts = [
        'birth_date' => 'date',
        'has_children' => 'boolean',
        'wants_children' => 'boolean',
        'profile_completed' => 'boolean',
    ];

    /**
     * Get the user that owns the profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the profile photo URL attribute.
     *
     * @return string
     */
    public function getProfilePhotoUrlAttribute()
    {
        return $this->profile_photo_path
            ? asset('storage/' . $this->profile_photo_path)
            : 'https://ui-avatars.com/api/?name=' . urlencode($this->user->name) . '&color=7F9CF5&background=EBF4FF';
    }
}
