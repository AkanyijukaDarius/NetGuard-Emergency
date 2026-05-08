<?php

use App\Http\Controllers\Api\EmergencyController;
use App\Http\Controllers\Api\ResponderController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

// Public Authentication
Route::post('/register', [EmergencyController::class, 'register']);
Route::post('/login', [EmergencyController::class, 'login']);



Broadcast::routes();

Route::middleware('auth:sanctum')->group(function () {
    // Victim Endpoints
    Route::post('/emergency/trigger', [EmergencyController::class, 'trigger']);
    Route::get('/my-emergencies', [EmergencyController::class, 'getMyRequests']);
    Route::post('/emergencies/{alertId}/cancel', [EmergencyController::class, 'cancelEmergency']);


    Route::get('/user/verify-kyc', [EmergencyController::class, 'verifyKyc']);

    // Responder Endpoints
    Route::get('/emergencies/active', [EmergencyController::class, 'active']);
    Route::get('/emergencies/resolved', [EmergencyController::class, 'resolved']);
    Route::post('/emergencies/{alertId}/dispatch', [EmergencyController::class, 'dispatchAlert']);
    Route::get('/responders/live', [ResponderController::class, 'getLiveResponders']);
});
