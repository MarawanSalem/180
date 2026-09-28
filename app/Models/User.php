<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Authorization\PermissionName;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_instructor'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_instructor' => 'boolean',
        ];
    }

    /**
     * Filament admin panel access is gated by Spatie permission admin.access.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->can(PermissionName::AdminAccess);
    }

    // ==============================
    // Relationships
    // ==============================

    /**
     * The subscriptions purchased by this user (student context).
     *
     * @return HasMany<Subscription>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Ledger entries belonging to this instructor.
     * The balance at any point in time equals SUM(amount_cents) across these entries.
     *
     * @return HasMany<InstructorLedgerEntry>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(InstructorLedgerEntry::class, 'instructor_id');
    }

    /**
     * Payout records received by this instructor.
     *
     * @return HasMany<Payout>
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'instructor_id');
    }

    // ==============================
    // Accessors & Mutators
    // ==============================

    /**
     * Dynamically compute the instructor's current available balance in cents.
     * This is never stored — it is always calculated from the append-only ledger.
     */
    public function getBalanceCentsAttribute(): int
    {
        return (int) $this->ledgerEntries()->sum('amount_cents');
    }

    // ==============================
    // Scopes
    // ==============================

    // ==============================
    // Events & Observers
    // ==============================

    // ==============================
    // Custom Methods
    // ==============================
}
