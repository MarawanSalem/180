<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Filament\Concerns\GatesWithSpatiePermissions;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use App\Support\Authorization\PermissionName;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class UserResource extends Resource
{
    use GatesWithSpatiePermissions;

    protected static ?string $model = User::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'User management';

    protected static ?string $navigationLabel = 'Users';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['roles', 'permissions']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return static::userCan(PermissionName::UsersCreate);
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCan(PermissionName::UsersUpdate);
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCan(PermissionName::UsersDelete);
    }

    public static function canDeleteAny(): bool
    {
        return static::userCan(PermissionName::UsersDelete);
    }

    protected static function viewAnyPermission(): string
    {
        return PermissionName::UsersViewAny;
    }

    protected static function viewPermission(): string
    {
        return PermissionName::UsersView;
    }
}
