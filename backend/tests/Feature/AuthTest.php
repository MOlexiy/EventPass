<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('registers a customer and sends a verification email', function () {
    Notification::fake();

    $this->fromSpa()->postJson('/api/auth/register', [
        'name' => 'Olexii',
        'email' => 'olexii@example.com',
        'password' => 'secret-pass-123',
        'password_confirmation' => 'secret-pass-123',
    ])->assertCreated()
        ->assertJsonPath('data.role', 'customer')
        ->assertJsonPath('data.email_verified', false);

    $user = User::firstWhere('email', 'olexii@example.com');
    Notification::assertSentTo($user, VerifyEmailNotification::class);
    $this->assertAuthenticatedAs($user);
});

it('does not let anyone self-register as admin', function () {
    $this->fromSpa()->postJson('/api/auth/register', [
        'name' => 'Mallory',
        'email' => 'mallory@example.com',
        'password' => 'secret-pass-123',
        'password_confirmation' => 'secret-pass-123',
        'role' => 'admin',
    ])->assertUnprocessable()->assertJsonValidationErrors('role');
});

it('logs in with a session and returns the current user', function () {
    $user = User::factory()->create();

    $this->fromSpa()->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.email', $user->email);

    $this->fromSpa()->getJson('/api/auth/user')->assertOk()->assertJsonPath('data.id', $user->id);
});

it('rejects wrong credentials and throttles brute force', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $_) {
        $this->fromSpa()->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'nope'])
            ->assertUnprocessable();
    }

    $this->fromSpa()->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
    $this->assertGuest();
});

it('returns 401 for guests on protected endpoints', function () {
    $this->getJson('/api/auth/user')->assertUnauthorized();
    $this->getJson('/api/orders')->assertUnauthorized();
});

it('verifies email through the signed link and redirects to the SPA', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->get($url)->assertRedirect(config('payments.frontend_url').'/?verified=1');
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects a tampered verification link', function () {
    $user = User::factory()->unverified()->create();

    $this->get("/api/auth/email/verify/{$user->id}/".sha1($user->email))->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resets a forgotten password', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->fromSpa()->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();

    $token = null;
    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($n) use (&$token) {
        $token = $n->token;

        return true;
    });

    $this->fromSpa()->postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'brand-new-pass-1',
        'password_confirmation' => 'brand-new-pass-1',
    ])->assertOk();

    $this->fromSpa()->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'brand-new-pass-1'])->assertOk();
});
