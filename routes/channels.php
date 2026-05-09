<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\EmergencyAlert;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/


Broadcast::channel('emergency.{alertId}', function ($user, $alertId) {
    try {
        $alert = EmergencyAlert::find($alertId);

        if (!$alert) {
            Log::error("Broadcast Auth Failed: Emergency Alert #{$alertId} not found.");
            return false;
        }

        $isVictim    = (int) $user->id === (int) $alert->user_id;
        $isResponder = (int) $user->id === (int) $alert->responder_id;
        $isAnyResponder = $user->role === 'responder';

        if ($isVictim || $isResponder || $isAnyResponder) {
            return [
                'id' => $user->id,
                'name' => $user->given_name,
                'role' => $user->role
            ];
        }

        return false;

    } catch (\Exception $e) {
        Log::error("Channel Auth Error: " . $e->getMessage());
        return false;
    }
});


Broadcast::channel('emergency-channel', function ($user) {
    return $user->role === 'responder';
});


Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});


 // General Responder Notifications
 
Broadcast::channel('responder.alerts', function ($user) {
    return $user->role === 'responder';
});
