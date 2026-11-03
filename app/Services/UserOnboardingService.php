<?php

namespace App\Services;

use App\Mail\WelcomeUserMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class UserOnboardingService
{
    public function __construct(private AuditService $audit) {}

    public function generateTemporaryPassword(int $length = 14): string
    {
        // Avoid ambiguous characters; meet Password::defaults() complexity.
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnopqrstuvwxyz';
        $digits = '23456789';
        $symbols = '!@#$%&*';

        $password = $upper[random_int(0, strlen($upper) - 1)]
            .$lower[random_int(0, strlen($lower) - 1)]
            .$digits[random_int(0, strlen($digits) - 1)]
            .$symbols[random_int(0, strlen($symbols) - 1)];

        $all = $upper.$lower.$digits.$symbols;
        for ($i = strlen($password); $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle($password);
    }

    /**
     * Apply a temporary password and require change on next login.
     * Returns the plaintext password for one-time email delivery only.
     */
    public function assignTemporaryPassword(User $user, ?string $plainPassword = null): string
    {
        $plain = $plainPassword ?: $this->generateTemporaryPassword();

        $user->forceFill([
            'password' => Hash::make($plain),
            'must_change_password' => true,
            'password_changed_at' => null,
            'remember_token' => Str::random(60),
        ])->save();

        return $plain;
    }

    /**
     * Send welcome / temporary-password email immediately. Never throws to callers.
     * sendNow skips the queue even if the mailable is later marked ShouldQueue —
     * this host has no always-on worker, and the temp password must not sit in jobs.
     */
    public function sendWelcomeEmail(User $user, string $temporaryPassword, ?User $actor = null): bool
    {
        $mailable = new WelcomeUserMail($user, $temporaryPassword);

        if ($mailable instanceof ShouldQueue) {
            Log::error('[mail] Welcome email mailable implements ShouldQueue. Sending synchronously anyway so it is not left in the jobs table.', [
                'user_id' => $user->id,
            ]);
        }

        try {
            Mail::to($user->email)->sendNow($mailable);

            $this->audit->log('welcome_email_sent', $actor, $user, null, null, [
                'email' => $user->email,
                'mailer' => config('mail.default'),
                'from' => config('mail.from.address'),
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('[mail] Welcome email failed', [
                'user_id' => $user->id,
                'email_hash' => hash('sha256', strtolower((string) $user->email)),
                'mailer' => config('mail.default'),
                'from' => config('mail.from.address'),
                'sendmail_path' => config('mail.mailers.sendmail.path'),
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            $this->audit->log('welcome_email_failed', $actor, $user, null, null, null, [
                'error' => $e->getMessage(),
                'mailer' => config('mail.default'),
                'exception' => $e::class,
            ]);

            return false;
        }
    }

    public function markPasswordChanged(User $user): void
    {
        $user->forceFill([
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();
    }
}
