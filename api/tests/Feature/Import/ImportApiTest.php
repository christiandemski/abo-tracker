<?php

use App\Jobs\SendPushReminders;
use App\Models\ImportBatch;
use App\Models\ImportCandidate;
use App\Models\ImportRule;
use App\Models\PriceChange;
use App\Models\PushToken;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Database\Seeders\TemplateSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-06 12:00:00');
    $this->seed([CategorySeeder::class, TemplateSeeder::class]);
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function upload(?string $contents = null, string $name = 'umsaetze_jan-okt.csv'): TestResponse
{
    return test()->post('/api/v1/imports', [
        'file' => UploadedFile::fake()->createWithContent($name, $contents ?? sparkasseFixture()),
        'bank_format' => 'sparkasse_camt_v2',
    ], ['Accept' => 'application/json']);
}

it('lists the supported bank formats', function () {
    $this->getJson('/api/v1/imports/formats')
        ->assertOk()
        ->assertJsonPath('data.0.key', 'sparkasse_camt_v2')
        ->assertJsonPath('data.0.label', 'Sparkasse (CSV-CAMT V2)');
});

describe('upload', function () {
    it('detects recurring payments and stores no raw data', function () {
        $response = upload()->assertCreated()
            ->assertJsonPath('data.file_name', 'umsaetze_jan-okt.csv')
            ->assertJsonPath('data.period_from', '2026-01-01')
            ->assertJsonPath('data.period_to', '2026-10-23') // last debit; the salary on 28.10. is a credit
            ->assertJsonPath('data.months', 10)
            ->assertJsonPath('data.counts.new', 6);

        // Nothing but the candidates: no IBANs, no groceries, no salary, no one-off purchases
        $dump = json_encode(DB::table('import_candidates')->get()).json_encode(DB::table('import_batches')->get());
        expect($dump)->not->toContain('DE0000000000')
            ->not->toContain('REWE')
            ->not->toContain('Zalando')
            ->not->toContain('Lohn')
            ->not->toContain('Arbeitgeber');

        $candidates = $this->getJson("/api/v1/imports/{$response->json('data.id')}/candidates")->assertOk()->json('data');
        $byName = collect($candidates)->keyBy('name');

        expect($byName->keys()->sort()->values()->all())->toBe([
            'Allianz Versicherungs', 'Disney+', 'FITX', 'Netflix', 'Rundfunkbeitrag', 'Spotify',
        ])
            ->and($byName['Rundfunkbeitrag']['rhythm_label'])->toBe('vierteljährlich')
            ->and($byName['Rundfunkbeitrag']['monthly_cents'])->toBe(1836)
            ->and($byName['Rundfunkbeitrag']['category']['key'])->toBe('other')
            ->and($byName['Allianz Versicherungs']['confidence'])->toBe('check')
            ->and($byName['Allianz Versicherungs']['rhythm_label'])->toBe('jährlich')
            ->and($byName['FITX']['amount_cents'])->toBe(3290)
            ->and($byName['Spotify']['category']['key'])->toBe('streaming');
    });

    it('rejects other formats with a helpful message', function () {
        upload("Datum,Text\n1,2\n", 'export.csv')->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Die Datei sieht nicht wie ein CSV-CAMT-V2-Export der Sparkasse aus. Prüf bitte das Exportformat.');

        expect(ImportBatch::count())->toBe(0);
    });

    it('limits the file size to 2 MB', function () {
        $this->post('/api/v1/imports', [
            'file' => UploadedFile::fake()->create('big.csv', 3000, 'text/csv'),
            'bank_format' => 'sparkasse_camt_v2',
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');
    });

    it('validates the bank format', function () {
        $this->post('/api/v1/imports', [
            'file' => UploadedFile::fake()->createWithContent('a.csv', sparkasseFixture()),
            'bank_format' => 'ing',
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('bank_format');
    });
});

describe('matching existing subscriptions', function () {
    it('greys out duplicates and reports price changes', function () {
        Queue::fake();
        $spotify = Subscription::factory()->for($this->user)->create(['name' => 'Spotify', 'amount_cents' => 1099]);
        $gym = Subscription::factory()->for($this->user)->create(['name' => 'FitX', 'amount_cents' => 2990]);
        $token = new PushToken(['expo_token' => 'ExponentPushToken[x]']);
        $token->user_id = $this->user->id;
        $token->save();

        $id = upload()->assertCreated()->assertJsonPath('data.counts.duplicate', 1)->assertJsonPath('data.counts.price_change', 1)->json('data.id');

        $byName = collect($this->getJson("/api/v1/imports/{$id}/candidates")->json('data'))->keyBy(fn ($c) => $c['subscription']['name'] ?? $c['name']);
        expect($byName['Spotify']['status'])->toBe('duplicate')
            ->and($byName['FitX']['status'])->toBe('price_change');

        $pc = PriceChange::sole();
        expect([$pc->subscription_id, $pc->old_cents, $pc->new_cents, $pc->effective_date->toDateString(), $pc->status->value, $pc->source])
            ->toBe([$gym->id, 2990, 3290, '2026-09-01', 'pending', 'import']);

        // Push right after the import, without amounts
        Queue::assertPushed(SendPushReminders::class, fn ($job) => $job->messages[0]['body'] === 'Bei FitX hat sich der Preis geändert.');

        // Re-importing the same file does not report the change twice
        upload()->assertCreated();
        expect(PriceChange::count())->toBe(1);
    });

    it('does not suggest an outdated price if the price was changed after the statement', function () {
        // Spotify costs 10,99 € in the statement (last booking 15.10.2026),
        // but the user entered a price cut to 9,99 € effective 06.11.2026.
        $spotify = Subscription::factory()->for($this->user)->create(['name' => 'Spotify', 'amount_cents' => 999]);
        $manual = new PriceChange([
            'subscription_id' => $spotify->id, 'old_cents' => 1099, 'new_cents' => 999,
            'effective_date' => '2026-11-06', 'source' => 'manual', 'status' => 'accepted',
        ]);
        $manual->user_id = $this->user->id;
        $manual->save();

        $id = upload()->assertCreated()->assertJsonPath('data.counts.price_change', 0)->json('data.id');

        $spotifyCandidate = collect($this->getJson("/api/v1/imports/{$id}/candidates")->json('data'))
            ->firstWhere('subscription.name', 'Spotify');
        expect($spotifyCandidate['status'])->toBe('duplicate')
            ->and(PriceChange::query()->where('source', 'import')->count())->toBe(0);
    });

    it('still reports a price change if the manual change is older than the statement', function () {
        $spotify = Subscription::factory()->for($this->user)->create(['name' => 'Spotify', 'amount_cents' => 999]);
        $manual = new PriceChange([
            'subscription_id' => $spotify->id, 'old_cents' => 1099, 'new_cents' => 999,
            'effective_date' => '2026-10-01', 'source' => 'manual', 'status' => 'accepted',
        ]);
        $manual->user_id = $this->user->id;
        $manual->save();

        upload()->assertCreated()->assertJsonPath('data.counts.price_change', 1);
    });

    it('recognizes taken-over subscriptions on the next import via import rules', function () {
        $id = upload()->json('data.id');
        $ids = collect($this->getJson("/api/v1/imports/{$id}/candidates")->json('data'))->pluck('id')->all();
        $this->postJson("/api/v1/imports/{$id}/apply", ['candidate_ids' => $ids])->assertOk();

        $second = upload()->assertCreated()->json('data');

        expect($second['counts']['new'])->toBe(0)
            ->and($second['counts']['duplicate'])->toBe(6)
            ->and($second['counts']['price_change'])->toBe(0) // Fitnessstudio created with the current price
            ->and(ImportRule::count())->toBe(6);
    });
});

describe('apply', function () {
    it('creates the selected subscriptions and deletes all candidates', function () {
        $id = upload()->json('data.id');
        $candidates = collect($this->getJson("/api/v1/imports/{$id}/candidates")->json('data'))->keyBy('name');
        $selected = [$candidates['Spotify']['id'], $candidates['Rundfunkbeitrag']['id']];

        $this->postJson("/api/v1/imports/{$id}/summary", ['candidate_ids' => $selected])
            ->assertOk()->assertJsonPath('data.monthly_added_cents', 1099 + 1836);

        $this->postJson("/api/v1/imports/{$id}/apply", ['candidate_ids' => $selected])
            ->assertOk()
            ->assertJsonPath('data.created_count', 2)
            ->assertJsonPath('data.monthly_added_cents', 2935);

        $spotify = Subscription::query()->where('name', 'Spotify')->sole();
        expect($spotify->source->value)->toBe('import')
            ->and($spotify->interval_unit->value)->toBe('month')
            ->and($spotify->anchor_date->toDateString())->toBe('2026-10-15')
            ->and($spotify->next_charge_date->toDateString())->toBe('2026-11-15')
            ->and($spotify->payment_method)->toBe('Lastschrift')
            ->and(ImportCandidate::count())->toBe(0)
            ->and(ImportBatch::sole()->status)->toBe('applied');

        $this->postJson("/api/v1/imports/{$id}/apply", ['candidate_ids' => $selected])->assertUnprocessable();
    });

    it('prunes candidates after 24 hours', function () {
        upload();
        CarbonImmutable::setTestNow('2026-11-07 12:01:00');
        Carbon\Carbon::setTestNow('2026-11-07 12:01:00');

        Artisan::call('imports:prune');

        expect(ImportCandidate::withoutGlobalScopes()->count())->toBe(0);
        Carbon\Carbon::setTestNow();
    });
});

describe('tenant isolation', function () {
    it('lets user B neither see nor apply user A\'s import', function () {
        $id = upload()->json('data.id');
        $ids = ImportCandidate::query()->pluck('id')->all();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/imports/{$id}/candidates")->assertNotFound();
        $this->postJson("/api/v1/imports/{$id}/summary", ['candidate_ids' => $ids])->assertNotFound();
        $this->postJson("/api/v1/imports/{$id}/apply", ['candidate_ids' => $ids])->assertNotFound();

        expect(Subscription::withoutGlobalScopes()->count())->toBe(0);
    });

    it('never matches other users\' subscriptions', function () {
        $other = User::factory()->create();
        Subscription::factory()->for($other)->create(['name' => 'Spotify', 'amount_cents' => 1099]);

        upload()->assertJsonPath('data.counts.duplicate', 0)->assertJsonPath('data.counts.new', 6);
    });
});
