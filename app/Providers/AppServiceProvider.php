<?php

namespace App\Providers;

use App\Listeners\EnforceLocalMailEnvelope;
use App\Services\LocalSendmailConfigurator;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl !== '') {
            URL::forceRootUrl($appUrl);
        }

        $scheme = parse_url($appUrl, PHP_URL_SCHEME);
        if ($scheme === 'https' || $this->app->environment('production')) {
            URL::forceScheme('https');
        }

        app(LocalSendmailConfigurator::class)->apply();
        Event::listen(MessageSending::class, EnforceLocalMailEnvelope::class);
    }
}
