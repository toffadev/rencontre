<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ClientCustomInfo;

class ClientCustomInfoPolicy
{
    /**
     * Determine if the user can delete the custom info.
     */
    public function delete(User $user, ClientCustomInfo $customInfo): bool
    {
        // Un modérateur peut supprimer une info s'il l'a ajoutée lui-même
        if ($user->type === 'moderateur' && $customInfo->added_by === $user->id) {
            return true;
        }

        // Un admin peut supprimer n'importe quelle info
        return $user->type === 'admin';
    }
}
