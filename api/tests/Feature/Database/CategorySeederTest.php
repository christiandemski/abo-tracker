<?php

use App\Models\Category;
use Database\Seeders\CategorySeeder;

it('seeds the nine default categories without orange', function () {
    $this->seed(CategorySeeder::class);

    $categories = Category::query()->whereNull('user_id')->orderBy('sort')->get();

    expect($categories)->toHaveCount(9)
        ->and($categories->pluck('name')->all())->toBe([
            'Telefon & Internet', 'Streaming', 'Sport', 'Software & Cloud',
            'Versicherung', 'Mobilität', 'Kredite & Raten', 'Sparen & Vorsorge', 'Sonstiges',
        ])
        ->and($categories->pluck('color'))->not->toContain('#FFB25C');
});

it('is idempotent', function () {
    $this->seed(CategorySeeder::class);
    $this->seed(CategorySeeder::class);

    expect(Category::query()->whereNull('user_id')->count())->toBe(9);
});
