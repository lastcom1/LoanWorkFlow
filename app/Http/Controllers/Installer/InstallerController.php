<?php

namespace App\Http\Controllers\Installer;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class InstallerController extends Controller
{
    private function lockPath(): string
    {
        return storage_path('app/installed.lock');
    }

    private function ensureNotInstalled(): void
    {
        if (File::exists($this->lockPath())) {
            abort(403, 'Installer is disabled. Application already installed.');
        }
    }

    public function welcome()
    {
        $this->ensureNotInstalled();

        return view('installer.welcome');
    }

    public function requirements()
    {
        $this->ensureNotInstalled();

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

    public function showEnv()
    {
        $this->ensureNotInstalled();

        return view('installer.env');
    }

    public function saveEnv(Request $request)
    {
        $this->ensureNotInstalled();

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

        Artisan::call('key:generate', ['--force' => true]);

        return redirect()->route('installer.migrate');
    }

    public function showMigrate()
    {
        $this->ensureNotInstalled();

        return view('installer.migrate');
    }

    public function runMigrate(Request $request)
    {
        $this->ensureNotInstalled();

        try {
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true, '--class' => 'Database\\Seeders\\RoleSeeder']);
            Artisan::call('db:seed', ['--force' => true, '--class' => 'Database\\Seeders\\BranchSeeder']);
        } catch (\Throwable $e) {
            return back()->withErrors(['migrate' => 'Migration failed: '.$e->getMessage()]);
        }

        return redirect()->route('installer.admin');
    }

    public function showAdmin()
    {
        $this->ensureNotInstalled();

        return view('installer.admin');
    }

    public function saveAdmin(Request $request)
    {
        $this->ensureNotInstalled();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::query()->updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'password' => Hash::make($data['password']),
                'is_active' => true,
                'is_demo' => false,
            ]
        );
        $user->syncRoles(['admin']);

        File::put($this->lockPath(), json_encode([
            'installed_at' => now()->toIso8601String(),
            'installed_by_email' => $user->email,
        ], JSON_PRETTY_PRINT));

        return redirect()->route('installer.complete');
    }

    public function complete()
    {
        return view('installer.complete', [
            'locked' => File::exists($this->lockPath()),
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
