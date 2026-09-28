<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayoutBatches;

use App\Filament\Concerns\GatesWithSpatiePermissions;
use App\Filament\Resources\PayoutBatches\Pages\ListPayoutBatches;
use App\Filament\Resources\PayoutBatches\Pages\ViewPayoutBatch;
use App\Filament\Resources\PayoutBatches\RelationManagers\PayoutsRelationManager;
use App\Filament\Resources\PayoutBatches\Schemas\PayoutBatchInfolist;
use App\Filament\Resources\PayoutBatches\Tables\PayoutBatchesTable;
use App\Models\PayoutBatch;
use App\Support\Authorization\PermissionName;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PayoutBatchResource extends Resource
{
    use GatesWithSpatiePermissions;

    protected static ?string $model = PayoutBatch::class;

    protected static ?string $recordTitleAttribute = 'batch_uuid';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Payout batches';

    protected static ?int $navigationSort = 3;

    public static function infolist(Schema $schema): Schema
    {
        return PayoutBatchInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayoutBatchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PayoutsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayoutBatches::route('/'),
            'view' => ViewPayoutBatch::route('/{record}'),
        ];
    }

    protected static function viewAnyPermission(): string
    {
        return PermissionName::PayoutBatchesViewAny;
    }

    protected static function viewPermission(): string
    {
        return PermissionName::PayoutBatchesView;
    }
}
