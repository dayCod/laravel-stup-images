<?php

declare(strict_types=1);

namespace Daycode\StupImage;

use Daycode\StupImage\Exceptions\StupImageException;
use Daycode\StupImage\Facades\StupImage;
use Illuminate\Http\UploadedFile;

/**
 * The v1 API, now a thin wrapper around the StupImage facade.
 *
 * Unlike v1, failures throw a StupImageException instead of returning an exception.
 *
 * @deprecated Use the StupImage facade instead. This trait will be removed in v3. See UPGRADE.md.
 */
trait Stupable
{
    /**
     * Upload a file and return its filename (relative to $path).
     *
     * @param  array{0?: int|null, 1?: int|null}|null  $resize
     *
     * @throws StupImageException
     */
    public function uploadFile(UploadedFile $file, string $path, ?array $resize = []): string
    {
        return $this->stupableUpload(StupImage::from($file), $path, $resize)->store()->filename;
    }

    /**
     * Upload a file, then delete the old one, and return the new filename.
     *
     * @param  array{0?: int|null, 1?: int|null}|null  $resize
     *
     * @throws StupImageException
     */
    public function syncUploadFile(UploadedFile $file, ?string $oldFileName, ?string $path, ?array $resize = []): string
    {
        $old = $oldFileName === null || $oldFileName === '' ? null : self::stupablePath($path, $oldFileName);

        return $this->stupableUpload(StupImage::replace($old, $file), $path, $resize)->store()->filename;
    }

    /**
     * Upload multiple files and return their filenames.
     *
     * @param  array<mixed>  $files
     * @param  array{0?: int|null, 1?: int|null}|null  $resize
     * @return list<string>
     *
     * @throws StupImageException
     */
    public function uploadMultipleFiles(array $files, ?string $path, ?array $resize = []): array
    {
        return array_values($this->stupableUpload(StupImage::many($files), $path, $resize)
            ->store()
            ->map(fn (StoredImage $image): string => $image->filename)
            ->all());
    }

    /**
     * Delete a file.
     */
    public function deleteFile(string $fileName, string $path): void
    {
        StupImage::delete(self::stupablePath($path, $fileName));
    }

    /**
     * @template TUpload of PendingUpload
     *
     * @param  TUpload  $upload
     * @param  array{0?: int|null, 1?: int|null}|null  $resize
     * @return TUpload
     */
    private function stupableUpload(PendingUpload $upload, ?string $path, ?array $resize): PendingUpload
    {
        $upload->directory($path ?? '');

        if (! empty($resize)) {
            $upload->resize($resize[0] ?? null, $resize[1] ?? null);
        }

        return $upload;
    }

    private static function stupablePath(?string $path, string $fileName): string
    {
        return ltrim(rtrim((string) $path, '/').'/'.$fileName, '/');
    }
}
