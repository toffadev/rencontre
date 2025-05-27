<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ModeratorController extends Controller
{
    /**
     * Get all moderators
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $moderators = User::where('type', 'moderateur')
            ->select('id', 'name', 'email')
            ->get();

        return response()->json($moderators);
    }
}
