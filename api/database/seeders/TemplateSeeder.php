<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

/**
 * Templates for common services – deliberately without prices (they go stale).
 * The first eight (popular) are the tiles on the "Abo hinzufügen" screen.
 * Idempotent: safe to run on every deployment.
 */
class TemplateSeeder extends Seeder
{
    /** @var list<array{0: string, 1: string, 2: string, 3?: string, 4?: int, 5?: string}> key, name, category, unit, count, aliases */
    public const TEMPLATES = [
        // popular (tiles)
        ['netflix', 'Netflix', 'streaming'],
        ['spotify', 'Spotify', 'streaming'],
        ['disney-plus', 'Disney+', 'streaming', 'month', 1, 'disney plus'],
        ['amazon-prime', 'Amazon Prime', 'streaming', 'year', 1, 'prime video amazon'],
        ['icloud', 'iCloud+', 'software', 'month', 1, 'apple icloud speicher'],
        ['fitness', 'Fitnessstudio', 'sport', 'month', 1, 'gym mcfit urban sports'],
        ['mobile', 'Handyvertrag', 'telecom', 'month', 1, 'handy mobilfunk telekom vodafone o2'],
        ['internet', 'Internet', 'telecom', 'month', 1, 'dsl glasfaser kabel router'],
        // more
        ['youtube-premium', 'YouTube Premium', 'streaming', 'month', 1, 'youtube music'],
        ['apple-music', 'Apple Music', 'streaming'],
        ['apple-tv', 'Apple TV+', 'streaming', 'month', 1, 'apple tv plus'],
        ['dazn', 'DAZN', 'streaming', 'month', 1, 'sport fussball'],
        ['wow', 'WOW', 'streaming', 'month', 1, 'sky'],
        ['paramount-plus', 'Paramount+', 'streaming', 'month', 1, 'paramount plus'],
        ['audible', 'Audible', 'streaming', 'month', 1, 'hörbuch hoerbuch'],
        ['google-one', 'Google One', 'software', 'month', 1, 'google drive speicher'],
        ['microsoft-365', 'Microsoft 365', 'software', 'year', 1, 'office word excel'],
        ['adobe-cc', 'Adobe Creative Cloud', 'software', 'year', 1, 'photoshop lightroom'],
        ['dropbox', 'Dropbox', 'software', 'year', 1],
        ['chatgpt', 'ChatGPT Plus', 'software', 'month', 1, 'openai'],
        ['claude', 'Claude Pro', 'software', 'month', 1, 'anthropic'],
        ['1password', '1Password', 'software', 'year', 1, 'passwort manager'],
        ['strava', 'Strava', 'sport', 'year', 1],
        ['urban-sports', 'Urban Sports Club', 'sport', 'month', 1, 'usc'],
        ['rundfunk', 'Rundfunkbeitrag', 'other', 'month', 3, 'gez ard zdf'],
        ['haftpflicht', 'Haftpflichtversicherung', 'insurance', 'year', 1, 'versicherung'],
        ['hausrat', 'Hausratversicherung', 'insurance', 'year', 1, 'versicherung'],
        ['kfz-versicherung', 'Kfz-Versicherung', 'insurance', 'year', 1, 'auto versicherung'],
        ['deutschlandticket', 'Deutschlandticket', 'mobility', 'month', 1, 'ticket bahn öpnv oepnv'],
        ['bahncard', 'BahnCard', 'mobility', 'year', 1, 'db bahn'],
        ['zeitung', 'Zeitungsabo', 'other', 'month', 1, 'zeitschrift magazin'],
    ];

    public const POPULAR_COUNT = 8;

    public function run(): void
    {
        foreach (self::TEMPLATES as $sort => $t) {
            Template::query()->updateOrCreate(['key' => $t[0]], [
                'name' => $t[1],
                'category_key' => $t[2],
                'interval_unit' => $t[3] ?? 'month',
                'interval_count' => $t[4] ?? 1,
                'aliases' => $t[5] ?? null,
                'popular' => $sort < self::POPULAR_COUNT,
                'sort' => $sort,
            ]);
        }
    }
}
