<?php

use App\Http\Controllers\Api\EmergencyController;
use App\Http\Controllers\Api\ResponderController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

Route::post('/register', [EmergencyController::class, 'register']);
Route::post('/login', [EmergencyController::class, 'login']);

Route::post('/emergency/trigger', [EmergencyController::class, 'trigger']);


Broadcast::routes();

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/emergencies/active', [EmergencyController::class, 'active']);
    Route::get('/user/verify-kyc', [EmergencyController::class, 'verifyKyc']);
    Route::post('/alerts/{id}/dispatch', [EmergencyController::class, 'dispatchAlert']);
    Route::get('/responders/live', [ResponderController::class, 'getLiveResponders']);
    Route::get('/my-emergencies', [EmergencyController::class, 'getMyRequests']);
    Route::delete('/emergency/{id}/cancel', [EmergencyController::class, 'cancelEmergency']);
});


