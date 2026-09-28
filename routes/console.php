<?php

declare(strict_types=1);

use App\Jobs\ReconcileUncertainPayoutsJob;
use Illuminate\Support\Facades\Schedule;

// Resolve uncertain payouts every 10 minutes (see docs/FEATURE_CHECKLIST.md §7.5).
Schedule::job(ReconcileUncertainPayoutsJob::class)->everyTenMinutes();

// Daily instructor payout cycle (see docs/FEATURE_CHECKLIST.md §5).
Schedule::command('payouts:run')->dailyAt('02:00')->withoutOverlapping();
