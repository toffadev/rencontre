<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;

class AdminProfileApiController extends Controller
{
    /**
     * Get all profiles for admin API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): JsonResponse
    {
        $profiles = Profile::select('id', 'name')
            ->get();

        return response()->json($profiles);
    }
}
