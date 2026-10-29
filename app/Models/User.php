<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

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

    public function primaryRoleName(): ?string
    {
        return $this->roles->first()?->name;
    }

    public function roleLabel(): string
    {
        return match ($this->primaryRoleName()) {
            'admin' => 'System Administrator',
            'md_ceo' => 'MD / CEO',
            'supervisor' => 'Supervisor / Head of Operations',
            'ft_officer' => 'Fund Transfer Officer',
            'submitter' => 'Branch Staff / Submitter',
            'auditor' => 'Auditor (Read-only)',
            default => 'User',
        };
    }
}
