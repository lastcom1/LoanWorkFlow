<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class ForgotPasswordController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function show()
    {
        return view('auth.forgot-password');
    }

    public function send(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($request->string('email')->toString()));
        $user = \App\Models\User::query()->where('email', $email)->first();

        $generic = 'If that email is registered and eligible, a password reset link has been sent. Check your inbox (and spam). The link expires in 60 minutes.';

        // Only attempt send for active, unlocked accounts — never reveal why.
        if ($user && $user->is_active && ! $user->isLocked()) {
            try {
                $status = Password::broker()->sendResetLink(['email' => $email]);
            } catch (TransportExceptionInterface $e) {
                return $this->mailFailureResponse($generic, $email, $user, $e);
            } catch (Throwable $e) {
                // Defensive: any unexpected failure while sending must not 500 or enumerate.
                return $this->mailFailureResponse($generic, $email, $user, $e);
            }

            $this->audit->log(
                $status === Password::RESET_LINK_SENT ? 'password_reset_requested' : 'password_reset_request_failed',
                $user,
                $user,
                null,
                null,
                null,
                ['status' => $status]
            );

            // Throttle is the only user-visible differentiation (rate limit, not enumeration).
            if ($status === Password::RESET_THROTTLED) {
                throw ValidationException::withMessages([
                    'email' => 'Please wait before requesting another reset link.',
                ]);
            }
        } elseif ($user) {
            $this->audit->log('password_reset_request_skipped', $user, $user, null, null, null, [
                'reason' => 'inactive_or_locked',
            ]);
        }

        return back()->with('status', $generic);
    }

    private function mailFailureResponse(
        string $generic,
        string $email,
        \App\Models\User $user,
        Throwable $e
    ) {
        Log::warning('Password reset mail transport failed', [
            'email_hash' => hash('sha256', $email),
            'error' => $e->getMessage(),
        ]);

        $this->audit->log('password_reset_mail_failed', $user, $user, null, null, null, [
            'error' => $e->getMessage(),
        ]);

        return back()->with('status', $generic);
    }
}
