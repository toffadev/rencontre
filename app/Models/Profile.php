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
        return $this->hasMany(Message::class);
    }
}
