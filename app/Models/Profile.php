<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Profile extends Model
{
    protected $fillable = [
        'name',
        'gender',
        'bio',
        'main_photo_path',
        'status',
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
}
