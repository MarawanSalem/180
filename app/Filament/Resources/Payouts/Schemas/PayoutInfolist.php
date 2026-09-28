<?php

namespace App\Filament\Resources\Payouts\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PayoutInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('batch.batch_uuid')
                    ->label('Batch UUID'),
                TextEntry::make('instructor.name')
                    ->label('Instructor'),
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
}
