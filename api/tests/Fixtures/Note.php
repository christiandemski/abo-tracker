<?php

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

/**
 * Test-only model to verify the BelongsToUser tenant isolation.
 */
class Note extends Model
{
    use BelongsToUser;

    protected $table = 'test_notes';

    protected $fillable = ['title'];
}
