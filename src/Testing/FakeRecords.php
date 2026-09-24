<?php

declare(strict_types=1);

namespace Daycode\StupImage\Testing;

use Daycode\StupImage\StoredImage;

/**
 * Operations recorded by StupImageFake, shared between the clones created by disk().
 *
 * @internal
 */
final class FakeRecords
{
    /**
     * @var list<StoredImage>
     */
    public array $stored = [];

    /**
     * @var list<array{disk: string, path: string}>
     */
    public array $deleted = [];

    /**
     * @var array<string, true>
     */
    public array $fakedDisks = [];
}
