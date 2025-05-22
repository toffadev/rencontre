<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    public function index()
    {
        $profiles = Profile::with(['photos', 'mainPhoto'])->get();

        return Inertia::render('Profile', [
            'profiles' => $profiles
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'gender' => 'required|string|in:male,female,other',
            'bio' => 'nullable|string',
            'status' => 'required|string|in:active,inactive',
            'photos' => 'required|array|min:1',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:8048'
        ]);

        try {
            // Create the profile
            $profile = Profile::create([
                'name' => $validated['name'],
                'gender' => $validated['gender'],
                'bio' => $validated['bio'],
                'status' => $validated['status'],
            ]);

            // Handle photos
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $index => $photo) {
                    $photoPath = $photo->store('profiles', 'public');
                    $storedPath = Storage::url($photoPath);

                    $profile->photos()->create([
                        'path' => $storedPath,
                        'order' => $index
                    ]);

                    // Set first photo as main photo
                    if ($index === 0) {
                        $profile->update(['main_photo_path' => $storedPath]);
                    }
                }
            }

            return redirect()->back()->with('success', 'Profil créé avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création du profil:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withErrors(['error' => 'Une erreur est survenue lors de la création du profil: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function update(Request $request, Profile $profile)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'gender' => 'required|string|in:male,female,other',
            'bio' => 'nullable|string',
            'status' => 'required|string|in:active,inactive',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:8048',
            'main_photo_id' => 'nullable|integer|exists:profile_photos,id'
        ]);

        try {
            $profile->update([
                'name' => $validated['name'],
                'gender' => $validated['gender'],
                'bio' => $validated['bio'],
                'status' => $validated['status'],
            ]);

            // Handle new photos
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $index => $photo) {
                    $photoPath = $photo->store('profiles', 'public');
                    $storedPath = Storage::url($photoPath);

                    $profile->photos()->create([
                        'path' => $storedPath,
                        'order' => $profile->photos()->count() + $index
                    ]);
                }
            }

            // Update main photo if specified
            if ($request->has('main_photo_id')) {
                $mainPhoto = $profile->photos()->find($request->main_photo_id);
                if ($mainPhoto) {
                    $profile->update(['main_photo_path' => $mainPhoto->path]);
                }
            }

            return redirect()->back()->with('success', 'Profil mis à jour avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour du profil:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withErrors(['error' => 'Une erreur est survenue lors de la mise à jour du profil: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(Profile $profile)
    {
        try {
            // Delete the photos from storage
            foreach ($profile->photos as $photo) {
                $imagePath = str_replace('/storage/', '', $photo->path);
                Storage::disk('public')->delete($imagePath);
            }

            $profile->delete();
            return redirect()->back()->with('success', 'Profil supprimé avec succès');
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression du profil:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withErrors(['error' => 'Une erreur est survenue lors de la suppression du profil: ' . $e->getMessage()]);
        }
    }

    public function setMainPhoto(Request $request, Profile $profile)
    {
        $validated = $request->validate([
            'photo_id' => 'required|exists:profile_photos,id'
        ]);

        try {
            $photo = $profile->photos()->findOrFail($validated['photo_id']);
            $profile->update(['main_photo_path' => $photo->path]);

            return redirect()->back()->with('success', 'Photo principale mise à jour');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Impossible de définir la photo principale']);
        }
    }

    public function deletePhoto(Request $request)
    {
        $validated = $request->validate([
            'photo_id' => 'required|exists:profile_photos,id'
        ]);

        try {
            $photo = \App\Models\ProfilePhoto::findOrFail($validated['photo_id']);
            $profile = $photo->profile;

            // Check if this is the main photo
            if ($profile->main_photo_path === $photo->path) {
                // Get another photo to be the main one or set to null
                $newMainPhoto = $profile->photos()->where('id', '!=', $photo->id)->first();
                $profile->update([
                    'main_photo_path' => $newMainPhoto ? $newMainPhoto->path : null
                ]);
            }

            // Delete the photo from storage
            $imagePath = str_replace('/storage/', '', $photo->path);
            Storage::disk('public')->delete($imagePath);

            $photo->delete();

            return redirect()->back()->with('success', 'Photo supprimée avec succès');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Impossible de supprimer la photo']);
        }
    }
}
