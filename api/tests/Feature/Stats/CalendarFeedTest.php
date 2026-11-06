<?php

use App\Models\CalendarFeed;
use App\Models\Scopes\OwnedByCurrentUserScope;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-06 10:00:00');
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);

    Subscription::factory()->for($this->user)->create(['name' => 'Netflix', 'amount_cents' => 1399, 'anchor_date' => '2026-01-15']);
    Subscription::factory()->for($this->user)->yearly(14388)->withTerm('2023-11-12', 12, 12, 2, 'day')
        ->create(['name' => 'Adobe CC', 'anchor_date' => '2026-11-12']);
    Subscription::factory()->for($this->user)->yearly(13900)->withTerm('2024-03-01', 12, 12, 3, 'month')
        ->create(['name' => 'ADAC, Plus', 'anchor_date' => '2027-03-01']);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

/** Creates the link and returns the token from the URL. */
function createFeedToken($test): string
{
    $url = $test->postJson('/api/v1/settings/calendar-feed/rotate')->assertOk()->json('data.url');
    preg_match('#/api/v1/ical/([a-f0-9]{64})\.ics$#', $url, $m);

    return $m[1];
}

it('starts without a link and with privacy-friendly defaults', function () {
    $data = $this->getJson('/api/v1/settings/calendar-feed')->assertOk()->json('data');

    expect($data['active'])->toBeFalse()
        ->and($data['url'])->toBeNull()
        ->and($data['options'])->toBe(['charges' => true, 'deadlines' => true, 'reminders' => false, 'amounts' => false]);
});

it('previews the next events in order, deadlines first', function () {
    $preview = $this->getJson('/api/v1/settings/calendar-feed')->json('data.preview');

    expect(array_column($preview, 'date'))->toBe(['2026-11-10', '2026-11-12', '2026-11-15', '2026-12-01'])
        ->and(array_column($preview, 'title'))->toBe(['Frist: Adobe CC kündigen', 'Adobe CC', 'Netflix', 'Frist: ADAC, Plus kündigen'])
        ->and(array_column($preview, 'type'))->toBe(['deadline', 'charge', 'charge', 'deadline']);
});

it('updates the options and the preview with them', function () {
    $data = $this->putJson('/api/v1/settings/calendar-feed', [
        'options' => ['charges' => true, 'deadlines' => false, 'reminders' => false, 'amounts' => true],
    ])->assertOk()->json('data');

    expect($data['options']['amounts'])->toBeTrue()
        ->and(array_column($data['preview'], 'title'))->toBe(['Adobe CC · 143,88 €', 'Netflix · 13,99 €', 'Netflix · 13,99 €', 'Netflix · 13,99 €'])
        ->and($this->getJson('/api/v1/settings/calendar-feed')->json('data.options.deadlines'))->toBeFalse();

    $this->putJson('/api/v1/settings/calendar-feed', ['options' => ['charges' => 'ja']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['options.charges', 'options.deadlines']);
});

it('stores only the hash of the token and shows the link once', function () {
    $token = createFeedToken($this);
    $row = CalendarFeed::query()->where('user_id', $this->user->id)->first();

    expect($row->token_hash)->toBe(hash('sha256', $token))
        ->and($row->getAttributes())->not->toContain($token)
        ->and($this->getJson('/api/v1/settings/calendar-feed')->json('data'))->toMatchArray(['active' => true, 'url' => null]);
});

it('serves a valid iCalendar feed with the secret token', function () {
    $token = createFeedToken($this);
    auth()->forgetGuards();

    $response = $this->get("/api/v1/ical/{$token}.ics")->assertOk();
    $body = $response->getContent();

    expect($response->headers->get('Content-Type'))->toStartWith('text/calendar')
        ->and($body)->toStartWith("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n")
        ->and($body)->toEndWith("END:VCALENDAR\r\n")
        ->and($body)->toContain("SUMMARY:Frist: Adobe CC kündigen\r\n")
        ->and($body)->toContain("DTSTART;VALUE=DATE:20261110\r\nDTEND;VALUE=DATE:20261111\r\n")
        // commas are escaped
        ->and($body)->toContain('SUMMARY:Frist: ADAC\, Plus kündigen')
        // no amounts by default
        ->and($body)->not->toContain('€')
        ->and(substr_count($body, 'BEGIN:VEVENT'))->toBe(substr_count($body, 'END:VEVENT'));

    foreach (explode("\r\n", $body) as $line) {
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }

    expect(CalendarFeed::query()->withoutGlobalScope(OwnedByCurrentUserScope::class)->first()->last_fetched_at)->not->toBeNull();
});

it('includes reminders 14 days before a deadline when switched on', function () {
    $this->putJson('/api/v1/settings/calendar-feed', [
        'options' => ['charges' => false, 'deadlines' => false, 'reminders' => true, 'amounts' => false],
    ]);
    $token = createFeedToken($this);

    $body = $this->get("/api/v1/ical/{$token}.ics")->getContent();

    // ADAC deadline 01.12.2026 → reminder 17.11.2026
    expect($body)->toContain("DTSTART;VALUE=DATE:20261117\r\n")
        ->and($body)->toContain('SUMMARY:Erinnerung: Frist für ADAC\, Plus in 14 Tagen')
        ->and($body)->not->toContain('SUMMARY:Netflix');
});

it('invalidates the old link on rotate and on switching off', function () {
    $old = createFeedToken($this);
    $new = createFeedToken($this);

    $this->get("/api/v1/ical/{$old}.ics")->assertNotFound();
    $this->get("/api/v1/ical/{$new}.ics")->assertOk();

    $this->deleteJson('/api/v1/settings/calendar-feed')->assertOk()->assertJsonPath('data.active', false);
    $this->get("/api/v1/ical/{$new}.ics")->assertNotFound();
});

it('rejects unknown and malformed tokens', function () {
    $this->get('/api/v1/ical/'.str_repeat('a', 64).'.ics')->assertNotFound();
    $this->get('/api/v1/ical/abc.ics')->assertNotFound();
});

it('only ever shows the feed owner\'s subscriptions', function () {
    $aliceToken = createFeedToken($this);

    $bob = User::factory()->create();
    Subscription::factory()->for($bob)->create(['name' => 'Bobs Geheimabo', 'amount_cents' => 999, 'anchor_date' => '2026-01-20']);
    Sanctum::actingAs($bob);
    $bobToken = createFeedToken($this);

    expect($this->getJson('/api/v1/settings/calendar-feed')->json('data.preview.0.title'))->toBe('Bobs Geheimabo');

    auth()->forgetGuards();
    $alice = $this->get("/api/v1/ical/{$aliceToken}.ics")->getContent();
    $bobFeed = $this->get("/api/v1/ical/{$bobToken}.ics")->getContent();

    expect($alice)->not->toContain('Bobs Geheimabo')->toContain('Netflix')
        ->and($bobFeed)->toContain('Bobs Geheimabo')->not->toContain('Netflix');

    // Bob's settings never touched Alice's row
    expect(CalendarFeed::query()->withoutGlobalScope(OwnedByCurrentUserScope::class)->count())->toBe(2);
});

it('requires login for the settings', function () {
    auth()->forgetGuards();

    $this->getJson('/api/v1/settings/calendar-feed')->assertUnauthorized();
    $this->postJson('/api/v1/settings/calendar-feed/rotate')->assertUnauthorized();
});
