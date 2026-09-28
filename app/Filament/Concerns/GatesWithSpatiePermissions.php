<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Support\Authorization\PermissionName;
use Illuminate\Database\Eloquent\Model;

trait GatesWithSpatiePermissions
{
    public static function canViewAny(): bool
    {
        return static::userCan(static::viewAnyPermission());
    }

    public static function canView(Model $record): bool
    {
        return static::userCan(static::viewPermission());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    abstract protected static function viewAnyPermission(): string;

    abstract protected static function viewPermission(): string;

    protected static function userCan(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can($permission);
    }
}
