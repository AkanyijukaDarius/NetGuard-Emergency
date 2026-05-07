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
        $alert = EmergencyAlert::where('id', $alertId)->first();

        if (!$alert) {
            Log::warning("Channel Auth Failed: Alert {$alertId} not found for user {$user->id}");
            return false;
        }

        $isOwner = (int) $user->id === (int) $alert->user_id;

        Log::info("Broadcasting auth - User: {$user->id} (Role: {$user->role}), Alert: {$alertId}, Owner: {$alert->user_id}, Allowed: " . ($isOwner ? 'YES' : 'NO'));

        return $isOwner ? ['id' => $user->id, 'role' => $user->role] : false;

    } catch (\Exception $e) {
        Log::error("Error in emergency channel auth: " . $e->getMessage());
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
