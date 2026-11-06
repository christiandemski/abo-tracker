<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config(['app.frontend_url' => 'https://abo.codekorb.de']);
});

function verificationUrl(User $user, ?string $email = null): string
{
    return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($email ?? $user->email),
    ]);
}

it('verifies via the signed link and redirects to the web app', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verificationUrl($user))
        ->assertRedirect('https://abo.codekorb.de/login?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects tampered links', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verificationUrl($user).'x')->assertForbidden();
    $this->get(verificationUrl($user, 'other@example.com'))->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification mail on request', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->withToken($user->createToken('x')->plainTextToken)
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertAccepted();

    Notification::assertSentTo($user, VerifyEmail::class);
});
