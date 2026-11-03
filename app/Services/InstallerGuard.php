<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class InstallerGuard
{
    public function lockPath(): string
    {
        return storage_path('app/installed.lock');
    }

    public function isLocked(): bool
    {
        return File::exists($this->lockPath());
    }

    public function lock(?string $byEmail = null, string $source = 'installer'): void
    {
        File::ensureDirectoryExists(dirname($this->lockPath()));
        File::put($this->lockPath(), json_encode([
            'installed_at' => now()->toIso8601String(),
            'installed_by_email' => $byEmail,
            'source' => $source,
        ], JSON_PRETTY_PRINT));
    }

    public function isEnabled(): bool
    {
        if ($this->isLocked()) {
            return false;
        }

        return (bool) config('installer.enabled', false);
    }

    public function configuredSecret(): ?string
    {
        $secret = config('installer.setup_secret');

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    public function secretAuthorized(Request $request): bool
    {
        $expected = $this->configuredSecret();
        if ($expected === null) {
            return false;
        }

        $provided = (string) (
            $request->session()->get('installer_setup_secret')
            ?? $request->query('setup_secret')
            ?? $request->input('setup_secret')
            ?? ''
        );

        return hash_equals($expected, $provided);
    }

    public function authorizeRequest(Request $request): void
    {
        $incoming = $request->input('setup_secret') ?: $request->query('setup_secret');
        if (is_string($incoming) && $incoming !== '' && $this->configuredSecret() !== null) {
            if (hash_equals($this->configuredSecret(), $incoming)) {
                $request->session()->put('installer_setup_secret', $incoming);
            }
        }
    }

    public function envLooksConfigured(): bool
    {
        if (! File::exists(base_path('.env'))) {
            return false;
        }

        $content = File::get(base_path('.env'));

        return (bool) preg_match('/^APP_KEY=base64:.+/m', $content)
            || (bool) preg_match('/^APP_KEY=[A-Za-z0-9\/+=]{16,}/m', $content);
    }

    public function usersExist(): bool
    {
        try {
            if (! Schema::hasTable('users')) {
                return false;
            }

            return User::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    public function assertCanRun(Request $request): void
    {
        if ($this->isLocked()) {
            abort(403, 'Installer is disabled. Application already installed.');
        }

        if (! $this->isEnabled()) {
            abort(403, 'Installer is disabled. Set INSTALLER_ENABLED=true and INSTALLER_SETUP_SECRET in .env to run setup, then lock again.');
        }

        $this->authorizeRequest($request);

        if (! $this->secretAuthorized($request)) {
            abort(403, 'Installer setup secret required. Open /install?setup_secret=YOUR_SECRET');
        }

        if ($this->usersExist()) {
            $this->lock(null, 'auto_lock_existing_users');
            abort(403, 'Installer refused: users already exist. Installer has been locked.');
        }
    }

    public function assertCanWriteEnv(): void
    {
        if ($this->envLooksConfigured() && $this->usersExist()) {
            $this->lock(null, 'auto_lock_configured_env');
            abort(403, 'Installer refused: application .env is already configured.');
        }
    }
}
