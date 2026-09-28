<?php

declare(strict_types=1);

namespace App\Filament\Resources\Subscriptions\Pages;

use App\DTOs\Subscription\AllocatePeriodDTO;
use App\Enums\SubscriptionPeriodStatus;
use App\Exceptions\SubscriptionException;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Http\Requests\Api\V1\Subscription\AllocatePeriodRequest;
use App\Models\SubscriptionPeriod;
use App\Models\User;
use App\Services\RevenueAllocationService;
use App\Support\Authorization\PermissionName;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ViewSubscription extends ViewRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('allocatePeriod')
                ->label('Allocate period revenue')
                ->icon('heroicon-o-calculator')
                ->visible(fn (): bool => auth()->user()?->can(PermissionName::PeriodsAllocate) ?? false)
                ->form([
                    Select::make('period_id')
                        ->label('Pending period')
                        ->options(fn (): array => $this->record->periods()
                            ->where('status', SubscriptionPeriodStatus::Pending->value)
                            ->orderBy('period_number')
                            ->get()
                            ->mapWithKeys(fn (SubscriptionPeriod $p) => [
                                $p->id => "Period #{$p->period_number} ({$p->period_amount_cents}¢)",
                            ])
                            ->all())
                        ->required(),
                    Repeater::make('consumption_weights')
                        ->schema([
                            Select::make('instructor_id')
                                ->options(fn () => User::query()->where('is_instructor', true)->pluck('email', 'id'))
                                ->required()
                                ->searchable(),
                            TextInput::make('weight')->numeric()->required()->minValue(0)->maxValue(1),
                        ])
                        ->minItems(1)
                        ->required(),
                ])
                ->action(function (array $data, RevenueAllocationService $allocation): void {
                    try {
                        $validated = Validator::make(
                            ['consumption_weights' => $data['consumption_weights'] ?? []],
                            AllocatePeriodRequest::ruleSet(),
                        )->validate();
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Validation failed')
                            ->body(collect($exception->errors())->flatten()->implode(' '))
                            ->danger()
                            ->send();

                        throw $exception;
                    }

                    $period = SubscriptionPeriod::query()->findOrFail((int) $data['period_id']);
                    $dto = AllocatePeriodDTO::fromRequest($validated);

                    try {
                        $allocation->allocatePeriod($period, $dto->consumptionWeights);
                    } catch (SubscriptionException $exception) {
                        Notification::make()
                            ->title('Allocation failed')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Period allocated')
                        ->body("Period #{$period->id} distributed via RevenueAllocationService.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
