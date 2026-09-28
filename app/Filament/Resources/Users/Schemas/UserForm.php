<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Services\UserAdminService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var UserAdminService $adminUsers */
        $adminUsers = app(UserAdminService::class);

        return $schema
            ->components([
                Section::make('Profile')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create'),
                        Toggle::make('is_instructor')
                            ->label('Instructor account'),
                    ])
                    ->columns(2),
                Section::make('Roles & permissions')
                    ->description('Validated via UserAdminService rule sets (Spatie). Direct permissions are additive on top of roles.')
                    ->schema([
                        CheckboxList::make('roles')
                            ->options($adminUsers->roleOptions())
                            ->columns(2)
                            ->bulkToggleable(),
                        CheckboxList::make('permissions')
                            ->label('Direct permissions')
                            ->options($adminUsers->permissionOptions())
                            ->columns(2)
                            ->bulkToggleable()
                            ->searchable(),
                    ]),
            ]);
    }
}
