<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Public entry for public_html/transfer
|--------------------------------------------------------------------------
|
| Application code lives OUTSIDE the web root, e.g.:
|   /home/USER/nnmfb-portal/
| This file lives at:
|   /home/USER/public_html/transfer/index.php
|
| Adjust $appRoot if your paths differ.
*/

$appRoot = getenv('NNMFB_APP_ROOT') ?: dirname(__DIR__, 2).'/nnmfb-portal';

if (! is_dir($appRoot) || ! is_file($appRoot.'/vendor/autoload.php')) {
    // Fallback for local packaging verification: sibling ../nnmfb-portal
    $alt = dirname(__DIR__).'/nnmfb-portal';
    if (is_file($alt.'/vendor/autoload.php')) {
        $appRoot = $alt;
    }
}

if (file_exists($maintenance = $appRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appRoot.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appRoot.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
