<?php

use App\Models\ReminderSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\ReminderDigestNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-06 10:00:00');
    $this->user = User::factory()->create(['name' => 'Chris Beispiel']);
    Sanctum::actingAs($this->user);
    $this->adobe = Subscription::factory()->for($this->user)->yearly(14388)
        ->withTerm('2023-11-12', 12, 12, 1, 'day')
        ->create(['name' => 'Adobe Creative Cloud', 'anchor_date' => '2026-11-12']);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

describe('letter', function () {
    it('builds the letter with placeholders for missing data', function () {
        $data = $this->getJson("/api/v1/subscriptions/{$this->adobe->id}/cancellation-letter")->assertOk()->json('data');

        expect($data['expected_end'])->toBe('2026-11-11')
            ->and($data['subject'])->toBe('Kündigung meines Vertrags „Adobe Creative Cloud“')
            ->and($data['text'])->toContain('Chris Beispiel')
            ->toContain('[Straße und Hausnummer]')
            ->toContain('Kundennummer: [Kundennummer]')
            ->toContain('voraussichtlich zum 11.11.2026')
            ->toContain('06.11.2026')
            ->and($data['missing'])->toBe(['address', 'customer_number'])
            ->and($data['recipient_email'])->toBeNull();
    });

    it('fills in stored address, customer number and provider mail', function () {
        $this->patchJson('/api/v1/me', ['address_street' => 'Musterweg 1', 'address_zip' => '12345', 'address_city' => 'Musterstadt'])
            ->assertOk()->assertJsonPath('data.address_city', 'Musterstadt');
        $this->patchJson("/api/v1/subscriptions/{$this->adobe->id}", ['customer_number' => 'AD-4711', 'provider_email' => 'kuendigung@adobe.example'])
            ->assertOk()->assertJsonPath('data.customer_number', 'AD-4711');

        $data = $this->getJson("/api/v1/subscriptions/{$this->adobe->id}/cancellation-letter")->json('data');

        expect($data['missing'])->toBe([])
            ->and($data['text'])->toContain("Musterweg 1\n12345 Musterstadt")->toContain('Kundennummer: AD-4711')
            ->and($data['recipient_email'])->toBe('kuendigung@adobe.example');
    });

    it('uses the day before the next charge without a minimum term', function () {
        $netflix = Subscription::factory()->for($this->user)->create(['name' => 'Netflix', 'anchor_date' => '2026-10-15']);

        expect($this->getJson("/api/v1/subscriptions/{$netflix->id}/cancellation-letter")->json('data.expected_end'))->toBe('2026-11-14');
    });

    it('renders a PDF from the template and from edited text', function () {
        $this->get("/api/v1/subscriptions/{$this->adobe->id}/cancellation-letter?format=pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="Kuendigung-adobe-creative-cloud.pdf"');

        $response = $this->post("/api/v1/subscriptions/{$this->adobe->id}/cancellation-letter", ['text' => "Mein eigener Text mit Umlauten: äöü ß\n\nGruß"]);
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect(substr($response->getContent(), 0, 5))->toBe('%PDF-');
    });
});

describe('cancel ("Ich habe gekündigt")', function () {
    it('records date and method and shows the savings', function () {
        $this->postJson("/api/v1/subscriptions/{$this->adobe->id}/cancel", ['cancel_method' => 'online'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.ends_at', '2026-11-11')
            ->assertJsonPath('data.cancelled_at', '2026-11-06')
            ->assertJsonPath('data.cancel_method', 'online')
            ->assertJsonPath('data.yearly_cents', 14388);

        $this->postJson("/api/v1/subscriptions/{$this->adobe->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.cancelled_at', null)
            ->assertJsonPath('data.cancel_method', null);
    });
});

describe('keep ("Behalten")', function () {
    it('hides the deadline in dashboard and reminders until the next renewal', function () {
        $this->getJson('/api/v1/dashboard')->assertJsonPath('data.upcoming.0.type', 'deadline');

        $this->postJson("/api/v1/subscriptions/{$this->adobe->id}/keep")
            ->assertOk()->assertJsonPath('data.kept_until', '2026-11-12')->assertJsonPath('data.is_kept', true);

        expect(collect($this->getJson('/api/v1/dashboard')->json('data.upcoming'))->where('type', 'deadline'))->toBeEmpty();

        Notification::fake();
        ReminderSetting::forUser($this->user)->update(['offsets_days' => [3]]);
        CarbonImmutable::setTestNow('2026-11-07 07:00:00');
        Artisan::call('reminders:dispatch');
        Notification::assertNothingSent();

        // next term: reminders are back (cancel by 10.11.2027, 3 days before)
        CarbonImmutable::setTestNow('2027-11-07 07:00:00');
        Artisan::call('reminders:dispatch');
        Notification::assertSentTo($this->user, ReminderDigestNotification::class);
    });

    it('shows the next term\'s deadline even if it lies before kept_until (monthly renewal)', function () {
        // Telekom: start 01.12.2024, 24 months, then monthly, notice 1 month.
        // Kept term renews 01.01.2027 (cancel by 30.11.2026). The next deadline
        // (31.12.2026 for the renewal on 01.02.2027) is before kept_until, but belongs to the next term.
        $telekom = Subscription::factory()->for($this->user)
            ->withTerm('2024-12-01', 24, 1, 1, 'month')
            ->create(['name' => 'Telekom', 'anchor_date' => '2026-12-01']);

        $this->postJson("/api/v1/subscriptions/{$telekom->id}/keep")
            ->assertOk()->assertJsonPath('data.kept_until', '2027-01-01')->assertJsonPath('data.is_kept', true);

        Notification::fake();
        ReminderSetting::forUser($this->user)->update(['offsets_days' => [3]]);

        // 3 days before 30.11.: kept → nothing
        CarbonImmutable::setTestNow('2026-11-27 07:00:00');
        Artisan::call('reminders:dispatch');
        Notification::assertNothingSent();

        // 3 days before 31.12.: next term → reminder again
        CarbonImmutable::setTestNow('2026-12-28 07:00:00');
        Artisan::call('subscriptions:refresh-dates');
        Artisan::call('reminders:dispatch');
        Notification::assertSentTo($this->user, ReminderDigestNotification::class);

        expect($telekom->fresh()->cancel_by_date->toDateString())->toBe('2026-12-31')
            ->and($telekom->fresh()->isKeptForCurrentTerm())->toBeFalse();
    });

    it('can be undone', function () {
        $this->postJson("/api/v1/subscriptions/{$this->adobe->id}/keep")->assertOk();
        $this->deleteJson("/api/v1/subscriptions/{$this->adobe->id}/keep")->assertOk()->assertJsonPath('data.is_kept', false);
    });

    it('is only possible with a term', function () {
        $netflix = Subscription::factory()->for($this->user)->create();
        $this->postJson("/api/v1/subscriptions/{$netflix->id}/keep")->assertUnprocessable();
    });
});

it('protects letters, PDFs and keep from other users', function () {
    Sanctum::actingAs(User::factory()->create());
    $id = $this->adobe->id;

    $this->getJson("/api/v1/subscriptions/{$id}/cancellation-letter")->assertNotFound();
    $this->post("/api/v1/subscriptions/{$id}/cancellation-letter", ['text' => 'x'], ['Accept' => 'application/json'])->assertNotFound();
    $this->postJson("/api/v1/subscriptions/{$id}/keep")->assertNotFound();
    $this->deleteJson("/api/v1/subscriptions/{$id}/keep")->assertNotFound();
});
