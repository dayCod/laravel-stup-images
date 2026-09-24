<?php

declare(strict_types=1);

namespace Daycode\StupImage\Commands;

use Daycode\StupImage\StupImageManager;
use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    protected $signature = 'stup-image:install {--force : Overwrite an existing config file}';

    protected $description = 'Publish the Stup Image config file and check the image driver';

    public function handle(StupImageManager $manager): int
    {
        $this->callSilently('vendor:publish', [
            '--tag' => 'stup-image-config',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->components->info('Config file published to config/stup-image.php.');

        $drivers = [
            'gd' => extension_loaded('gd'),
            'imagick' => extension_loaded('imagick'),
        ];

        foreach ($drivers as $driver => $available) {
            $this->components->twoColumnDetail($driver, $available ? '<fg=green>available</>' : '<fg=yellow>not installed</>');
        }

        $configured = $manager->configString('driver') ?? 'gd';

        if (! array_key_exists($configured, $drivers)) {
            $this->components->error("Unsupported driver [{$configured}], use gd or imagick.");

            return self::FAILURE;
        }

        if (! $drivers[$configured]) {
            $alternative = collect($drivers)->filter()->keys()->first();

            $this->components->error("The configured driver [{$configured}] is not available. Install the PHP {$configured} extension"
                .($alternative !== null ? " or set STUP_IMAGE_DRIVER={$alternative}." : '.'));

            return self::FAILURE;
        }

        $this->components->info("Stup Image is ready to use with the [{$configured}] driver.");

        return self::SUCCESS;
    }
}
