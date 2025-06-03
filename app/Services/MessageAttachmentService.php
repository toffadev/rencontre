<?php

namespace App\Services;

use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MessageAttachmentService
{
    public function storeAttachment(Message $message, UploadedFile $file): MessageAttachment
    {
        // Générer un nom de fichier unique
        $fileName = uniqid() . '_' . $file->getClientOriginalName();

        // Stocker le fichier dans le dossier 'message-attachments'
        $path = $file->storeAs('message-attachments', $fileName, 'public');

        // Créer l'enregistrement de la pièce jointe
        return MessageAttachment::create([
            'message_id' => $message->id,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);
    }

    public function deleteAttachment(MessageAttachment $attachment): bool
    {
        // Supprimer le fichier physique
        Storage::disk('public')->delete($attachment->file_path);

        // Supprimer l'enregistrement
        return $attachment->delete();
    }
}
