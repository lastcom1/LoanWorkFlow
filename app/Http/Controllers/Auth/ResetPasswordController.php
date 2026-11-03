<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\UserAccountService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class ResetPasswordController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private UserAccountService $accounts,
    ) {}

    public function show(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $account = User::query()->where('email', $request->string('email')->toString())->first();
        if ($account && ! $account->is_active) {
            $this->accounts->deletePasswordResets($account);
            $this->audit->log('password_reset_blocked_deactivated', $account, $account);

            throw ValidationException::withMessages([
                'email' => UserAccountService::DEACTIVATED_LOGIN_MESSAGE,
            ]);
        }

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                if (! $user->is_active) {
                    $this->accounts->deletePasswordResets($user);

                    throw ValidationException::withMessages([
                        'email' => UserAccountService::DEACTIVATED_LOGIN_MESSAGE,
                    ]);
                }

                $user->forceFill([
                    'password' => Hash::make($request->string('password')->toString()),
                    'remember_token' => Str::random(60),
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ])->save();

                event(new PasswordReset($user));

                $this->audit->log('password_reset_completed', $user, $user);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return redirect()->route('login')->with('status', 'Your password has been reset. Sign in with your new password.');
    }
}
