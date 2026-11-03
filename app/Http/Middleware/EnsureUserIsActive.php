<?php

namespace App\Http\Middleware;

use App\Services\UserAccountService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            app(UserAccountService::class)->revokeAccess($user);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => UserAccountService::DEACTIVATED_LOGIN_MESSAGE,
            ]);
        }

        if ($user && $user->isLocked()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your account is temporarily locked due to failed login attempts.',
            ]);
        }

        return $next($request);
    }
}
