<?php

use App\Http\Controllers\PersonController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CarController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/refresh', [AuthController::class, 'refresh']);

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (x-auth-token)
|--------------------------------------------------------------------------
*/

Route::middleware('supabase.auth')->group(function () {

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
    Route::get("trips/{trip}/person", [TripController::class, 'passengers']);
    Route::post('/trips', [TripController::class, 'store']);
    Route::patch("/trips/{trip}", [TripController::class, 'update']);
    Route::delete('/trips/{trip}', [TripController::class, 'destroy']);
    Route::post('/trips/{trip}/person', [TripController::class, 'reserve']);
    Route::patch('/trips/{trip}/cancel', [TripController::class, 'cancel']);
    Route::delete('/trips/{trip}/reservations', [TripController::class, 'cancelReservation']);


    /* ===== BRANDS ===== */
    Route::get('/brands', [BrandController::class, 'index']);
    Route::get('/brand/{brand}', [BrandController::class, 'show']);

    /* ===== CARS ===== */
    Route::get('/cars', [CarController::class, 'index']);
    Route::get('/cars/{car}', [CarController::class, 'show']);
    Route::post('/cars', [CarController::class, 'store']);
    Route::put('/cars/{car}', [CarController::class, 'update']);
    Route::delete('/cars/{car}', [CarController::class, 'destroy']);

});
