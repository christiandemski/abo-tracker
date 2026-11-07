<?php

use App\Models\Category;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Database\Seeders\TemplateSeeder;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed([CategorySeeder::class, TemplateSeeder::class]);
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

describe('categories', function () {
    it('lists defaults first, then own categories', function () {
        $this->postJson('/api/v1/categories', ['name' => 'Zeitschriften', 'color' => '#9AA3B5'])->assertCreated();

        $data = $this->getJson('/api/v1/categories')->assertOk()->json('data');

        expect($data)->toHaveCount(10)
            ->and($data[0]['name'])->toBe('Telefon & Internet')
            ->and($data[0]['is_default'])->toBeTrue()
            ->and($data[9]['name'])->toBe('Zeitschriften')
            ->and($data[9]['is_default'])->toBeFalse();
    });

    it('only allows palette colors, never orange', function () {
        $this->postJson('/api/v1/categories', ['name' => 'Wichtig', 'color' => '#FFB25C'])
            ->assertUnprocessable()->assertJsonValidationErrors('color');
    });

    it('keeps default categories read-only', function () {
        $default = Category::query()->whereNull('user_id')->first();

        $this->patchJson("/api/v1/categories/{$default->id}", ['name' => 'x'])->assertForbidden();
        $this->deleteJson("/api/v1/categories/{$default->id}")->assertForbidden();
    });

    it('keeps subscriptions when their category is deleted', function () {
        $id = $this->postJson('/api/v1/categories', ['name' => 'Privat', 'color' => '#9AA3B5'])->json('data.id');
        $sub = Subscription::factory()->for($this->user)->create(['category_id' => $id]);

        $this->deleteJson("/api/v1/categories/{$id}")->assertNoContent();

        expect($sub->fresh()->category_id)->toBeNull();
    });

    it('lets user B neither see nor change user A\'s categories', function () {
        $id = $this->postJson('/api/v1/categories', ['name' => 'Nur Alice', 'color' => '#9AA3B5'])->json('data.id');
        Sanctum::actingAs(User::factory()->create());

        expect($this->getJson('/api/v1/categories')->json('data.*.name'))->not->toContain('Nur Alice');
        $this->patchJson("/api/v1/categories/{$id}", ['name' => 'x'])->assertNotFound();
        $this->deleteJson("/api/v1/categories/{$id}")->assertNotFound();
    });
});

describe('templates', function () {
    it('returns the 8 popular templates without prices', function () {
        $data = $this->getJson('/api/v1/templates')->assertOk()->json('data');

        expect($data)->toHaveCount(8)
            ->and($data[0])->toHaveKeys(['key', 'name', 'category_id', 'interval_unit', 'interval_count'])
            ->and($data[0])->not->toHaveKey('amount_cents')
            ->and($data[0]['category_id'])->not->toBeNull();
    });

    it('searches names and aliases', function () {
        expect($this->getJson('/api/v1/templates?q=gez')->json('data.*.name'))->toBe(['Rundfunkbeitrag'])
            ->and($this->getJson('/api/v1/templates?q=Disney')->json('data.*.key'))->toBe(['disney-plus'])
            ->and($this->getJson('/api/v1/templates?q=zzz')->json('data'))->toBe([]);
    });
});

describe('billing preview', function () {
    it('converts amounts for the live footer', function () {
        $this->getJson('/api/v1/billing/preview?amount_cents=14388&interval_unit=year')
            ->assertOk()
            ->assertJsonPath('data.monthly_cents', 1199)
            ->assertJsonPath('data.yearly_cents', 14388)
            ->assertJsonPath('data.rhythm_label', 'jährlich')
            ->assertJsonPath('data.is_converted', true);

        $this->getJson('/api/v1/billing/preview?amount_cents=3000&interval_unit=month&interval_count=3')
            ->assertJsonPath('data.monthly_cents', 1000)
            ->assertJsonPath('data.rhythm_label', 'vierteljährlich');
    });

    it('validates the input', function () {
        $this->getJson('/api/v1/billing/preview?interval_unit=x')
            ->assertUnprocessable()->assertJsonValidationErrors(['amount_cents', 'interval_unit']);
    });
});

it('refreshes stored dates daily for all users', function () {
    CarbonImmutable::setTestNow('2026-11-05 10:00:00');
    $sub = Subscription::factory()->for($this->user)->create(['anchor_date' => '2026-10-15']);
    expect($sub->next_charge_date->toDateString())->toBe('2026-11-15');

    CarbonImmutable::setTestNow('2026-11-16 00:05:00');
    auth()->forgetGuards();
    Artisan::call('subscriptions:refresh-dates');

    expect(Subscription::withoutGlobalScopes()->find($sub->id)->next_charge_date->toDateString())->toBe('2026-12-15');
    CarbonImmutable::setTestNow();
});
