<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ProfileLockService;
use Illuminate\Support\Facades\Auth;

class LockController extends Controller
{
    protected $lockService;

    public function __construct(ProfileLockService $lockService)
    {
        $this->lockService = $lockService;
    }

    /**
     * Obtenir le statut des verrous
     */
    public function getLockStatus(Request $request)
    {
        $request->validate([
            'profile_id' => 'nullable|integer|exists:profiles,id',
            'client_id' => 'nullable|integer|exists:users,id'
        ]);

        $response = [];

        if ($request->has('profile_id')) {
            $profileId = $request->input('profile_id');
            $isLocked = $this->lockService->isProfileLocked($profileId);

            $response['profile'] = [
                'profile_id' => $profileId,
                'is_locked' => $isLocked
            ];

            if ($isLocked) {
                $response['profile']['lock_info'] = $this->lockService->getLockInfo($profileId, 'profile');
            }
        }

        if ($request->has('client_id')) {
            $clientId = $request->input('client_id');
            $profileId = $request->input('profile_id');

            $isLocked = $this->lockService->isClientLocked($clientId, $profileId);

            $response['client'] = [
                'client_id' => $clientId,
                'is_locked' => $isLocked
            ];

            if ($isLocked) {
                $response['client']['lock_info'] = $this->lockService->getLockInfo($clientId, 'client');
            }
        }

        return response()->json($response);
    }

    /**
     * Demander le déverrouillage d'un profil ou client
     */
    public function requestUnlock(Request $request)
    {
        $request->validate([
            'profile_id' => 'nullable|integer|exists:profiles,id',
            'client_id' => 'nullable|integer|exists:users,id',
            'reason' => 'nullable|string|max:255'
        ]);

        $moderator = Auth::user();
        $success = false;

        if ($request->has('profile_id')) {
            $profileId = $request->input('profile_id');

            // Vérifier si le modérateur a l'autorisation de déverrouiller ce profil
            $lockInfo = $this->lockService->getLockInfo($profileId, 'profile');

            if ($lockInfo && ($lockInfo['moderator_id'] == $moderator->id || $moderator->hasRole('admin'))) {
                $success = $this->lockService->unlockProfile($profileId);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vous n\'avez pas l\'autorisation de déverrouiller ce profil'
                ], 403);
            }
        }

        if ($request->has('client_id')) {
            $clientId = $request->input('client_id');
            $profileId = $request->input('profile_id');

            // Vérifier si le modérateur a l'autorisation de déverrouiller ce client
            $lockInfo = $this->lockService->getLockInfo($clientId, 'client');

            if ($lockInfo && ($lockInfo['moderator_id'] == $moderator->id || $moderator->hasRole('admin'))) {
                $success = $this->lockService->unlockClient($clientId, $profileId);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vous n\'avez pas l\'autorisation de déverrouiller ce client'
                ], 403);
            }
        }

        if ($success) {
            return response()->json([
                'status' => 'success',
                'message' => 'Ressource déverrouillée avec succès'
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors du déverrouillage de la ressource'
            ], 500);
        }
    }

    /**
     * Étendre la durée d'un verrou
     */
    public function extendLock(Request $request)
    {
        $request->validate([
            'profile_id' => 'nullable|integer|exists:profiles,id',
            'client_id' => 'nullable|integer|exists:users,id',
            'duration' => 'required|integer|min:10|max:300' // Entre 10 sec et 5 min
        ]);

        $moderator = Auth::user();
        $duration = $request->input('duration');
        $success = false;

        if ($request->has('profile_id')) {
            $profileId = $request->input('profile_id');

            // Vérifier si le modérateur a l'autorisation d'étendre ce verrou
            $lockInfo = $this->lockService->getLockInfo($profileId, 'profile');

            if ($lockInfo && $lockInfo['moderator_id'] == $moderator->id) {
                // Déverrouiller puis reverrouiller avec une nouvelle durée
                $this->lockService->unlockProfile($profileId);
                $success = $this->lockService->lockProfile($profileId, $moderator->id, $duration);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vous n\'avez pas l\'autorisation d\'étendre ce verrou'
                ], 403);
            }
        }

        if ($request->has('client_id')) {
            $clientId = $request->input('client_id');
            $profileId = $request->input('profile_id');

            // Vérifier si le modérateur a l'autorisation d'étendre ce verrou
            $lockInfo = $this->lockService->getLockInfo($clientId, 'client');

            if ($lockInfo && $lockInfo['moderator_id'] == $moderator->id) {
                // Déverrouiller puis reverrouiller avec une nouvelle durée
                $this->lockService->unlockClient($clientId, $profileId);
                $success = $this->lockService->lockClient($clientId, $profileId, $moderator->id, $duration);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vous n\'avez pas l\'autorisation d\'étendre ce verrou'
                ], 403);
            }
        }

        if ($success) {
            return response()->json([
                'status' => 'success',
                'message' => 'Durée du verrou étendue avec succès'
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de l\'extension du verrou'
            ], 500);
        }
    }
}
