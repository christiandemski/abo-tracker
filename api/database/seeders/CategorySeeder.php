<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Default categories (user_id = null). Colors from docs/DESIGN.md – no category is orange.
 * Idempotent: safe to run on every deployment.
 */
class CategorySeeder extends Seeder
{
    public const DEFAULTS = [
        ['key' => 'telecom', 'name' => 'Telefon & Internet', 'color' => '#8EA8FF'],
        ['key' => 'streaming', 'name' => 'Streaming', 'color' => '#B5E36B'],
        ['key' => 'sport', 'name' => 'Sport', 'color' => '#4FD1C5'],
        ['key' => 'software', 'name' => 'Software & Cloud', 'color' => '#C9A7FF'],
        ['key' => 'insurance', 'name' => 'Versicherung', 'color' => '#F48FB1'],
        ['key' => 'mobility', 'name' => 'Mobilität', 'color' => '#6B7385'],
        ['key' => 'loans', 'name' => 'Kredite & Raten', 'color' => '#7DD3FC'],
        ['key' => 'savings', 'name' => 'Sparen & Vorsorge', 'color' => '#86EFAC'],
        ['key' => 'other', 'name' => 'Sonstiges', 'color' => '#9AA3B5'],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $sort => $category) {
            $model = Category::query()
                ->whereNull('user_id')
                ->where('key', $category['key'])
                ->firstOrNew();

            $model->forceFill([
                'user_id' => null,
                'key' => $category['key'],
                'name' => $category['name'],
                'color' => $category['color'],
                'sort' => $sort,
            ])->save();
        }
    }
}
