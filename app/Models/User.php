<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN = 'admin';

    /**
     * Roles an Admin may assign. Super Admin and Admin are intentionally absent.
     *
     * @var list<string>
     */
    public const OPERATIONAL_ROLES = [
        'md_ceo',
        'supervisor',
        'ft_officer',
        'submitter',
        'auditor',
    ];

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'staff_id',
        'branch_id',
        'phone',
        'is_active',
        'is_demo',
        'must_change_password',
        'password_changed_at',
        'last_login_at',
        'failed_login_attempts',
        'locked_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function submittedTickets(): HasMany
    {
        return $this->hasMany(TransferTicket::class, 'submitter_id');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(TransferTicket::class, 'assigned_officer_id');
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    /**
     * Account-administration role. Views tickets but does not operate them.
     * Super Admin is not view-only; they keep the previous system-admin powers.
     */
    public function isViewOnlyAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN) && ! $this->isSuperAdmin();
    }

    public function primaryRoleName(): ?string
    {
        return $this->roles->first()?->name;
    }

    /**
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        return [
            self::ROLE_SUPER_ADMIN => 'Super Admin',
            self::ROLE_ADMIN => 'Admin',
            'md_ceo' => 'MD / CEO',
            'supervisor' => 'Supervisor / Head of Operations',
            'ft_officer' => 'Fund Transfer Officer',
            'submitter' => 'Branch Staff / Submitter',
            'auditor' => 'Auditor (Read-only)',
        ];
    }

    public function roleLabel(): string
    {
        return self::roleOptions()[$this->primaryRoleName()] ?? 'User';
    }

    /**
     * @return list<string>
     */
    public function assignableRoles(): array
    {
        if ($this->isSuperAdmin()) {
            return array_merge([self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN], self::OPERATIONAL_ROLES);
        }

        if ($this->hasRole(self::ROLE_ADMIN)) {
            return self::OPERATIONAL_ROLES;
        }

        return [];
    }

    public function canManageUser(User $target): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->hasRole(self::ROLE_ADMIN)) {
            return false;
        }

        return ! $target->isSuperAdmin();
    }

    public function canDeactivateUser(User $target): bool
    {
        if (! $this->canManageUser($target)) {
            return false;
        }

        if ($this->isViewOnlyAdmin() && $this->id === $target->id) {
            return false;
        }

        return true;
    }

    public function canResendWelcomeTo(User $target): bool
    {
        if (! $this->canManageUser($target)) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        return ! $target->hasRole(self::ROLE_ADMIN);
    }

    /**
     * Roles this actor may set on $target. An Admin cannot promote anyone
     * to Admin or Super Admin, and cannot change an existing Admin's role.
     *
     * @return list<string>
     */
    public function rolesAssignableTo(User $target): array
    {
        if ($this->isSuperAdmin()) {
            return $this->assignableRoles();
        }

        if ($this->isViewOnlyAdmin() && $target->hasRole(self::ROLE_ADMIN)) {
            return [self::ROLE_ADMIN];
        }

        return $this->assignableRoles();
    }
}
