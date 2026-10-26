<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

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

        if ($user && (! $user->is_active || $user->isLocked())) {
            throw ValidationException::withMessages([
                'email' => 'This account cannot reset a password right now. Contact an administrator.',
            ]);
        }

        $status = Password::broker()->sendResetLink(['email' => $email]);

        if ($user) {
            $this->audit->log(
                $status === Password::RESET_LINK_SENT ? 'password_reset_requested' : 'password_reset_request_failed',
                $user,
                $user,
                null,
                null,
                null,
                ['status' => $status]
            );
        }

        if ($status !== Password::RESET_LINK_SENT) {
            // Generic message to avoid account enumeration where possible,
            // but still surface throttle clearly.
            if ($status === Password::RESET_THROTTLED) {
                throw ValidationException::withMessages([
                    'email' => 'Please wait before requesting another reset link.',
                ]);
            }

            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        return back()->with('status', 'If that email is registered, a password reset link has been sent. Check your inbox (and spam). The link expires in 60 minutes.');
    }
}
