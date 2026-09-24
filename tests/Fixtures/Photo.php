<?php

declare(strict_types=1);

namespace Daycode\StupImage\Tests\Fixtures;

use Daycode\StupImage\Concerns\HasStupImages;
use Illuminate\Database\Eloquent\Model;

/**
 * Lets Larastan analyse the HasStupImages trait.
 */
class Photo extends Model
{
    use HasStupImages;

    /**
     * @var array<string, string|array{directory?: string, disk?: string, preset?: string, variants?: list<string>}>
     */
    protected array $stupImages = ['image' => 'photos'];
}
