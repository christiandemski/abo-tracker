<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use App\Models\Scopes\OwnedByCurrentUserScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['offsets_days', 'push', 'email', 'charge_reminders'])]
class ReminderSetting extends Model
{
    use BelongsToUser;

    /** Offsets users can choose from (days before the cancel-by date). */
    public const AVAILABLE_OFFSETS = [30, 14, 7, 3, 1];

    public const DEFAULT_OFFSETS = [14, 3];

    protected function casts(): array
    {
        return [
            'offsets_days' => 'array',
            'push' => 'boolean',
            'email' => 'boolean',
            'charge_reminders' => 'boolean',
        ];
    }

    /**
     * Settings of a user, created with defaults on first access.
     */
    public static function forUser(User $user): self
    {
        $setting = static::query()
            ->withoutGlobalScope(OwnedByCurrentUserScope::class)
            ->where('user_id', $user->id)
            ->first();

        if ($setting) {
            return $setting;
        }

        $setting = new static([
            'offsets_days' => self::DEFAULT_OFFSETS,
            'push' => true,
            'email' => true,
            'charge_reminders' => false,
        ]);
        $setting->user_id = $user->id;
        $setting->save();

        return $setting;
    }

    /**
     * @return list<int> sorted descending, e.g. [14, 3]
     */
    public function offsets(): array
    {
        $offsets = array_values(array_unique(array_map('intval', $this->offsets_days ?: self::DEFAULT_OFFSETS)));
        rsort($offsets);

        return $offsets;
    }
}
