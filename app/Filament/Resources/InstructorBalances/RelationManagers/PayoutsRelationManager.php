<?php

namespace App\Filament\Resources\InstructorBalances\RelationManagers;

use App\Enums\PayoutStatus;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PayoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'payouts';

    protected static ?string $title = 'Payout history';

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('batch.batch_uuid')
                    ->label('Batch UUID'),
                TextEntry::make('amount_cents')
                    ->numeric(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('provider_tx_id')
                    ->placeholder('-'),
                TextEntry::make('attempts')
                    ->numeric(),
                TextEntry::make('failure_reason')
                    ->placeholder('-'),
                TextEntry::make('idempotency_key'),
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
            ->recordTitleAttribute('idempotency_key')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('batch.batch_uuid')
                    ->label('Batch')
                    ->limit(12)
                    ->tooltip(fn ($record) => $record->batch?->batch_uuid),
                TextColumn::make('amount_cents')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('provider_tx_id')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('attempts')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('failure_reason')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(PayoutStatus::class),
            ])
            ->headerActions([])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
