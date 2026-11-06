<?php

namespace App\Console\Commands;

use App\Models\ImportCandidate;
use App\Models\Scopes\OwnedByCurrentUserScope;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Privacy: import suggestions that were not taken over are deleted after 24 hours.
 */
#[Signature('imports:prune')]
#[Description('Delete import candidates older than 24 hours')]
class PruneImports extends Command
{
    public function handle(): int
    {
        $deleted = ImportCandidate::query()
            ->withoutGlobalScope(OwnedByCurrentUserScope::class)
            ->where('created_at', '<', now()->subDay())
            ->delete();

        $this->info("Deleted {$deleted} import candidate(s).");

        return self::SUCCESS;
    }
}
