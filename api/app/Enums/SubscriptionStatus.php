<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Paused = 'paused';
    /** Installment after its last payment (set automatically) */
    case Finished = 'finished';
}
