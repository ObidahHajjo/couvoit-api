<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CarController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\TripController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return json_encode(['message' => 'ok']);
});

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/forgot-password', [AuthController::class, 'forgetPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (x-auth-token)
|--------------------------------------------------------------------------
*/

Route::middleware('jwt')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });
    /* ===== USERS / PERSONS ===== */
    Route::get('/persons', [PersonController::class, 'index']);
    Route::get('/persons/{person}', [PersonController::class, 'show']);
    Route::get('/persons/{person}/trips-driver', [PersonController::class, 'tripsDriver']);
    Route::get('/persons/{person}/trips-passenger', [PersonController::class, 'tripsPassenger']);

    Route::post('/persons', [PersonController::class, 'store']);
    Route::patch('/persons/role', [PersonController::class, 'updateRole']);
    Route::patch('/persons/{person}', [PersonController::class, 'update']);
    Route::delete('/persons/{person}', [PersonController::class, 'destroy']);

    /* ===== TRIPS ===== */
    Route::get('/trips', [TripController::class, 'index']);
    Route::get('/trips/{trip}', [TripController::class, 'show']);
    Route::get('/trips/{trip}/person', [TripController::class, 'passengers']);
    Route::post('/trips/{trip}/contact-driver', [ChatController::class, 'contactDriver']);
    Route::post('/trips', [TripController::class, 'store']);
    Route::patch('/trips/{trip}', [TripController::class, 'update']);
    Route::delete('/trips/{trip}', [TripController::class, 'destroy']);
    Route::post('/trips/{trip}/person', [TripController::class, 'reserve']);
    Route::patch('/trips/{trip}/cancel', [TripController::class, 'cancel']);
    Route::delete('/trips/{trip}/reservations', [TripController::class, 'cancelReservation']);
    Route::get('/conversations', [ChatController::class, 'index']);
    Route::get('/conversations/{conversation}', [ChatController::class, 'show']);
    Route::post('/conversations/{conversation}/messages', [ChatController::class, 'send']);
    Route::post('/conversations/{conversation}/messages/clear', [ChatController::class, 'clearMessages']);
    Route::post('/conversations/{conversation}/messages/{message}/clear', [ChatController::class, 'clearMessage']);
    Route::post('/conversations/{conversation}/clear', [ChatController::class, 'clear']);
    Route::get('/chat/conversations', [ChatController::class, 'index']);
    Route::get('/chat/conversations/{conversation}', [ChatController::class, 'show']);
    Route::post('/chat/conversations/{conversation}/messages', [ChatController::class, 'send']);
    Route::post('/chat/conversations/{conversation}/messages/clear', [ChatController::class, 'clearMessages']);
    Route::post('/chat/conversations/{conversation}/messages/{message}/clear', [ChatController::class, 'clearMessage']);
    Route::post('/chat/conversations/{conversation}/clear', [ChatController::class, 'clear']);
    Route::post('/my-trips/{trip}/contact-passenger/{person}', [ChatController::class, 'contactPassenger']);
    Route::post('/broadcasting/auth-proxy', [ChatController::class, 'proxy']);

    /* ===== BRANDS ===== */
    Route::get('/brands', [BrandController::class, 'index']);
    Route::get('/brand/{brand}', [BrandController::class, 'show']);

    /* ===== CARS ===== */
    Route::get('/cars/search', [CarController::class, 'search']);
    Route::get('/cars', [CarController::class, 'index']);
    Route::get('/cars/{car}', [CarController::class, 'show']);
    Route::post('/cars', [CarController::class, 'store']);
    Route::put('/cars/{car}', [CarController::class, 'update']);
    Route::delete('/cars/{car}', [CarController::class, 'destroy']);
});
