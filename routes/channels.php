<?php
// routes/channels.php

use Illuminate\Support\Facades\Broadcast;
use App\Models\EmergencyAlert;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('emergency.{alertId}', function ($user, $alertId) {
    try {
        $alert = EmergencyAlert::find($alertId);

        if (!$alert) {
            return false;
        }

        // Check if user is the Victim OR the assigned Responder
        $isOwner = (int) $user->id === (int) $alert->user_id;
        $isAssignedResponder = (int) $user->id === (int) $alert->responder_id;

        Log::info("Auth Check - User: {$user->id}, Alert: {$alertId}, Owner: {$alert->user_id}, Responder: {$alert->responder_id}, Allowed: " . ($isOwner || $isAssignedResponder ? 'YES' : 'NO'));

        if ($isOwner || $isAssignedResponder) {
            return ['id' => $user->id, 'role' => $user->role];
        }

        return false;

    } catch (\Exception $e) {
        return false;
    }
});

Broadcast::channel('responder.alerts', function ($user) {
    $isResponder = $user->role === 'responder';

    Log::info("Responder channel auth - User: {$user->id}, Role: {$user->role}, Allowed: " . ($isResponder ? 'YES' : 'NO'));

    return $isResponder ? ['id' => $user->id, 'name' => "{$user->given_name} {$user->family_name}"] : false;
});

Broadcast::channel('responder.{responderId}', function ($user, $responderId) {
    return (int) $user->id === (int) $responderId && $user->role === 'responder';
});
