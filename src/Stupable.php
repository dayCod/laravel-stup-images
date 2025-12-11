<?php

declare(strict_types=1);

namespace Daycode\StupImage;

use Daycode\StupImage\Services\Intervention;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\Exception\UploadException;

trait Stupable
{
    /**
     * Upload file
     */
    public function uploadFile(UploadedFile $file, string $path, ?array $resize = []): string|UploadException
    {
        $fileExt = $file->getClientOriginalExtension();

        if (config('stup-image.allowed_extensions') !== ['*'] && !in_array($fileExt, config('stup-image.allowed_extensions'))) {
            return new UploadException('The provided image request is not an allowed extension.');
        }

        $fileName = $file->getClientOriginalName();

        if (config('stup-image.hash_filename')) {
            $fileName = md5(time().$fileName).'.'.$fileExt;
        } else {
            $fileName = "{$fileName}.{$fileExt}";
        }

        $service = (new Intervention)
            ->read($file)
            ->setImageName($fileName)
            ->setPath(Storage::path($path));

        if (!empty($resize)) {
            $service->resize($resize[0], $resize[1]);
        }

        return $service->save();
    }

    /**
     * Sync upload file
     */
    public function syncUploadFile(UploadedFile $file, ?string $oldFileName, ?string $path, ?array $resize = []): string
    {
        if (! is_null($oldFileName)) {
            $this->deleteFile($oldFileName, $path);
        }

        return $this->uploadFile($file, $path, $resize);
    }

    /**
     * Upload multiple files
     */
    public function uploadMultipleFiles(array $files, ?string $path, ?array $resize = []): array|UploadException
    {
        if (is_array($files)) {
            $imagePath = [];

            foreach ($files as $file) {
                $imagePath[] = $this->uploadFile(
                    file: $file,
                    path: $path,
                    resize: $resize
                );
            }

            return $imagePath;
        }

        return new UploadException('The provided image request is not an array.');
    }

    /**
     * Delete file
     */
    public function deleteFile(string $fileName, string $path): void
    {
        if (Storage::exists("{$path}/{$fileName}")) {
            Storage::delete("{$path}/{$fileName}");
        }
    }
}
