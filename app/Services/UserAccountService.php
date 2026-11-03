<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserAccountService
{
    public const DEACTIVATED_LOGIN_MESSAGE = 'Your account has been deactivated. Contact an administrator.';

    public function __construct(private AuditService $audit) {}

    /**
     * Mark the account inactive, invalidate remember-me, database sessions,
     * and outstanding password-reset tokens.
     */
    public function deactivate(User $user, ?User $actor = null): void
    {
        $this->revokeAccess($user);

        $this->audit->log('user_deactivated', $actor, $user, null, null, [
            'email' => $user->email,
            'is_active' => false,
        ]);
    }

    public function activate(User $user, ?User $actor = null): void
    {
        $user->forceFill(['is_active' => true])->save();

        $this->audit->log('user_activated', $actor, $user, null, null, [
            'email' => $user->email,
            'is_active' => true,
        ]);
    }

    /**
     * Kill existing credentials for an inactive account without a second audit row.
     * Safe to call when is_active was set with a raw SQL update.
     */
    public function revokeAccess(User $user): void
    {
        $user->forceFill([
            'is_active' => false,
            'remember_token' => Str::random(60),
        ])->save();

        $this->deleteSessions($user);
        $this->deletePasswordResets($user);
    }

    public function deleteSessions(User $user): void
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }

        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    public function deletePasswordResets(User $user): void
    {
        if (! Schema::hasTable('password_reset_tokens')) {
            return;
        }

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
    }
}
