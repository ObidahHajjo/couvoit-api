<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CarController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\SupportChatController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\Admin\AdminBrandController;
use App\Http\Controllers\Admin\AdminCarController;
use App\Http\Controllers\Admin\AdminCarModelController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminTripController;
use App\Http\Controllers\Admin\AdminTypeController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\TypeController;
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
    
    /* ===== ADMIN ===== */
    Route::group(['prefix' => 'admin', 'middleware' => ['admin']], function () {
        Route::get('/stats', [AdminDashboardController::class, 'stats']);
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::put('/users/{user}', [AdminUserController::class, 'update']);
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
        Route::get('/trips', [AdminTripController::class, 'index']);
        Route::delete('/trips/{trip}', [AdminTripController::class, 'destroy']);

    Route::apiResource('brands', AdminBrandController::class)->except(['create', 'show', 'edit']);
    Route::apiResource('models', AdminCarModelController::class)->except(['create', 'show', 'edit']);
    Route::apiResource('types', AdminTypeController::class)->except(['create', 'show', 'edit']);
    Route::get('/cars', [AdminCarController::class, 'index']);
        Route::delete('/cars/{car}', [AdminCarController::class, 'destroy']);
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
    Route::post('/trips/{trip}/contact-driver-email', [ChatController::class, 'contactDriverByEmail']);
    Route::post('/trips', [TripController::class, 'store']);
    Route::patch('/trips/{trip}', [TripController::class, 'update']);
    Route::delete('/trips/{trip}', [TripController::class, 'destroy']);
    Route::post('/trips/{trip}/person', [TripController::class, 'reserve']);
    Route::patch('/trips/{trip}/cancel', [TripController::class, 'cancel']);
    Route::delete('/trips/{trip}/reservations', [TripController::class, 'cancelReservation']);
    Route::get('/conversations', [ChatController::class, 'index']);
    Route::get('/conversations/{conversation}', [ChatController::class, 'show']);
    Route::post('/conversations/{conversation}/messages', [ChatController::class, 'send']);
    Route::get('/conversations/attachments/{attachment}', [ChatController::class, 'downloadAttachment'])->name('chat.attachments.download');
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
    Route::post('/my-trips/{trip}/contact-passenger/{person}/email', [ChatController::class, 'contactPassengerByEmail']);
    Route::post('/support/contact-email', [SupportController::class, 'sendSupportEmail']);
    Route::post('/broadcasting/auth-proxy', [ChatController::class, 'proxy']);

    /* ===== SUPPORT CHAT ===== */
    Route::prefix('support-chat')->group(function () {
        Route::post('/sessions', [SupportChatController::class, 'createSession']);
        Route::get('/sessions', [SupportChatController::class, 'getSessions']);
        Route::get('/sessions/waiting', [SupportChatController::class, 'getWaitingSessions']);
        Route::get('/sessions/{sessionId}', [SupportChatController::class, 'getSession']);
        Route::post('/sessions/{sessionId}/join', [SupportChatController::class, 'joinSession']);
        Route::post('/sessions/{sessionId}/close', [SupportChatController::class, 'closeSession']);
        Route::get('/sessions/{sessionId}/messages', [SupportChatController::class, 'getMessages']);
        Route::post('/sessions/{sessionId}/messages', [SupportChatController::class, 'sendMessage']);
        Route::post('/sessions/{sessionId}/read', [SupportChatController::class, 'markAsRead']);
        Route::post('/sessions/{sessionId}/typing', [SupportChatController::class, 'setTyping']);
        Route::get('/sessions/{sessionId}/unread', [SupportChatController::class, 'getUnreadCount']);
        Route::get('/sessions/{sessionId}/attachments/{attachmentId}', [SupportChatController::class, 'downloadAttachment'])
            ->name('support.attachment.download');
        Route::post('/presence', [SupportChatController::class, 'setPresence']);
        Route::get('/presence', [SupportChatController::class, 'getPresence']);
    });

    /* ===== BRANDS ===== */
    Route::get('/brands', [BrandController::class, 'index']);
    Route::get('/brand/{brand}', [BrandController::class, 'show']);

    /* ===== TYPES ===== */
    Route::get('/types', [TypeController::class, 'index']);

    /* ===== CARS ===== */
    Route::get('/cars/search', [CarController::class, 'search']);
    Route::get('/cars', [CarController::class, 'index']);
    Route::get('/cars/{car}', [CarController::class, 'show']);
    Route::post('/cars', [CarController::class, 'store']);
    Route::put('/cars/{car}', [CarController::class, 'update']);
    Route::delete('/cars/{car}', [CarController::class, 'destroy']);
});
