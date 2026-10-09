<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * "Sign in with Google" via Socialite. These routes live in the web group
 * because the OAuth state is kept in the session.
 */
class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $frontend = config('payments.frontend_url');

        try {
            $google = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->away("{$frontend}/login?error=google");
        }

        $user = User::where('google_id', $google->getId())->first()
            ?? User::where('email', $google->getEmail())->first();

        if ($user === null) {
            $user = User::create([
                'name' => $google->getName() ?: $google->getEmail(),
                'email' => $google->getEmail(),
                'role' => Role::Customer,
            ]);
        }

        $user->forceFill([
            'google_id' => $google->getId(),
            'avatar_url' => $google->getAvatar(),
            // Google has already confirmed the address.
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->away($frontend.'/');
    }
}
