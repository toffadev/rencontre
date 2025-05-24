<?php

namespace App\Observers;

use App\Models\User;
use App\Services\PointService;

class UserObserver
{
    protected $pointService;

    public function __construct(PointService $pointService)
    {
        $this->pointService = $pointService;
    }

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // Donner les points bonus uniquement aux clients
        if ($user->type === 'client') {
            $this->pointService->addInitialBonus($user);
        }
    }
}
