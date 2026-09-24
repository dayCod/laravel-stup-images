<?php

declare(strict_types=1);

namespace Daycode\StupImage\Tests\Fixtures;

use Daycode\StupImage\Stupable;

/**
 * Lets Larastan analyse the deprecated Stupable trait.
 */
class LegacyUploader
{
    use Stupable;
}
