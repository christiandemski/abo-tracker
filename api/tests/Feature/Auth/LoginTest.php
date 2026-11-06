<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'chris@example.com',
        'password' => 'correct-horse-battery',
    ]);
});

it('logs in and issues a token per device', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'Chris@Example.com',
        'password' => 'correct-horse-battery',
        'device_name' => 'Browser',
    ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'email']]);

    expect($this->user->tokens()->pluck('name')->all())->toBe(['Browser']);
});

it('rejects wrong credentials with a 422', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'chris@example.com',
        'password' => 'wrong-password',
        'device_name' => 'Browser',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('limits login attempts to 5 per minute', function () {
    foreach (range(1, 5) as $_) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'chris@example.com', 'password' => 'wrong', 'device_name' => 'x',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => 'chris@example.com', 'password' => 'correct-horse-battery', 'device_name' => 'x',
    ])->assertTooManyRequests();
});

it('logs out by revoking only the current token', function () {
    $current = $this->user->createToken('Phone')->plainTextToken;
    $this->user->createToken('Browser');

    $this->withToken($current)->postJson('/api/v1/auth/logout')->assertNoContent();

    expect($this->user->tokens()->pluck('name')->all())->toBe(['Browser']);
});

it('returns 401 JSON for /me without a token', function () {
    $this->get('/api/v1/me')->assertUnauthorized()->assertJsonStructure(['message']);
});

it('blocks verified-only endpoints for unverified users', function () {
    $unverified = User::factory()->unverified()->admin()->create();

    $this->withToken($unverified->createToken('x')->plainTextToken)
        ->postJson('/api/v1/invitations', ['email' => 'friend@example.com'])
        ->assertForbidden();
});
