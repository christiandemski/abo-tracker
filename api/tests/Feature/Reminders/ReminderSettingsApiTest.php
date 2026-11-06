<?php

use App\Models\PushToken;
use App\Models\ReminderSetting;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

describe('reminder settings', function () {
    it('returns defaults on first access', function () {
        $this->getJson('/api/v1/settings/reminders')
            ->assertOk()
            ->assertExactJson(['data' => [
                'offsets_days' => [14, 3],
                'available_offsets' => [30, 14, 7, 3, 1],
                'push' => true,
                'email' => true,
                'charge_reminders' => false,
                'push_devices' => 0,
            ]]);
    });

    it('updates the settings', function () {
        $this->putJson('/api/v1/settings/reminders', [
            'offsets_days' => [3, 30], 'push' => false, 'email' => true, 'charge_reminders' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.offsets_days', [30, 3])
            ->assertJsonPath('data.push', false)
            ->assertJsonPath('data.charge_reminders', true);
    });

    it('only accepts the offered offsets and at least one', function () {
        $this->putJson('/api/v1/settings/reminders', ['offsets_days' => [], 'push' => true, 'email' => true, 'charge_reminders' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('offsets_days');
        $this->putJson('/api/v1/settings/reminders', ['offsets_days' => [5], 'push' => true, 'email' => true, 'charge_reminders' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('offsets_days.0');
        $this->putJson('/api/v1/settings/reminders', ['offsets_days' => [3, 3], 'push' => true, 'email' => true, 'charge_reminders' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('offsets_days.0');
    });

    it('keeps settings per user', function () {
        $this->putJson('/api/v1/settings/reminders', ['offsets_days' => [1], 'push' => false, 'email' => false, 'charge_reminders' => true])->assertOk();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/settings/reminders')->assertJsonPath('data.offsets_days', [14, 3])->assertJsonPath('data.push', true);
        expect(ReminderSetting::withoutGlobalScopes()->count())->toBe(2);
    });
});

describe('push tokens', function () {
    it('registers a device and updates it on repeat', function () {
        $this->postJson('/api/v1/push-tokens', ['token' => 'ExponentPushToken[abc123]', 'device_name' => 'Pixel 8'])->assertCreated();
        $this->postJson('/api/v1/push-tokens', ['token' => 'ExponentPushToken[abc123]'])->assertOk();

        expect(PushToken::count())->toBe(1)
            ->and(PushToken::sole()->device_name)->toBe('Pixel 8');
        $this->getJson('/api/v1/settings/reminders')->assertJsonPath('data.push_devices', 1);
    });

    it('rejects invalid tokens', function () {
        $this->postJson('/api/v1/push-tokens', ['token' => 'not-a-token'])->assertUnprocessable()->assertJsonValidationErrors('token');
    });

    it('moves a token to the account that registers it last (same phone, other login)', function () {
        $this->postJson('/api/v1/push-tokens', ['token' => 'ExponentPushToken[shared]'])->assertCreated();
        $bob = User::factory()->create();
        Sanctum::actingAs($bob);

        $this->postJson('/api/v1/push-tokens', ['token' => 'ExponentPushToken[shared]'])->assertOk();

        expect(PushToken::withoutGlobalScopes()->sole()->user_id)->toBe($bob->id);
    });

    it('unregisters only own devices', function () {
        $this->postJson('/api/v1/push-tokens', ['token' => 'ExponentPushToken[alice]'])->assertCreated();
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/v1/push-tokens/'.rawurlencode('ExponentPushToken[alice]'))->assertNoContent();
        expect(PushToken::withoutGlobalScopes()->count())->toBe(1);

        Sanctum::actingAs($this->user);
        $this->deleteJson('/api/v1/push-tokens/'.rawurlencode('ExponentPushToken[alice]'))->assertNoContent();
        expect(PushToken::withoutGlobalScopes()->count())->toBe(0);
    });
});
