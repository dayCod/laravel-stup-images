<?php

declare(strict_types=1);

use Daycode\StupImage\Exceptions\DisallowedMimeTypeException;
use Daycode\StupImage\Exceptions\ImageTooLargeException;
use Daycode\StupImage\Exceptions\InvalidImageException;
use Daycode\StupImage\Exceptions\StorageException;
use Daycode\StupImage\Exceptions\StupImageException;
use Daycode\StupImage\Facades\StupImage;
use Daycode\StupImage\StoredImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

it('stores an image in one statement', function (): void {
    $image = StupImage::from(UploadedFile::fake()->image('avatar.jpg', 1000, 800))
        ->directory('avatars')
        ->cover(400, 400)
        ->toWebp(quality: 80)
        ->store();

    expect($image)->toBeInstanceOf(StoredImage::class)
        ->and($image->disk)->toBe('local')
        ->and($image->path)->toMatch('#^avatars/[0-9a-z]{26}\.webp$#')
        ->and($image->filename)->toBe(basename($image->path))
        ->and($image->mimeType)->toBe('image/webp')
        ->and([$image->width, $image->height])->toBe([400, 400])
        ->and($image->size)->toBe(Storage::disk('local')->size($image->path));

    expect(getimagesizefromstring((string) Storage::disk('local')->get($image->path)))
        ->toMatchArray([0 => 400, 1 => 400, 'mime' => 'image/webp']);
});

it('uses the configured directory and keeps the original format', function (): void {
    $image = StupImage::from(UploadedFile::fake()->image('photo.png', 20, 10))->store();

    expect($image->path)->toStartWith('images/')->toEndWith('.png')
        ->and([$image->width, $image->height])->toBe([20, 10]);
});

it('stores in the root when the directory is empty', function (): void {
    $image = StupImage::from(UploadedFile::fake()->image('photo.png'))->directory('/')->store();

    expect($image->path)->toBe($image->filename);
});

it('trims slashes from the directory', function (): void {
    $image = StupImage::from(UploadedFile::fake()->image('photo.png'))->directory('/users/avatars/')->store();

    expect($image->path)->toStartWith('users/avatars/');
});

it('stores on the default filesystem disk', function (): void {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);

    $image = StupImage::from(UploadedFile::fake()->image('photo.jpg'))->store();

    expect($image->disk)->toBe('public');
    Storage::disk('public')->assertExists($image->path);
});

it('prefers the configured package disk', function (): void {
    Storage::fake('public');
    config(['stup-image.disk' => 'public']);

    expect(StupImage::from(UploadedFile::fake()->image('photo.jpg'))->store()->disk)->toBe('public');
});

it('stores on any disk, including s3', function (string $disk): void {
    Storage::fake($disk);

    $viaBuilder = StupImage::from(UploadedFile::fake()->image('photo.jpg'))->disk($disk)->store();
    $viaManager = StupImage::disk($disk)->from(UploadedFile::fake()->image('photo.jpg'))->store();

    expect([$viaBuilder->disk, $viaManager->disk])->toBe([$disk, $disk]);
    Storage::disk($disk)->assertExists([$viaBuilder->path, $viaManager->path]);

    if ($disk !== 'local') {
        Storage::disk('local')->assertMissing([$viaBuilder->path, $viaManager->path]);
    }
})->with(['local', 'public', 's3']);

it('passes the visibility to the disk', function (): void {
    $private = StupImage::from(UploadedFile::fake()->image('photo.jpg'))->visibility('private')->store();
    $public = StupImage::from(UploadedFile::fake()->image('photo.jpg'))->visibility('public')->store();

    expect(Storage::disk('local')->getVisibility($private->path))->toBe('private')
        ->and(Storage::disk('local')->getVisibility($public->path))->toBe('public');
});

it('uses the configured visibility', function (): void {
    config(['stup-image.visibility' => 'public']);

    $image = StupImage::from(UploadedFile::fake()->image('photo.jpg'))->store();

    expect(Storage::disk('local')->getVisibility($image->path))->toBe('public');
});

