<?php

namespace App\Filament\Resources\InstructorBalances\RelationManagers;

use App\Enums\LedgerEntryType;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LedgerEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'ledgerEntries';

    protected static ?string $title = 'Ledger entries';

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('type')
                    ->badge(),
                TextEntry::make('amount_cents')
                    ->numeric(),
                TextEntry::make('idempotency_key'),
                KeyValueEntry::make('metadata')
                    ->columnSpanFull()
                    ->state(function ($record): array {
                        $metadata = $record->metadata;

                        return is_array($metadata) ? $metadata : [];
                    }),
                TextEntry::make('payout.id')
                    ->label('Payout')
                    ->placeholder('-'),
                TextEntry::make('subscriptionPeriod.id')
                    ->label('Subscription period')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('idempotency_key')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('amount_cents')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('idempotency_key')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->idempotency_key),
                TextColumn::make('metadata')
                    ->formatStateUsing(function (mixed $state): string {
                        if ($state === null || $state === '' || $state === []) {
                            return '—';
                        }

                        if (is_array($state)) {
                            return json_encode($state, JSON_UNESCAPED_SLASHES) ?: '—';
                        }

                        if (is_string($state)) {
                            return $state;
                        }

                        return (string) json_encode($state, JSON_UNESCAPED_SLASHES);
                    })
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(LedgerEntryType::class),
            ])
            ->headerActions([])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
