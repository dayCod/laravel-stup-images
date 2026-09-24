<?php

declare(strict_types=1);

use Daycode\StupImage\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/**
 * A disk whose put/delete calls fail: pass false to return false, or a Throwable to throw it.
 */
function brokenDisk(bool|Throwable|null $put = null, bool|Throwable|null $delete = null): Illuminate\Contracts\Filesystem\Filesystem
{
    $root = sys_get_temp_dir().'/stup-image-broken-'.bin2hex(random_bytes(4));

    return new class(new League\Flysystem\Filesystem($adapter = new League\Flysystem\Local\LocalFilesystemAdapter($root)), $adapter, ['root' => $root], $put, $delete) extends Illuminate\Filesystem\FilesystemAdapter
    {
        public function __construct($driver, $adapter, array $config, private bool|Throwable|null $failPut, private bool|Throwable|null $failDelete)
        {
            parent::__construct($driver, $adapter, $config);
        }

        public function put($path, $contents, $options = [])
        {
            if ($this->failPut instanceof Throwable) {
                throw $this->failPut;
            }

            // Allow test setup (plain strings) but fail image writes.
            if ($this->failPut === false && ! in_array($contents, ['x', 'old'], true)) {
                return false;
            }

            return parent::put($path, $contents, $options);
        }

        public function delete($paths)
        {
            if ($this->failDelete instanceof Throwable) {
                throw $this->failDelete;
            }

            return $this->failDelete === false ? false : parent::delete($paths);
        }
    };
}
