<?php

namespace App\Filament\Resources\PayoutBatches\Pages;

use App\Filament\Resources\PayoutBatches\PayoutBatchResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPayoutBatch extends ViewRecord
{
    protected static string $resource = PayoutBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
