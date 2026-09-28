<?php

declare(strict_types=1);

namespace App\Filament\Resources\InstructorBalances;

use App\Filament\Concerns\GatesWithSpatiePermissions;
use App\Filament\Resources\InstructorBalances\Pages\ListInstructorBalances;
use App\Filament\Resources\InstructorBalances\Pages\ViewInstructorBalance;
use App\Filament\Resources\InstructorBalances\RelationManagers\LedgerEntriesRelationManager;
use App\Filament\Resources\InstructorBalances\RelationManagers\PayoutsRelationManager;
use App\Filament\Resources\InstructorBalances\Schemas\InstructorBalanceInfolist;
use App\Filament\Resources\InstructorBalances\Tables\InstructorBalancesTable;
use App\Models\User;
use App\Support\Authorization\PermissionName;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class InstructorBalanceResource extends Resource
{
    use GatesWithSpatiePermissions;

    protected static ?string $model = User::class;

    protected static ?string $slug = 'instructor-balances';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Instructor balances';

    protected static ?string $modelLabel = 'instructor balance';

    protected static ?string $pluralModelLabel = 'instructor balances';

    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $schema): Schema
    {
        return InstructorBalanceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstructorBalancesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_instructor', true)
            ->withSum('ledgerEntries', 'amount_cents');
    }

    public static function getRelations(): array
    {
        return [
            LedgerEntriesRelationManager::class,
            PayoutsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstructorBalances::route('/'),
            'view' => ViewInstructorBalance::route('/{record}'),
        ];
    }

    protected static function viewAnyPermission(): string
    {
        return PermissionName::InstructorsViewAny;
    }

    protected static function viewPermission(): string
    {
        return PermissionName::InstructorsView;
    }
}
