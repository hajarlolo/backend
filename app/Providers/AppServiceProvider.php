<?php

namespace App\Providers;

use App\Helpers\UniversityHelper;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UniversityHelper::class, fn () => new UniversityHelper());
    }

    public function boot(): void
    {
        // Force root URL from config for proper signed URL generation
        $appUrl = (string) config('app.url');
        if ($appUrl !== '') {
            URL::forceRootUrl($appUrl);
            $scheme = parse_url($appUrl, PHP_URL_SCHEME);
            if (is_string($scheme) && $scheme !== '') {
                URL::forceScheme($scheme);
            }
        }

        // Register the standard Laravel email verification listener
        // This sends verification email when Registered event is fired
        Event::listen(Registered::class, SendEmailVerificationNotification::class);
    }
}
