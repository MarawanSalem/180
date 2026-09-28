<?php

namespace App\Filament\Resources\Subscriptions\RelationManagers;

use App\Enums\SubscriptionPeriodStatus;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PeriodsRelationManager extends RelationManager
{
    protected static string $relationship = 'periods';

    protected static ?string $title = 'Amortization schedule';

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('period_number')
                    ->numeric(),
                TextEntry::make('period_amount_cents')
                    ->numeric(),
                TextEntry::make('period_start')
                    ->date(),
                TextEntry::make('period_end')
                    ->date(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('period_number')
            ->defaultSort('period_number')
            ->columns([
                TextColumn::make('period_number')
                    ->label('#')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('period_amount_cents')
                    ->label('Amount (cents)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('period_start')
                    ->date()
                    ->sortable(),
                TextColumn::make('period_end')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SubscriptionPeriodStatus::class),
            ])
            ->headerActions([])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
