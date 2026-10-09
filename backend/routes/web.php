<?php

use App\Http\Controllers\Auth\GoogleController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['name' => 'EventPass API', 'docs' => '/api']));

// OAuth needs the session for its "state" parameter, hence the web group.
Route::get('auth/google/redirect', [GoogleController::class, 'redirect']);
Route::get('auth/google/callback', [GoogleController::class, 'callback']);
