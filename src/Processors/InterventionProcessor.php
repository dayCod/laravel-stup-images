<?php

declare(strict_types=1);

namespace Daycode\StupImage\Processors;

use Daycode\StupImage\Contracts\ImageProcessor;
use Daycode\StupImage\Exceptions\DriverNotAvailableException;
use Daycode\StupImage\Exceptions\InvalidImageException;
use Daycode\StupImage\MimeTypes;
use Daycode\StupImage\ProcessedImage;
use Intervention\Image\Decoders\BinaryImageDecoder;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\Exceptions\DriverException;
use Intervention\Image\Exceptions\RuntimeException as InterventionRuntimeException;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use InvalidArgumentException;

final class InterventionProcessor implements ImageProcessor
{
    private ?ImageManager $manager = null;

    public function __construct(private readonly string $driver = 'gd') {}

    public function process(string $contents, array $manipulations = [], ?string $format = null, int $quality = 85): ProcessedImage
    {
        try {
            // Only decode raw binary data: never treat the contents as a file path.
            $image = $this->manager()->read($contents, BinaryImageDecoder::class);
        } catch (DecoderException|InterventionRuntimeException $e) {
            throw InvalidImageException::unreadable('(binary)', $e);
        }

        foreach ($manipulations as [$operation, $width, $height]) {
            $image = $this->apply($image, $operation, $width, $height);
        }

        $extension = $format !== null
            ? MimeTypes::normalizeFormat($format)
            : MimeTypes::extension($image->origin()->mediaType()) ?? 'jpg';

        $encoded = $image->encodeByExtension($extension, quality: $quality);

        return new ProcessedImage(
            contents: $encoded->toString(),
            mimeType: $encoded->mediaType(),
            extension: $extension,
            width: $image->width(),
            height: $image->height(),
        );
    }

    private function apply(ImageInterface $image, string $operation, ?int $width, ?int $height): ImageInterface
    {
        return match ($operation) {
            'resize' => $image->resize($width, $height),
            'scale' => $image->scale($width, $height),
            'cover' => $image->cover($width ?? $image->width(), $height ?? $image->height()),
            'contain' => $image->contain($width ?? $image->width(), $height ?? $image->height()),
            default => throw new InvalidArgumentException("Unsupported image operation [{$operation}]."),
        };
    }

    private function manager(): ImageManager
    {
        if ($this->manager !== null) {
            return $this->manager;
        }

        try {
            $driver = match ($this->driver) {
                'gd' => new GdDriver,
                'imagick' => new ImagickDriver,
                default => throw DriverNotAvailableException::unsupported($this->driver),
            };
        } catch (DriverException $e) {
            throw DriverNotAvailableException::missingExtension($this->driver, $e);
        }

        return $this->manager = new ImageManager($driver);
    }
}
