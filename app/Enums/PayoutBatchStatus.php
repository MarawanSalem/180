<?php

declare(strict_types=1);

namespace App\Enums;

enum PayoutBatchStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case PartiallyFailed = 'partially_failed';
}
