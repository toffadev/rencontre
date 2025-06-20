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
        'role',
        'is_online',
        'last_online_at',
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

    /**
     * Get the point consumptions for this user
     */
    public function pointConsumptions()
    {
        return $this->hasMany(PointConsumption::class);
    }

    /**
     * Get the moderator profile assignments for the user
     */
    public function moderatorProfileAssignments()
    {
        return $this->hasMany(ModeratorProfileAssignment::class);
    }

    /**
     * Get the point transactions for this user
     */
    public function pointTransactions()
    {
        return $this->hasMany(PointTransaction::class);
    }

    /**
     * Get reports made by this user
     */
    public function reportsMade()
    {
        return $this->hasMany(ProfileReport::class, 'reporter_id');
    }

    /**
     * Get reports where this user is reported
     */
    public function reportsReceived()
    {
        return $this->hasMany(ProfileReport::class, 'reported_user_id');
    }

    /**
     * Get the URL for the user's profile photo.
     *
     * @return string
     */
    public function getProfilePhotoUrlAttribute()
    {
        // Pour l'instant, on retourne une image par défaut
        // Vous pouvez personnaliser ceci plus tard pour utiliser une vraie photo de profil
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&color=7F9CF5&background=EBF4FF';
    }

    /**
     * Get the client profile associated with the user.
     */
    public function clientProfile()
    {
        return $this->hasOne(ClientProfile::class);
    }

    /**
     * Get the client notifications for this user
     */
    public function clientNotifications()
    {
        return $this->hasMany(ClientNotification::class);
    }

    /**
     * Update the last activity timestamp
     */
    public function updateLastActivity()
    {
        $this->last_activity_at = now();
        $this->save();
    }

    /**
     * Update the online status and last online time
     *
     * @param bool $isOnline
     * @return void
     */
    public function updateOnlineStatus(bool $isOnline): void
    {
        $this->is_online = $isOnline;

        if ($isOnline) {
            $this->last_online_at = now();
        }

        $this->save();
    }

    /**
     * Count active conversations for this moderator
     *
     * @return int
     */
    public function getActiveConversationsCount(): int
    {
        if ($this->type !== 'moderateur') {
            return 0;
        }

        // Compte les conversations distinctes (combinaison client_id et profile_id)
        // où ce modérateur est impliqué et qui ont eu une activité récente
        return Message::where('moderator_id', $this->id)
            ->where('created_at', '>', now()->subDays(7))
            ->select('client_id', 'profile_id')
            ->distinct()
            ->count();
    }
}
