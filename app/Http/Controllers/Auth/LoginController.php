<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\UserAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private UserAccountService $accounts,
    ) {}

    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.strtolower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Try again in {$seconds} seconds.",
            ]);
        }

        $user = User::query()->where('email', $credentials['email'])->first();

        // Check before Auth::attempt so a deactivated account never receives a
        // session or a remember-me cookie. Wrong passwords stay generic.
        if ($user && ! $user->is_active && Hash::check($credentials['password'], $user->password)) {
            $this->accounts->revokeAccess($user);
            $this->audit->log('login_blocked_deactivated', $user, $user);

            throw ValidationException::withMessages([
                'email' => UserAccountService::DEACTIVATED_LOGIN_MESSAGE,
            ]);
        }

        if ($user && $user->isLocked()) {
            throw ValidationException::withMessages([
                'email' => 'Account temporarily locked. Try again later or contact an administrator.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 300);

            if ($user) {
                $user->increment('failed_login_attempts');
                if ($user->failed_login_attempts >= 5) {
                    $user->forceFill(['locked_until' => now()->addMinutes(15)])->save();
                }
                $this->audit->log('login_failed', $user, $user, null, null, null, [
                    'email' => $credentials['email'],
                ]);
            }

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        /** @var User $authUser */
        $authUser = Auth::user();

        if (! $authUser->is_active) {
            $this->accounts->revokeAccess($authUser);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => UserAccountService::DEACTIVATED_LOGIN_MESSAGE,
            ]);
        }

        $authUser->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
        ])->save();

        $this->audit->log('login_success', $authUser, $authUser);

        if ($authUser->must_change_password) {
            return redirect()->route('password.change');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $this->audit->log('logout', $user, $user);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
