<?php

use App\Models\Category;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->seed(CategorySeeder::class);
    $this->alice = User::factory()->create();
    $this->bob = User::factory()->create();

    $this->actingAs($this->alice);
    $this->aliceCategory = Category::create(['name' => 'Zeitschriften', 'color' => '#9AA3B5']);
});

it('shows default categories plus own ones', function () {
    expect(Category::query()->count())->toBe(8)
        ->and($this->aliceCategory->user_id)->toBe($this->alice->id);
});

it('hides user A\'s categories from user B', function () {
    $this->actingAs($this->bob);

    expect(Category::query()->count())->toBe(7)
        ->and(Category::find($this->aliceCategory->id))->toBeNull();
});

it('keeps default categories read-only', function () {
    $default = Category::query()->whereNull('user_id')->first();

    expect(Gate::forUser($this->alice)->allows('view', $default))->toBeTrue()
        ->and(Gate::forUser($this->alice)->allows('update', $default))->toBeFalse()
        ->and(Gate::forUser($this->alice)->allows('update', $this->aliceCategory))->toBeTrue()
        ->and(Gate::forUser($this->bob)->allows('view', $this->aliceCategory))->toBeFalse();
});
