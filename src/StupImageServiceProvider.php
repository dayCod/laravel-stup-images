<?php

declare(strict_types=1);

namespace Daycode\StupImage;

use Daycode\StupImage\Commands\InstallCommand;
use Daycode\StupImage\Contracts\ImageProcessor;
use Daycode\StupImage\Processors\InterventionProcessor;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class StupImageServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('stup-image')
            ->hasConfigFile()
            ->hasCommand(InstallCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->bind(ImageProcessor::class, fn (Application $app): ImageProcessor => new InterventionProcessor(
            $app->make(StupImageManager::class)->configString('driver') ?? 'gd',
        ));

        // Scoped: a fresh instance per request / job, which keeps it safe under Octane.
        $this->app->scoped(StupImageManager::class, fn (Application $app): StupImageManager => new StupImageManager($app));
        $this->app->alias(StupImageManager::class, 'stup-image');
    }

    public function packageBooted(): void
    {
        // The v1 publish tag, kept for backwards compatibility.
        $this->publishes([
            __DIR__.'/../config/stup-image.php' => config_path('stup-image.php'),
        ], 'stup-image');

        if (! UploadedFile::hasMacro('storeImage')) {
            UploadedFile::macro('storeImage', function (?string $directory = null, ?string $disk = null): StoredImage {
                /** @var UploadedFile $this */
                return app(StupImageManager::class)
                    ->from($this)
                    ->when($directory !== null, fn (ImageUpload $upload) => $upload->directory((string) $directory))
                    ->when($disk !== null, fn (ImageUpload $upload) => $upload->disk((string) $disk))
                    ->store();
            });
        }
    }
}
