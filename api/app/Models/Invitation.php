<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['email', 'token_hash', 'invited_by', 'is_admin', 'expires_at'])]
#[Hidden(['token_hash'])]
class Invitation extends Model
{
    use HasFactory;

    public const VALID_DAYS = 7;

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * Create an invitation and return it together with the plain token (never stored).
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(string $email, ?User $invitedBy = null, bool $isAdmin = false): array
    {
        $plainToken = Str::random(64);

        $invitation = static::create([
            'email' => Str::lower($email),
            'token_hash' => static::hashToken($plainToken),
            'invited_by' => $invitedBy?->id,
            'is_admin' => $isAdmin,
            'expires_at' => now()->addDays(self::VALID_DAYS),
        ]);

        return [$invitation, $plainToken];
    }

    public static function findValidByToken(string $plainToken): ?self
    {
        return static::query()
            ->where('token_hash', static::hashToken($plainToken))
            ->valid()
            ->first();
    }

    public function scopeValid(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public function isValid(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
