<?php

namespace App\Http\Controllers;

use App\Events\TestEvent;
use Illuminate\Http\Request;

class TestEventController extends Controller
{
    public function sendTestEvent(Request $request)
    {
        $message = $request->input('message', 'Test message from controller');

        // Déclencher l'événement
        event(new TestEvent($message));

        // Répondre avec confirmation
        return response()->json([
            'success' => true,
            'message' => 'Événement envoyé avec succès',
            'event_data' => [
                'channel' => 'test-channel',
                'event' => 'TestEvent',
                'message' => $message
            ]
        ]);
    }
}
