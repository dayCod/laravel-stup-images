<?php

declare(strict_types=1);

namespace Daycode\StupImage;

use Illuminate\Support\ServiceProvider;

class StupImageServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            path: __DIR__ . '/config/stup-image.php',
            key: 'stup-image'
        );
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes(
            paths: [
                __DIR__ . '/config/stup-image.php' => config_path('stup-image.php'),
            ],
            groups: 'stup-image'
        );
    }
}
