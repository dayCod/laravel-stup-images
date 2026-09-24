<?php

declare(strict_types=1);

namespace Daycode\StupImage\Tests;

use Daycode\StupImage\StupImageServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            StupImageServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('filesystems.default', 'local');
        $app['config']->set('database.default', 'testing');
    }
}