it('uses an explicit name and overwrites an existing file', function (): void {
    $first = StupImage::from(UploadedFile::fake()->image('a.jpg', 10, 10))->directory('logos')->name('Company Logo.jpg')->store();
    $second = StupImage::from(UploadedFile::fake()->image('b.jpg', 20, 20))->directory('logos')->name('company-logo')->store();

    expect($first->path)->toBe('logos/company-logo.jpg')
        ->and($second->path)->toBe('logos/company-logo.jpg')
        ->and(getimagesizefromstring((string) Storage::disk('local')->get($second->path))[0])->toBe(20);
});

it('keeps dots in explicit names that are not image extensions', function (): void {
    expect(StupImage::from(UploadedFile::fake()->image('a.jpg'))->directory('')->name('v1.2')->store()->path)->toBe('v12.jpg');
});

it('rejects empty explicit names', function (): void {
    StupImage::from(UploadedFile::fake()->image('a.jpg'))->name('***');
})->throws(InvalidArgumentException::class);

it('uses the configured naming strategy', function (string $naming, string $pattern): void {
    config(['stup-image.naming' => $naming]);

    expect(StupImage::from(UploadedFile::fake()->image('My Photo.jpg'))->directory('')->store()->path)->toMatch($pattern);
})->with([
    ['ulid', '/^[0-9a-z]{26}\.jpg$/'],
    ['uuid', '/^[0-9a-f-]{36}\.jpg$/'],
    ['hash', '/^[0-9a-f]{32}\.jpg$/'],
    ['original', '/^my-photo\.jpg$/'],
]);

it('accepts a custom namer class', function (): void {
    config(['stup-image.naming' => FixedNamer::class]);

    expect(StupImage::from(UploadedFile::fake()->image('a.jpg'))->directory('x')->store()->path)->toBe('x/fixed.jpg');
});

it('rejects an invalid naming strategy', function (): void {
    config(['stup-image.naming' => 'nope']);

    StupImage::from(UploadedFile::fake()->image('a.jpg'))->store();
})->throws(InvalidArgumentException::class, 'Invalid image naming strategy [nope]');

it('converts every image to the default format', function (): void {
    config(['stup-image.default_format' => 'webp']);

    expect(StupImage::from(UploadedFile::fake()->image('a.png'))->store()->mimeType)->toBe('image/webp');
});

it('supports every output format helper', function (string $method, string $mime): void {
    expect(StupImage::from(UploadedFile::fake()->image('a.png'))->{$method}()->store()->mimeType)->toBe($mime);
})->with([
    ['toWebp', 'image/webp'],
    ['toJpeg', 'image/jpeg'],
    ['toPng', 'image/png'],
]);

it('supports avif when the driver can encode it', function (): void {
    expect(StupImage::from(UploadedFile::fake()->image('a.png'))->toAvif(50)->store()->mimeType)->toBe('image/avif');
})->skip(fn (): bool => ! function_exists('imageavif'), 'GD was built without AVIF support');

it('supports conditional options', function (): void {
    $image = StupImage::from(UploadedFile::fake()->image('a.png', 100, 100))
        ->when(true, fn ($upload) => $upload->scale(50))
        ->when(false, fn ($upload) => $upload->scale(10))
        ->store();

    expect($image->width)->toBe(50);
});

it('rejects invalid options', function (Closure $configure): void {
    $configure(StupImage::from(UploadedFile::fake()->image('a.png')));
})->with([
    'quality too low' => [fn ($upload) => $upload->quality(0)],
    'quality too high' => [fn ($upload) => $upload->quality(101)],
    'unknown format' => [fn ($upload) => $upload->format('psd')],
    'no dimensions' => [fn ($upload) => $upload->scale()],
])->throws(InvalidArgumentException::class);

