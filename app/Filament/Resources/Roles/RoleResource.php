<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles;

use App\Filament\Concerns\GatesWithSpatiePermissions;
use App\Filament\Resources\Roles\Pages\ManageRoles;
use App\Services\UserAdminService;
use App\Support\Authorization\PermissionName;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;
use UnitEnum;

class RoleResource extends Resource
{
    use GatesWithSpatiePermissions;

    protected static ?string $model = Role::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'User management';

    protected static ?string $navigationLabel = 'Roles & permissions';

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Role')
                ->schema([
                    TextInput::make('name')->disabled()->dehydrated(),
                    CheckboxList::make('permissions')
                        ->options(fn () => app(UserAdminService::class)->permissionOptions())
                        ->columns(2)
                        ->bulkToggleable()
                        ->searchable(),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->badge(),
            TextEntry::make('permissions.name')
                ->label('Permissions')
                ->badge()
                ->separator(','),
            TextEntry::make('users_count')
                ->label('Assigned users')
                ->state(fn (Role $record): int => $record->users()->count()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->badge()->searchable()->sortable(),
                TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permissions'),
                TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, Role $record): array {
                        $data['permissions'] = $record->permissions->pluck('name')->all();

                        return $data;
                    })
                    ->using(fn (Role $record, array $data): Role => app(UserAdminService::class)->syncRolePermissions($record, $data))
                    ->visible(fn (): bool => auth()->user()?->can(PermissionName::RolesUpdate) ?? false),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRoles::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCan(PermissionName::RolesUpdate);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    protected static function viewAnyPermission(): string
    {
        return PermissionName::RolesViewAny;
    }

    protected static function viewPermission(): string
    {
        return PermissionName::RolesView;
    }
}
