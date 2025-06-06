<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ProfileSetupController extends Controller
{
    /**
     * Show the profile setup form.
     */
    public function show()
    {
        $user = Auth::user();
        $profile = $user->clientProfile;

        return Inertia::render('ProfileSetup', [
            'profile' => $profile,
            'user' => $user
        ]);
    }

    /**
     * Handle the profile setup submission.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        try {
            // Validation des champs
            $validated = $request->validate([
                'birth_date' => 'required|date|before:-18 years',
                'city' => 'required|string|max:100',
                'sexual_orientation' => 'required|in:heterosexual,homosexual',
                'seeking_gender' => 'required|in:male,female',
                'bio' => 'required|string|min:10|max:1000',
                'profile_photo' => 'nullable|image|max:1024|mimes:jpeg,png,jpg,gif',
            ], [
                'birth_date.required' => 'La date de naissance est obligatoire',
                'birth_date.before' => 'Vous devez avoir au moins 18 ans',
                'city.required' => 'La ville est obligatoire',
                'sexual_orientation.required' => 'L\'orientation sexuelle est obligatoire',
                'seeking_gender.required' => 'Veuillez indiquer qui vous recherchez',
                'bio.required' => 'Une description est obligatoire',
                'bio.min' => 'La description doit faire au moins 10 caractères',
                'profile_photo.image' => 'Le fichier doit être une image',
                'profile_photo.max' => 'L\'image ne doit pas dépasser 1Mo',
                'profile_photo.mimes' => 'L\'image doit être au format : jpeg, png, jpg ou gif',
            ]);

            // Gestion de la photo de profil
            if ($request->hasFile('profile_photo')) {
                // Supprimer l'ancienne photo si elle existe
                if ($user->clientProfile && $user->clientProfile->profile_photo_path) {
                    Storage::disk('public')->delete($user->clientProfile->profile_photo_path);
                }

                $path = $request->file('profile_photo')->store('profile-photos', 'public');
                $validated['profile_photo_path'] = $path;
            }

            // Création ou mise à jour du profil
            $profile = ClientProfile::updateOrCreate(
                ['user_id' => $user->id],
                array_merge($validated, [
                    'profile_completed' => true,
                    'country' => 'France', // Valeur par défaut
                ])
            );

            return redirect()->route('client.home')
                ->with('success', 'Profil configuré avec succès !');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Erreur lors de la configuration du profil : ' . $e->getMessage());
            return back()->withErrors([
                'error' => 'Une erreur est survenue lors de la configuration du profil : ' . $e->getMessage()
            ])->withInput();
        }
    }
}
