<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payouts\Pages;

use App\Exceptions\PayoutException;
use App\Filament\Resources\Payouts\PayoutResource;
use App\Jobs\ReconcileUncertainPayoutsJob;
use App\Services\PayoutService;
use App\Support\Authorization\PermissionName;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPayouts extends ListRecords
{
    protected static string $resource = PayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runPayoutCycle')
                ->label('Run payout cycle')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Run payout cycle')
                ->modalDescription('Dispatches ProcessInstructorPayoutJob for every instructor via PayoutService (same path as POST /api/v1/payouts/run).')
                ->visible(fn (): bool => auth()->user()?->can(PermissionName::PayoutsRun) ?? false)
                ->action(function (PayoutService $payoutService): void {
                    try {
                        $result = $payoutService->startScheduledCycle();
                    } catch (PayoutException $exception) {
                        Notification::make()
                            ->title('Payout cycle blocked')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Payout cycle dispatched')
                        ->body("Batch #{$result['batch']->id} — {$result['dispatched_jobs']} jobs queued.")
                        ->success()
                        ->send();
                }),

            Action::make('reconcileUncertain')
                ->label('Reconcile uncertain')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Reconcile uncertain payouts')
                ->modalDescription('Queues ReconcileUncertainPayoutsJob (same path as POST /api/v1/payouts/reconcile). No Filament form validation — authorization is Spatie permission payouts.reconcile.')
                ->visible(fn (): bool => auth()->user()?->can(PermissionName::PayoutsReconcile) ?? false)
                ->action(function (): void {
                    ReconcileUncertainPayoutsJob::dispatch();

                    Notification::make()
                        ->title('Reconciliation queued')
                        ->success()
                        ->send();
                }),
        ];
    }
}
