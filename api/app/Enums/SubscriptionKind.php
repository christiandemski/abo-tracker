<?php

namespace App\Enums;

/**
 * What a recurring payment is. Only subscriptions have a term, deadlines and the
 * saving features; installments end on their own, savings are not fixed costs.
 */
enum SubscriptionKind: string
{
    case Subscription = 'subscription';
    case Installment = 'installment';
    case Savings = 'savings';

    public function label(): string
    {
        return match ($this) {
            self::Subscription => 'Abo',
            self::Installment => 'Rate',
            self::Savings => 'Sparen',
        };
    }
}
