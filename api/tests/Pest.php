<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Unit tests get the application (casts, config) but no database.
pest()->extend(TestCase::class)->in('Unit');

/** Synthetic Sparkasse CSV-CAMT V2 export (Windows-1252), see tests/Fixtures/import/make_sparkasse_fixture.php */
function sparkasseFixture(): string
{
    return file_get_contents(__DIR__.'/Fixtures/import/sparkasse_camt_v2.csv');
}
