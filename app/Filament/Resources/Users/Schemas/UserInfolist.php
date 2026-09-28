<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('email')->label('Email address'),
                        IconEntry::make('is_instructor')->boolean(),
                        TextEntry::make('created_at')->dateTime(),
                    ])
                    ->columns(2),
                Section::make('Roles & permissions')
                    ->schema([
                        TextEntry::make('roles.name')
                            ->label('Roles')
                            ->badge()
                            ->separator(','),
                        TextEntry::make('permissions.name')
                            ->label('Direct permissions')
                            ->badge()
                            ->separator(',')
                            ->placeholder('None'),
                    ]),
            ]);
    }
}
