<?php

declare(strict_types=1);

namespace Daycode\StupImage\Concerns;

use Daycode\StupImage\Facades\StupImage;
use Daycode\StupImage\StoredImage;
use Daycode\StupImage\StupImageManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Store uploaded files assigned to image attributes and clean up replaced images.
 *
 *     protected array $stupImages = [
 *         'avatar' => 'avatars',
 *         'cover' => ['directory' => 'covers', 'preset' => 'medium', 'disk' => 's3', 'variants' => ['thumbnail']],
 *     ];
 *
 *     $user->update(['avatar' => $request->file('avatar')]);
 *     $user->avatarUrl();
 *
 * @mixin Model
 */
trait HasStupImages
{
    /**
     * Replaced or cleared images to delete once the model has been saved.
     *
     * @var list<array{disk: string|null, path: string}>
     */
    protected array $stupImagesToDelete = [];

    public static function bootHasStupImages(): void
    {
        static::saving(function (Model $model): void {
            /** @var Model&self $model */
            $model->storeStupImages();
        });

        static::saved(function (Model $model): void {
            /** @var Model&self $model */
            $model->deleteReplacedStupImages();
        });

        static::deleted(function (Model $model): void {
            /** @var Model&self $model */
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->deleteAllStupImages();
        });
    }

    /**
     * The image attributes and their options.
     *
     * @return array<string, array{directory?: string, disk?: string, preset?: string, variants?: list<string>}>
     */
    public function stupImageAttributes(): array
    {
        /** @var array<string, string|array{directory?: string, disk?: string, preset?: string, variants?: list<string>}> $definitions */
        $definitions = property_exists(static::class, 'stupImages') ? $this->stupImages : [];

        return array_map(
            fn (string|array $options): array => is_string($options) ? ['directory' => $options] : $options,
            $definitions,
        );
    }

    /**
     * The URL of an image attribute (or one of its variants), null when empty.
     */
    public function stupImageUrl(string $attribute, ?string $variant = null): ?string
    {
        $path = $this->getAttribute($attribute);

        if (! is_string($path) || $path === '') {
            return null;
        }

        return $this->stupImageManager($attribute)->url($path, $variant);
    }

    /**
     * Handle dynamic "{attribute}Url()" calls, e.g. $user->avatarUrl('thumbnail').
     *
     * @param  string  $method
     * @param  array<mixed>  $parameters
     */
    public function __call($method, $parameters)
    {
        if (str_ends_with($method, 'Url') && $method !== 'Url') {
            $attribute = Str::snake(substr($method, 0, -3));

            if (array_key_exists($attribute, $this->stupImageAttributes())) {
                $variant = $parameters[0] ?? null;

                if ($variant !== null && ! is_string($variant)) {
                    throw new InvalidArgumentException("The variant passed to {$method}() must be a preset name.");
                }

                return $this->stupImageUrl($attribute, $variant);
            }
        }

        return parent::__call($method, $parameters);
    }

    protected function storeStupImages(): void
    {
        $stored = [];

        try {
            foreach ($this->stupImageAttributes() as $attribute => $options) {
                $value = $this->getAttributes()[$attribute] ?? null;
                $original = $this->getRawOriginal($attribute);
                $original = is_string($original) && $original !== '' ? $original : null;

                if ($value instanceof UploadedFile) {
                    $image = $this->storeStupImage($value, $options);
                    $stored[] = $image;

                    $this->setAttribute($attribute, $image->path);

                    if ($original !== null && $original !== $image->path) {
                        $this->stupImagesToDelete[] = ['disk' => $options['disk'] ?? null, 'path' => $original];
                    }

                    continue;
                }

                // The attribute was cleared: delete the old image after saving.
                if ($original !== null && $this->isDirty($attribute) && ($value === null || $value === '')) {
                    $this->stupImagesToDelete[] = ['disk' => $options['disk'] ?? null, 'path' => $original];
                }
            }
        } catch (Throwable $e) {
            // Another attribute failed: the save is aborted, so remove the images stored so far.
            foreach ($stored as $image) {
                StupImage::disk($image->disk)->delete($image->path);
            }

            $this->stupImagesToDelete = [];

            throw $e;
        }
    }

    /**
     * @param  array{directory?: string, disk?: string, preset?: string, variants?: list<string>}  $options
     */
    protected function storeStupImage(UploadedFile $file, array $options): StoredImage
    {
        $upload = StupImage::from($file);

        if (isset($options['disk'])) {
            $upload->disk($options['disk']);
        }

        if (isset($options['directory'])) {
            $upload->directory($options['directory']);
        }

        if (isset($options['preset'])) {
            $upload->preset($options['preset']);
        }

        if (isset($options['variants'])) {
            $upload->variants($options['variants']);
        }

        return $upload->store();
    }

    protected function deleteReplacedStupImages(): void
    {
        $pending = $this->stupImagesToDelete;
        $this->stupImagesToDelete = [];

        foreach ($pending as ['disk' => $disk, 'path' => $path]) {
            $this->safelyDeleteStupImage($disk, $path);
        }
    }

    protected function deleteAllStupImages(): void
    {
        foreach ($this->stupImageAttributes() as $attribute => $options) {
            $path = $this->getRawOriginal($attribute);

            if (is_string($path) && $path !== '') {
                $this->safelyDeleteStupImage($options['disk'] ?? null, $path);
            }
        }
    }

    private function safelyDeleteStupImage(?string $disk, string $path): void
    {
        try {
            StupImage::disk($disk)->delete($path);
        } catch (Throwable $e) {
            // The database is already up to date; a leftover file must not fail the request.
            report($e);
        }
    }

    private function stupImageManager(string $attribute): StupImageManager
    {
        return StupImage::disk($this->stupImageAttributes()[$attribute]['disk'] ?? null);
    }
}
