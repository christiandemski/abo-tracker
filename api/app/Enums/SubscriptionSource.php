<?php

namespace App\Enums;

enum SubscriptionSource: string
{
    case Manual = 'manual';
    case Import = 'import';
    case Template = 'template';
}
