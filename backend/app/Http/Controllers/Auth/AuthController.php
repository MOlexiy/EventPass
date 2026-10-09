<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Cookie-based SPA authentication with Sanctum: the SPA first calls
 * /sanctum/csrf-cookie, then these endpoints work on the regular web session.
 */
class AuthController extends Controller
{
    public function register(RegisterRequest $request): UserResource
    {
        $user = User::create([
            ...$request->safe()->only(['name', 'email', 'password']),
            'role' => $request->input('role', Role::Customer->value),
        ]);

        event(new Registered($user)); // queues the verification email

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return new UserResource($user);
    }

    public function login(LoginRequest $request): UserResource
    {
        $request->authenticate();
        $request->session()->regenerate();

        return new UserResource($request->user());
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
