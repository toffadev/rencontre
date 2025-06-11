<?php

namespace App\Http\Controllers\Moderator;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\Profile;
use App\Models\ProfilePhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProfilePhotoController extends Controller
{
    /**
     * Normalise un chemin de fichier pour éviter les problèmes de double slash
     * 
     * @param string $path
     * @return string
     */
    private function normalizeFilePath($path)
    {
        // Si le chemin commence par /storage/, le convertir en storage/
        if (strpos($path, '/storage/') === 0) {
            return 'storage' . substr($path, 8);
        }

        // Si le chemin commence par storage/ (sans slash), le laisser tel quel
        if (strpos($path, 'storage/') === 0) {
            return $path;
        }

        // Sinon, ajouter storage/ au début si nécessaire
        if (!str_starts_with($path, 'storage/') && !str_starts_with($path, '/storage/')) {
            return 'storage/' . $path;
        }

        return $path;
    }
    /**
     * Récupère toutes les photos d'un profil avec indication de celles déjà envoyées à un client spécifique
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProfilePhotos(Request $request)
    {
        $request->validate([
            'profile_id' => 'required|exists:profiles,id',
            'client_id' => 'required|exists:users,id',
        ]);

        $profileId = $request->profile_id;
        $clientId = $request->client_id;

        // Vérifier que le modérateur a accès à ce profil
        $hasAccess = DB::table('moderator_profile_assignments')
            ->where('user_id', Auth::id())
            ->where('profile_id', $profileId)
            ->where('is_active', true)
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas accès à ce profil'
            ], 403);
        }

        // Récupérer toutes les photos du profil
        $profilePhotos = ProfilePhoto::where('profile_id', $profileId)
            ->orderBy('order')
            ->get();

        // Récupérer les IDs des photos déjà envoyées à ce client
        $sentPhotoIds = $this->getSentPhotoIds($profileId, $clientId);

        // Formater les données pour le frontend
        $photos = $profilePhotos->map(function ($photo) use ($sentPhotoIds) {
            // Normaliser le chemin
            $normalizedPath = $this->normalizeFilePath($photo->path);

            return [
                'id' => $photo->id,
                'path' => $normalizedPath,
                'url' => asset($normalizedPath), // Générer une URL complète avec asset()
                'order' => $photo->order,
                'already_sent' => in_array($photo->id, $sentPhotoIds),
            ];
        });

        return response()->json([
            'success' => true,
            'photos' => $photos
        ]);
    }

    /**
     * Envoie une photo de profil à un client
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendProfilePhoto(Request $request)
    {
        $request->validate([
            'profile_id' => 'required|exists:profiles,id',
            'client_id' => 'required|exists:users,id',
            'photo_id' => 'required|exists:profile_photos,id',
        ]);

        $profileId = $request->profile_id;
        $clientId = $request->client_id;
        $photoId = $request->photo_id;
        $moderatorId = Auth::id();

        // Vérifier que le modérateur a accès à ce profil
        $hasAccess = DB::table('moderator_profile_assignments')
            ->where('user_id', $moderatorId)
            ->where('profile_id', $profileId)
            ->where('is_active', true)
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas accès à ce profil'
            ], 403);
        }

        // Vérifier que la photo appartient bien au profil
        $photo = ProfilePhoto::where('id', $photoId)
            ->where('profile_id', $profileId)
            ->first();

        if (!$photo) {
            return response()->json([
                'success' => false,
                'message' => 'Cette photo n\'appartient pas à ce profil'
            ], 404);
        }

        // Vérifier que la photo n'a pas déjà été envoyée à ce client
        $sentPhotoIds = $this->getSentPhotoIds($profileId, $clientId);
        if (in_array($photoId, $sentPhotoIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Cette photo a déjà été envoyée à ce client'
            ], 400);
        }

        try {
            // Créer un nouveau message
            $message = Message::create([
                'client_id' => $clientId,
                'profile_id' => $profileId,
                'moderator_id' => $moderatorId,
                'content' => '',
                'is_from_client' => false,
            ]);

            // Normaliser le chemin
            $normalizedPath = $this->normalizeFilePath($photo->path);

            // Créer une pièce jointe pour ce message
            $attachment = MessageAttachment::create([
                'message_id' => $message->id,
                'file_path' => $normalizedPath,
                'file_name' => basename($normalizedPath),
                'mime_type' => 'image/jpeg', // Par défaut, ajustez selon vos besoins
                'file_size' => 0, // Taille inconnue, mais ce n'est pas critique
            ]);

            // Préparer les données pour la réponse
            $messageData = [
                'id' => $message->id,
                'content' => '',
                'isFromClient' => false,
                'time' => $message->created_at->format('H:i'),
                'date' => $message->created_at->format('Y-m-d'),
                'created_at' => $message->created_at->toISOString(),
                'attachment' => [
                    'id' => $attachment->id,
                    'file_name' => $attachment->file_name,
                    'mime_type' => $attachment->mime_type,
                    'url' => asset($normalizedPath),
                ],
            ];

            // Émettre l'événement en temps réel
            broadcast(new \App\Events\MessageSent($message))->toOthers();

            return response()->json([
                'success' => true,
                'message' => 'Photo envoyée avec succès',
                'messageData' => $messageData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi de la photo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupère les IDs des photos de profil déjà envoyées à un client spécifique
     *
     * @param int $profileId
     * @param int $clientId
     * @return array
     */
    private function getSentPhotoIds($profileId, $clientId)
    {
        // Récupérer tous les messages avec pièces jointes envoyés par ce profil à ce client
        $sentPhotoUrls = Message::where('profile_id', $profileId)
            ->where('client_id', $clientId)
            ->where('is_from_client', false)
            ->whereHas('attachments')
            ->with('attachments')
            ->get()
            ->pluck('attachments')
            ->flatten()
            ->pluck('file_path')
            ->toArray();

        // Normaliser les chemins des photos envoyées
        $normalizedSentUrls = array_map(function ($url) {
            return $this->normalizeFilePath($url);
        }, $sentPhotoUrls);

        // Récupérer toutes les photos du profil
        $profilePhotos = ProfilePhoto::where('profile_id', $profileId)->get();

        // Filtrer manuellement pour gérer les différentes formes de chemins
        $sentPhotoIds = [];
        foreach ($profilePhotos as $photo) {
            $normalizedPhotoPath = $this->normalizeFilePath($photo->path);

            // Vérifier si cette photo a été envoyée
            foreach ($normalizedSentUrls as $sentUrl) {
                if ($sentUrl === $normalizedPhotoPath || basename($sentUrl) === basename($normalizedPhotoPath)) {
                    $sentPhotoIds[] = $photo->id;
                    break;
                }
            }
        }

        return $sentPhotoIds;
    }
}
