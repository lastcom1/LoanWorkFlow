<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\InstallerGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class InstallerController extends Controller
{
    public function __construct(private InstallerGuard $guard) {}

    public function welcome(Request $request)
    {
        $this->guard->assertCanRun($request);

        return view('installer.welcome');
    }

    public function requirements(Request $request)
    {
        $this->guard->assertCanRun($request);

        $checks = [
            'PHP >= 8.2' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'PDO' => extension_loaded('pdo'),
            'Mbstring' => extension_loaded('mbstring'),
            'OpenSSL' => extension_loaded('openssl'),
            'Tokenizer' => extension_loaded('tokenizer'),
            'XML' => extension_loaded('xml'),
            'Ctype' => extension_loaded('ctype'),
            'JSON' => extension_loaded('json'),
            'BCMath' => extension_loaded('bcmath'),
            'Fileinfo' => extension_loaded('fileinfo'),
            'storage writable' => is_writable(storage_path()),
            'bootstrap/cache writable' => is_writable(base_path('bootstrap/cache')),
        ];

        $structure = [
            'artisan' => File::exists(base_path('artisan')),
            'public/index.php' => File::exists(public_path('index.php')),
            'vendor/autoload.php' => File::exists(base_path('vendor/autoload.php')),
            '.env.example' => File::exists(base_path('.env.example')),
        ];

        return view('installer.requirements', compact('checks', 'structure'));
    }

    public function showEnv(Request $request)
    {
        $this->guard->assertCanRun($request);
        $this->guard->assertCanWriteEnv();

        return view('installer.env', [
            'envExists' => File::exists(base_path('.env')),
            'envConfigured' => $this->guard->envLooksConfigured(),
        ]);
    }

    public function saveEnv(Request $request)
    {
        $this->guard->assertCanRun($request);
        $this->guard->assertCanWriteEnv();

        // Never rewrite an already-keyed production .env via the web installer.
        if ($this->guard->envLooksConfigured()) {
            return redirect()->route('installer.migrate')
                ->with('status', 'Existing .env left unchanged. Continue with migrations.');
        }

        $data = $request->validate([
            'app_url' => ['required', 'url'],
            'db_host' => ['required', 'string'],
            'db_port' => ['required', 'string'],
            'db_database' => ['required', 'string'],
            'db_username' => ['required', 'string'],
            'db_password' => ['nullable', 'string'],
            'mail_host' => ['required', 'string'],
            'mail_port' => ['required', 'string'],
            'mail_username' => ['required', 'string'],
            'mail_password' => ['required', 'string'],
            'mail_from_address' => ['required', 'email'],
            'mail_from_name' => ['required', 'string'],
        ]);

        try {
            $pdo = new \PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s', $data['db_host'], $data['db_port'], $data['db_database']),
                $data['db_username'],
                $data['db_password'] ?? '',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            unset($pdo);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['db_database' => 'Database connection failed: '.$e->getMessage()]);
        }

        if (! File::exists(base_path('.env'))) {
            File::copy(base_path('.env.example'), base_path('.env'));
        }

        $this->writeEnv([
            'APP_NAME' => '"NNMFB Transfer Portal"',
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'INSTALLER_ENABLED' => 'true',
            'APP_URL' => $data['app_url'],
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $data['db_host'],
            'DB_PORT' => $data['db_port'],
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'],
            'DB_PASSWORD' => '"'.addcslashes($data['db_password'] ?? '', '"\\').'"',
            'MAIL_MAILER' => 'smtp',
            'MAIL_HOST' => $data['mail_host'],
            'MAIL_PORT' => $data['mail_port'],
            'MAIL_USERNAME' => $data['mail_username'],
            'MAIL_PASSWORD' => '"'.addcslashes($data['mail_password'], '"\\').'"',
            'MAIL_ENCRYPTION' => 'tls',
            'MAIL_FROM_ADDRESS' => $data['mail_from_address'],
            'MAIL_FROM_NAME' => '"'.addcslashes($data['mail_from_name'], '"\\').'"',
        ]);

        // Generate key only when missing.
        $env = File::get(base_path('.env'));
        if (! preg_match('/^APP_KEY=base64:.+/m', $env)) {
            Artisan::call('key:generate', ['--force' => true]);
        }

        return redirect()->route('installer.migrate');
    }

    public function showMigrate(Request $request)
    {
        $this->guard->assertCanRun($request);

        return view('installer.migrate');
    }

    public function runMigrate(Request $request)
    {
        $this->guard->assertCanRun($request);

        try {
            Artisan::call('migrate', ['--force' => true]);
            // Reference data only — never demo users in installer path.
            Artisan::call('db:seed', ['--force' => true, '--class' => 'Database\\Seeders\\ProductionSeeder']);
        } catch (\Throwable $e) {
            return back()->withErrors(['migrate' => 'Migration failed: '.$e->getMessage()]);
        }

        return redirect()->route('installer.admin');
    }

    public function showAdmin(Request $request)
    {
        $this->guard->assertCanRun($request);

        return view('installer.admin');
    }

    public function saveAdmin(Request $request)
    {
        $this->guard->assertCanRun($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (User::query()->where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email already exists. Installer will not modify existing users.',
            ]);
        }

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
            'is_demo' => false,
        ]);
        $user->assignRole('admin');

        $this->guard->lock($user->email, 'web_installer');
        $this->disableInstallerInEnv();

        return redirect()->route('installer.complete');
    }

    public function complete()
    {
        return view('installer.complete', [
            'locked' => $this->guard->isLocked(),
        ]);
    }

    private function disableInstallerInEnv(): void
    {
        if (! File::exists(base_path('.env'))) {
            return;
        }

        $this->writeEnv([
            'INSTALLER_ENABLED' => 'false',
        ]);
    }

    private function writeEnv(array $values): void
    {
        $envPath = base_path('.env');
        $content = File::get($envPath);

        foreach ($values as $key => $value) {
            if (preg_match("/^{$key}=.*/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", $key.'='.$value, $content);
            } else {
                $content .= PHP_EOL.$key.'='.$value;
            }
        }

        File::put($envPath, $content);
    }
}