describe('validation', function (): void {
    it('rejects a disallowed MIME type', function (): void {
        config(['stup-image.allowed_mime_types' => ['image/png']]);

        StupImage::from(UploadedFile::fake()->image('photo.jpg'))->store();
    })->throws(DisallowedMimeTypeException::class, 'image/jpeg');

    it('detects the MIME type from the contents, not the extension', function (): void {
        $path = tempnam(sys_get_temp_dir(), 'stup');
        file_put_contents($path, '<?php echo "not an image";');

        try {
            StupImage::from(new UploadedFile($path, 'innocent.jpg', 'image/jpeg', null, true))->store();
        } finally {
            @unlink($path);
        }
    })->throws(DisallowedMimeTypeException::class, 'text/x-php');

    it('rejects non-image files', function (): void {
        StupImage::from(UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'))->store();
    })->throws(DisallowedMimeTypeException::class);

    it('rejects files that cannot be decoded', function (): void {
        config(['stup-image.allowed_mime_types' => null]);

        StupImage::from(UploadedFile::fake()->createWithContent('broken.jpg', 'garbage'))->store();
    })->throws(InvalidImageException::class);

    it('rejects empty files', function (): void {
        StupImage::from(UploadedFile::fake()->createWithContent('empty.jpg', ''))->store();
    })->throws(InvalidImageException::class);

    it('rejects values that are not uploaded files', function (mixed $value): void {
        StupImage::from($value)->store();
    })->with([null, 'avatar.jpg', 42])->throws(InvalidImageException::class);

    it('rejects failed uploads', function (): void {
        $file = new UploadedFile(__FILE__, 'a.jpg', 'image/jpeg', UPLOAD_ERR_PARTIAL, true);

        StupImage::from($file)->store();
    })->throws(InvalidImageException::class, 'was not uploaded successfully');

    it('rejects files larger than max_size', function (): void {
        config(['stup-image.max_size' => 1]);

        StupImage::from(UploadedFile::fake()->image('big.png', 400, 400)->size(2))->store();
    })->throws(ImageTooLargeException::class, 'maximum allowed size is 1 KB');

    it('allows any size when max_size is null', function (): void {
        config(['stup-image.max_size' => null]);

        expect(StupImage::from(UploadedFile::fake()->image('big.png')->size(50_000))->store())->toBeInstanceOf(StoredImage::class);
    });

    it('rejects images larger than the max dimensions', function (): void {
        config(['stup-image.max_width' => 100]);

        StupImage::from(UploadedFile::fake()->image('wide.png', 101, 10))->store();
    })->throws(ImageTooLargeException::class, '101x10');

    it('rejects undecodable files when checking dimensions', function (): void {
        config(['stup-image.max_height' => 100, 'stup-image.allowed_mime_types' => null]);

        StupImage::from(UploadedFile::fake()->createWithContent('broken.jpg', 'garbage'))->store();
    })->throws(InvalidImageException::class);

    it('accepts images within the max dimensions', function (): void {
        config(['stup-image.max_width' => 100, 'stup-image.max_height' => 100]);

        expect(StupImage::from(UploadedFile::fake()->image('ok.png', 100, 100))->store()->width)->toBe(100);
    });

    it('leaves no file behind after a validation error', function (): void {
        config(['stup-image.allowed_mime_types' => ['image/png']]);

        try {
            StupImage::from(UploadedFile::fake()->image('photo.jpg'))->store();
        } catch (StupImageException) {
            //
        }

        expect(Storage::disk('local')->allFiles())->toBeEmpty();
    });
});

it('throws a storage exception when the disk refuses the write', function (): void {
    Storage::set('broken', brokenDisk(put: false));

    StupImage::from(UploadedFile::fake()->image('a.jpg'))->disk('broken')->store();
})->throws(StorageException::class, 'Unable to write');

it('wraps disk errors in a storage exception', function (): void {
    Storage::set('broken', brokenDisk(put: new RuntimeException('S3 is down')));

    StupImage::from(UploadedFile::fake()->image('a.jpg'))->disk('broken')->store();
})->throws(StorageException::class, 'Unable to write');

final class FixedNamer implements Daycode\StupImage\Contracts\Namer
{
    public function name(UploadedFile $file, string $extension, string $directory, Filesystem $disk): string
    {
        return "fixed.{$extension}";
    }
}
