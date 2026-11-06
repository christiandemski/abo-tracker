<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Private iCal link of a user. Only the SHA-256 hash of the token is stored.
 */
#[Fillable(['options'])]
class CalendarFeed extends Model
{
    use BelongsToUser;

    /** What goes into the calendar (defaults: charges and deadlines, no amounts) */
    public const DEFAULT_OPTIONS = [
        'charges' => true,
        'deadlines' => true,
        'reminders' => false,
        'amounts' => false,
    ];

    /** Pre-reminder in the feed, days before a deadline */
    public const REMINDER_DAYS = 14;

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'token_created_at' => 'immutable_datetime',
            'last_fetched_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return array{charges: bool, deadlines: bool, reminders: bool, amounts: bool}
     */
    public function resolvedOptions(): array
    {
        $stored = is_array($this->options) ? $this->options : [];
        $options = [];
        foreach (self::DEFAULT_OPTIONS as $key => $default) {
            $options[$key] = array_key_exists($key, $stored) ? (bool) $stored[$key] : $default;
        }

        /** @var array{charges: bool, deadlines: bool, reminders: bool, amounts: bool} */
        return $options;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isActive(): bool
    {
        return $this->token_hash !== null;
    }
}
