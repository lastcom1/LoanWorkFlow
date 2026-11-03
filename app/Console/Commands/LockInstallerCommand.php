<?php

namespace App\Console\Commands;

use App\Services\InstallerGuard;
use Illuminate\Console\Command;

class LockInstallerCommand extends Command
{
    protected $signature = 'installer:lock {--email= : Optional email recorded in the lock file}';

    protected $description = 'Disable the web installer by writing storage/app/installed.lock';

    public function handle(InstallerGuard $guard): int
    {
        $guard->lock($this->option('email'), 'artisan_installer_lock');
        $this->info('Installer locked at '.$guard->lockPath());

        return self::SUCCESS;
    }
}
