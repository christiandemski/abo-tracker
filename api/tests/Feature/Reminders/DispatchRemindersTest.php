<?php

use App\Jobs\SendPushReminders;
use App\Models\PushToken;
use App\Models\ReminderSetting;
use App\Models\SentReminder;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\ReminderDigestNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

function adobeFor(User $user, array $overrides = []): Subscription
{
    return Subscription::factory()->for($user)->create(array_merge([
        'name' => 'Adobe Creative Cloud', 'amount_cents' => 14388, 'interval_unit' => 'year', 'anchor_date' => '2026-11-12',
        'start_date' => '2023-11-12', 'min_term_months' => 12, 'renewal_months' => 12, 'notice_value' => 1, 'notice_unit' => 'day',
    ], $overrides));
}

function addPushToken(User $user, string $token = 'ExponentPushToken[abc123]'): PushToken
{
    $t = new PushToken(['expo_token' => $token, 'device_name' => 'Pixel']);
    $t->user_id = $user->id;
    $t->save();

    return $t;
}

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-07 07:00:00'); // 3 days before Adobe's deadline
    $this->user = User::factory()->create(['name' => 'Chris']);
    $this->adobe = adobeFor($this->user);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

describe('idempotency', function () {
    it('sends each reminder once, even if cron runs twice', function () {
        Notification::fake();
        Queue::fake();
        addPushToken($this->user);

        Artisan::call('reminders:dispatch');
        Artisan::call('reminders:dispatch');

        Notification::assertSentToTimes($this->user, ReminderDigestNotification::class, 1);
        Queue::assertPushed(SendPushReminders::class, 1);
        expect(SentReminder::query()->orderBy('channel')->get(['type', 'offset_days', 'channel'])->toArray())->toBe([
            ['type' => 'deadline', 'offset_days' => 3, 'channel' => 'mail'],
            ['type' => 'deadline', 'offset_days' => 3, 'channel' => 'push'],
        ]);
    });

    it('enforces uniqueness in the database', function () {
        $row = ['user_id' => $this->user->id, 'subscription_id' => $this->adobe->id, 'type' => 'deadline',
            'due_date' => '2026-11-10', 'offset_days' => 3, 'channel' => 'mail', 'created_at' => now()];
        DB::table('sent_reminders')->insert($row);

        expect(fn () => DB::table('sent_reminders')->insert($row))->toThrow(QueryException::class);
    });

    it('catches up a missed run without sending twice later', function () {
        Notification::fake();
        CarbonImmutable::setTestNow('2026-11-09 07:00:00'); // 07. and 08. missed

        Artisan::call('reminders:dispatch');
        CarbonImmutable::setTestNow('2026-11-10 07:00:00');
        Artisan::call('reminders:dispatch');

        Notification::assertSentToTimes($this->user, ReminderDigestNotification::class, 1);
    });
});

describe('mail digest', function () {
    it('bundles all due reminders of a user into one mail with amounts', function () {
        Notification::fake();
        Subscription::factory()->for($this->user)->create([
            'name' => 'Fitnessstudio', 'amount_cents' => 2990, 'anchor_date' => '2026-12-01',
            // 05.12.2024 + 24 months = 05.12.2026, notice 14 days → cancel by 21.11.2026: 14-day reminder today
            'start_date' => '2024-12-05', 'min_term_months' => 24, 'notice_value' => 14, 'notice_unit' => 'day',
        ]);
        ReminderSetting::forUser($this->user)->update(['offsets_days' => [14, 3]]);

        Artisan::call('reminders:dispatch');

        Notification::assertSentTo($this->user, ReminderDigestNotification::class, function (ReminderDigestNotification $n) {
            $mail = $n->toMail($this->user);
            $text = implode("\n", $mail->introLines);

            return count($n->items) === 2
                && $mail->subject === '2 Kündigungsfristen stehen an'
                && str_contains($text, 'Adobe Creative Cloud')
                && str_contains($text, '10.11.2026')
                && str_contains($text, '143,88')
                && str_contains($text, 'Fitnessstudio');
        });
    });

    it('marks log entries as sent after the mail went out', function () {
        Artisan::call('reminders:dispatch');

        expect(SentReminder::query()->where('channel', 'mail')->value('sent_at'))->not->toBeNull();
    });

    it('respects the settings and unverified addresses', function () {
        Notification::fake();
        ReminderSetting::forUser($this->user)->update(['email' => false]);
        $unverified = User::factory()->unverified()->create();
        adobeFor($unverified);

        Artisan::call('reminders:dispatch');

        Notification::assertNothingSent();
    });
});

describe('push', function () {
    it('sends pushes without amounts and links to the subscription', function () {
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok', 'id' => 'x']]])]);
        addPushToken($this->user);
        ReminderSetting::forUser($this->user)->update(['email' => false]);

        Artisan::call('reminders:dispatch');
        Artisan::call('queue:work', ['--stop-when-empty' => true]);

        Http::assertSent(function ($request) {
            $m = $request->data()[0];

            return $m['to'] === 'ExponentPushToken[abc123]'
                && $m['title'] === 'Kündigungsfrist'
                && $m['body'] === 'Frist für Adobe Creative Cloud endet in 3 Tagen.'
                && ! str_contains($m['body'], '€')
                && $m['data']['url'] === '/subscription/'.$this->adobe->id;
        });
        expect(SentReminder::query()->where('channel', 'push')->value('sent_at'))->not->toBeNull();
    });

    it('combines more than three reminders into one summary push', function () {
        Queue::fake();
        addPushToken($this->user);
        foreach (['A', 'B', 'C'] as $i => $name) {
            adobeFor($this->user, ['name' => "Vertrag {$name}"]);
        }

        Artisan::call('reminders:dispatch');

        Queue::assertPushed(SendPushReminders::class, fn (SendPushReminders $job) => count($job->messages) === 1
            && $job->messages[0]['title'] === '4 Kündigungsfristen stehen an'
            && count($job->sentReminderIds) === 4);
    });

    it('forgets devices that are no longer registered', function () {
        Http::fake(['exp.host/*' => Http::response(['data' => [[
            'status' => 'error', 'message' => 'not registered', 'details' => ['error' => 'DeviceNotRegistered'],
        ]]])]);
        addPushToken($this->user);
        ReminderSetting::forUser($this->user)->update(['email' => false]);

        Artisan::call('reminders:dispatch');
        Artisan::call('queue:work', ['--stop-when-empty' => true]);

        expect(PushToken::withoutGlobalScopes()->count())->toBe(0)
            ->and(SentReminder::query()->value('sent_at'))->toBeNull();
    });

    it('skips push without registered devices', function () {
        Queue::fake();

        Artisan::call('reminders:dispatch');

        Queue::assertNotPushed(SendPushReminders::class);
        expect(SentReminder::query()->where('channel', 'push')->exists())->toBeFalse();
    });
});
