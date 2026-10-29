<?php

return [
    /*
    | Web installer is OFF by default. Enable temporarily with a one-time secret:
    | INSTALLER_ENABLED=true
    | INSTALLER_SETUP_SECRET=long-random-value
    | Then visit /install?setup_secret=... and run php artisan installer:lock when done.
    */
    'enabled' => (bool) filter_var(env('INSTALLER_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'setup_secret' => env('INSTALLER_SETUP_SECRET'),
];
