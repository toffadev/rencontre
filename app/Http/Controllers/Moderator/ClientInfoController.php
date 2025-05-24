<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ClientInfo;
use App\Models\ClientCustomInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientInfoController extends Controller
{
    /**
     * Get all information for a specific client
     */
    public function getClientInfo(User $client)
    {
        $basicInfo = $client->clientInfo;
        $customInfos = $client->customInfos()
            ->with('addedBy:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'basic_info' => $basicInfo,
            'custom_infos' => $customInfos
        ]);
    }

    /**
     * Update or create basic client information
     */
    public function updateBasicInfo(Request $request, User $client)
    {
        $validated = $request->validate([
            'age' => 'nullable|integer|min:18|max:100',
            'ville' => 'nullable|string|max:100',
            'quartier' => 'nullable|string|max:100',
            'orientation' => 'nullable|in:heterosexuel,homosexuel,bisexuel'
        ]);

        $client->clientInfo()->updateOrCreate(
            ['user_id' => $client->id],
            $validated
        );

        return response()->json(['message' => 'Informations mises à jour avec succès']);
    }

    /**
     * Add a new custom information for a client
     */
    public function addCustomInfo(Request $request, User $client)
    {
        $validated = $request->validate([
            'titre' => 'required|string|max:100',
            'contenu' => 'required|string'
        ]);

        $customInfo = $client->customInfos()->create([
            ...$validated,
            'added_by' => Auth::id()
        ]);

        return response()->json([
            'message' => 'Information ajoutée avec succès',
            'info' => $customInfo->load('addedBy:id,name')
        ]);
    }

    /**
     * Delete a custom information
     */
    public function deleteCustomInfo(ClientCustomInfo $customInfo)
    {
        $this->authorize('delete', $customInfo);

        $customInfo->delete();
        return response()->json(['message' => 'Information supprimée avec succès']);
    }
}
