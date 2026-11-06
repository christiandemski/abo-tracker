<?php

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
});

function registerPayload(string $token, array $overrides = []): array
{
    return array_merge([
        'token' => $token,
        'name' => 'Chris',
        'email' => 'chris@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        'device_name' => 'Pixel 8',
    ], $overrides);
}

it('registers with a valid invitation and returns a token', function () {
    [$invitation, $token] = Invitation::issue('chris@example.com', isAdmin: true);

    $response = $this->postJson('/api/v1/auth/register', registerPayload($token))
        ->assertCreated()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'email_verified', 'is_admin']])
        ->assertJsonPath('user.email_verified', true)
        ->assertJsonPath('user.is_admin', true);

    $user = User::sole();
    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->tokens()->first()->name)->toBe('Pixel 8')
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();

    $this->withToken($response->json('token'))->getJson('/api/v1/me')
        ->assertOk()->assertJsonPath('data.email', 'chris@example.com');

    Notification::assertNothingSent();
});

it('requires verification when registering with a different address', function () {
    [, $token] = Invitation::issue('chris@example.com');

    $this->postJson('/api/v1/auth/register', registerPayload($token, ['email' => 'other@example.com']))
        ->assertCreated()
        ->assertJsonPath('user.email_verified', false)
        ->assertJsonPath('user.is_admin', false);

    Notification::assertSentTo(User::sole(), VerifyEmail::class);
});

it('rejects invalid, expired and used invitations', function () {
    $this->postJson('/api/v1/auth/register', registerPayload('nope'))
        ->assertUnprocessable()->assertJsonValidationErrors('token');

    [$expired, $expiredToken] = Invitation::issue('chris@example.com');
    $expired->forceFill(['expires_at' => now()->subSecond()])->save();
    $this->postJson('/api/v1/auth/register', registerPayload($expiredToken))
        ->assertUnprocessable()->assertJsonValidationErrors('token');

    [, $token] = Invitation::issue('used@example.com');
    $this->postJson('/api/v1/auth/register', registerPayload($token, ['email' => 'used@example.com']))->assertCreated();
    $this->postJson('/api/v1/auth/register', registerPayload($token, ['email' => 'second@example.com']))
        ->assertUnprocessable()->assertJsonValidationErrors('token');

    expect(User::count())->toBe(1);
});

it('enforces a password of at least 12 characters', function () {
    [, $token] = Invitation::issue('chris@example.com');

    $this->postJson('/api/v1/auth/register', registerPayload($token, [
        'password' => 'short-pass1', 'password_confirmation' => 'short-pass1',
    ]))->assertUnprocessable()->assertJsonValidationErrors('password');
});

it('rejects an address that already has an account', function () {
    User::factory()->create(['email' => 'chris@example.com']);
    [, $token] = Invitation::issue('chris@example.com');

    $this->postJson('/api/v1/auth/register', registerPayload($token, ['email' => 'Chris@Example.com']))
        ->assertUnprocessable()->assertJsonValidationErrors('email');
});
