<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payouts;

use App\Filament\Concerns\GatesWithSpatiePermissions;
use App\Filament\Resources\Payouts\Pages\ListPayouts;
use App\Filament\Resources\Payouts\Pages\ViewPayout;
use App\Filament\Resources\Payouts\Schemas\PayoutInfolist;
use App\Filament\Resources\Payouts\Tables\PayoutsTable;
use App\Models\Payout;
use App\Support\Authorization\PermissionName;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PayoutResource extends Resource
{
    use GatesWithSpatiePermissions;

    protected static ?string $model = Payout::class;

    protected static ?string $recordTitleAttribute = 'idempotency_key';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Payouts';

    protected static ?int $navigationSort = 2;

    public static function infolist(Schema $schema): Schema
    {
        return PayoutInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayoutsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayouts::route('/'),
            'view' => ViewPayout::route('/{record}'),
        ];
    }

    protected static function viewAnyPermission(): string
    {
        return PermissionName::PayoutsViewAny;
    }

    protected static function viewPermission(): string
    {
        return PermissionName::PayoutsView;
    }
}
