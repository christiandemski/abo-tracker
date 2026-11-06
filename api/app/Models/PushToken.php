<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['expo_token', 'device_name', 'last_seen_at'])]
class PushToken extends Model
{
    use BelongsToUser;

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'immutable_datetime',
        ];
    }
}
