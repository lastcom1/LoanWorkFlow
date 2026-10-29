<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\UserOnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ForcePasswordChangeController extends Controller
{
    public function __construct(
        private UserOnboardingService $onboarding,
        private AuditService $audit,
    ) {}

    public function show(Request $request)
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('dashboard');
        }

        return view('auth.force-change-password');
    }

    public function update(Request $request)
    {
        $user = $request->user();

        if (! $user->must_change_password) {
            return redirect()->route('dashboard');
        }

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'New password must be different from the temporary password.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        $this->onboarding->markPasswordChanged($user);

        $this->audit->log('password_changed_forced', $user, $user);

        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Password updated. You can continue using the portal.');
    }
}
