<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionPeriodStatus: string
{
    case Pending = 'pending';
    case Distributed = 'distributed';
    case Refunded = 'refunded';
    case Voided = 'voided';
}
