<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Organizer\EventController as OrganizerEventController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

// --- Auth ----------------------------------------------------------------
Route::prefix('auth')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:5,1');
        Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('user', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])->middleware('throttle:6,1');
    });

    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
});

// --- Public catalogue ----------------------------------------------------
Route::get('events', [EventController::class, 'index']);
Route::get('events/cities', [EventController::class, 'cities']);
Route::get('events/{event}', [EventController::class, 'show']);
Route::get('payments/providers', [PaymentController::class, 'providers']);

// Payment provider callbacks: no session, no CSRF; trust comes from signatures.
Route::post('webhooks/{provider}', [PaymentController::class, 'webhook'])
    ->whereIn('provider', ['liqpay', 'stripe', 'fake'])
    ->middleware('throttle:120,1');

// --- Buyer ---------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);

    Route::middleware('verified')->group(function () {
        Route::post('orders', [OrderController::class, 'store'])->middleware('throttle:20,1');
        Route::post('orders/{order}/checkout', [OrderController::class, 'checkout']);
        Route::post('payments/fake/{order}', [PaymentController::class, 'fakeComplete']);
    });

    // --- Organizer -------------------------------------------------------
    Route::middleware(['verified', 'organizer'])->prefix('organizer')->group(function () {
        Route::get('events', [OrganizerEventController::class, 'index']);
        Route::post('events', [OrganizerEventController::class, 'store']);
        Route::get('events/{event}', [OrganizerEventController::class, 'show']);
        Route::put('events/{event}', [OrganizerEventController::class, 'update']);
        Route::delete('events/{event}', [OrganizerEventController::class, 'destroy']);
        Route::post('events/{event}/publish', [OrganizerEventController::class, 'publish']);
        Route::post('events/{event}/cancel', [OrganizerEventController::class, 'cancel']);
        Route::get('events/{event}/stats', [OrganizerEventController::class, 'stats']);
        Route::get('events/{event}/orders', [OrganizerEventController::class, 'orders']);
        Route::post('events/{event}/check-in', [CheckInController::class, 'store'])->middleware('throttle:240,1');
        Route::post('orders/{order}/refund', [OrderController::class, 'refund']);
    });
});
