<?php

namespace App\Enums;

enum PriceChangeStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Dismissed = 'dismissed';
}
