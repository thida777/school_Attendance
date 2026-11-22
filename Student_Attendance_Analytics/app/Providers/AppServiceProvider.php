<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Sharing the site through a public link (e.g. a Cloudflare tunnel for team testing):
        // start the server with PUBLIC_URL=https://… so every link, image and script uses that address.
        if ($publicUrl = env('PUBLIC_URL')) {
            URL::forceRootUrl($publicUrl);
            if (str_starts_with($publicUrl, 'https://')) {
                URL::forceScheme('https');
            }
        }
    }
}
