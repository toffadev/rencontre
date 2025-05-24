<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'type',
        'points',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if user is admin
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->type === 'admin';
    }

    /**
     * Check if user is moderator
     *
     * @return bool
     */
    public function isModerator(): bool
    {
        return $this->type === 'moderateur';
    }

    /**
     * Check if user is client
     *
     * @return bool
     */
    public function isClient(): bool
    {
        return $this->type === 'client';
    }

    /**
     * Get messages associated with this user
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function messages()
    {
        return $this->hasMany(Message::class, 'client_id');
    }

    /**
     * Get the basic information for this client
     */
    public function clientInfo()
    {
        return $this->hasOne(ClientInfo::class);
    }

    /**
     * Get all custom information for this client
     */
    public function customInfos()
    {
        return $this->hasMany(ClientCustomInfo::class);
    }
}
