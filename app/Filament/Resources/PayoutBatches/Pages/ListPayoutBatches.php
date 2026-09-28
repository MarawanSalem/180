<?php

namespace App\Filament\Resources\PayoutBatches\Pages;

use App\Filament\Resources\PayoutBatches\PayoutBatchResource;
use Filament\Resources\Pages\ListRecords;

class ListPayoutBatches extends ListRecords
{
    protected static string $resource = PayoutBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
