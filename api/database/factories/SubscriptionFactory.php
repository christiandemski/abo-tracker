<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Netflix', 'Spotify', 'Fitnessstudio', 'Internet', 'Zeitung']),
            'amount_cents' => fake()->numberBetween(299, 4999),
            'interval_unit' => 'month',
            'interval_count' => 1,
            'anchor_date' => now()->subMonths(2)->toDateString(),
            'status' => 'active',
            'remind' => true,
            'source' => 'manual',
        ];
    }

    public function yearly(int $amountCents): static
    {
        return $this->state(fn () => ['interval_unit' => 'year', 'amount_cents' => $amountCents]);
    }

    public function withTerm(string $start, int $minTermMonths, ?int $renewalMonths, int $noticeValue, string $noticeUnit): static
    {
        return $this->state(fn () => [
            'start_date' => $start,
            'min_term_months' => $minTermMonths,
            'renewal_months' => $renewalMonths,
            'notice_value' => $noticeValue,
            'notice_unit' => $noticeUnit,
        ]);
    }

    public function cancelled(?string $endsAt = null): static
    {
        return $this->state(fn () => ['status' => 'cancelled', 'ends_at' => $endsAt]);
    }

    public function paused(): static
    {
        return $this->state(fn () => ['status' => 'paused']);
    }
}
