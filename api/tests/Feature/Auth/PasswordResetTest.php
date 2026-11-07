<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    config(['app.frontend_url' => 'https://kosten.codekorb.de']);
    $this->user = User::factory()->create(['email' => 'chris@example.com']);
});

it('sends a reset link pointing to the web app', function () {
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'chris@example.com'])->assertOk();

    Notification::assertSentTo($this->user, ResetPassword::class, function (ResetPassword $n) {
        $url = $n->toMail($this->user)->actionUrl;

        return str_starts_with($url, 'https://kosten.codekorb.de/reset-password?token=')
            && str_contains($url, 'email=chris%40example.com');
    });
});

it('answers the same for unknown addresses', function () {
    $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'chris@example.com'])->json();
    $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk()->json();

    expect($unknown)->toBe($known);
});

it('resets the password and revokes all tokens', function () {
    $this->user->createToken('Phone');
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'chris@example.com']);

    $token = null;
    Notification::assertSentTo($this->user, ResetPassword::class, function (ResetPassword $n) use (&$token) {
        $token = $n->token;

        return true;
    });

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => 'chris@example.com',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertOk();

    expect($this->user->tokens()->count())->toBe(0);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'chris@example.com', 'password' => 'brand-new-password', 'device_name' => 'x',
    ])->assertOk();
});

it('rejects an invalid reset token', function () {
    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'invalid',
        'email' => 'chris@example.com',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});
