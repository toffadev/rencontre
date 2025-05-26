<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ClientCustomInfo;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientCustomInfoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ClientCustomInfo $customInfo): bool
    {
        // N'importe quel modérateur peut supprimer une information
        return $user->type === 'moderateur';
    }
}
