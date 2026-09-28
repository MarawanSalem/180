<?php

declare(strict_types=1);

namespace App\Filament\Resources\Subscriptions\Pages;

use App\DTOs\Subscription\CreateSubscriptionDTO;
use App\Enums\PlanTier;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Http\Requests\Api\V1\Subscription\StoreSubscriptionRequest;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Support\Authorization\PermissionName;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createSubscription')
                ->label('Create subscription')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => auth()->user()?->can(PermissionName::SubscriptionsCreate) ?? false)
                ->form([
                    Select::make('user_id')
                        ->label('Student')
                        ->options(fn () => User::query()->where('is_instructor', false)->orderBy('name')->pluck('email', 'id'))
                        ->searchable()
                        ->required(),
                    Select::make('plan_tier')
                        ->options(collect(PlanTier::cases())->mapWithKeys(fn (PlanTier $t) => [$t->value => $t->value]))
                        ->required(),
                    TextInput::make('price_cents')->numeric()->required(),
                    TextInput::make('platform_fee_bps')->numeric()->default(3000)->required(),
                    DateTimePicker::make('starts_at')->required()->default(now()),
                ])
                ->action(function (array $data, SubscriptionService $subscriptions): void {
                    // Validation lives in StoreSubscriptionRequest::ruleSet — not Filament field rules.
                    $payload = collect($data)->except('user_id')->all();

                    try {
                        $validated = Validator::make($payload, StoreSubscriptionRequest::ruleSet())->validate();
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Validation failed')
                            ->body(collect($exception->errors())->flatten()->implode(' '))
                            ->danger()
                            ->send();

                        throw $exception;
                    }

                    $student = User::query()->findOrFail((int) $data['user_id']);
                    $dto = CreateSubscriptionDTO::fromRequest($validated);

                    $subscription = $subscriptions->createSubscription(
                        actor: $student,
                        planTier: $dto->planTier,
                        priceCents: $dto->priceCents,
                        platformFeeBps: $dto->platformFeeBps,
                        startsAt: $dto->startsAt,
                    );

                    Notification::make()
                        ->title('Subscription created')
                        ->body("Subscription #{$subscription->id} for {$student->email}")
                        ->success()
                        ->send();
                }),
        ];
    }
}
