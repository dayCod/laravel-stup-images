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
    public function uploadFile(UploadedFile $file, string $folderPrefix): string
    {
        $fileName = $file->getClientOriginalName();
        $fileExt = $file->getClientOriginalExtension();
        $encodedFileName = md5(time().$fileName).'.'.$fileExt;

        $service = (new Intervention)
            ->read($file)
            ->setImageName($encodedFileName)
            ->setPath(storage_path('app/public/'.$folderPrefix));

        return $service->save();
    }

    /**
     * Sync upload file
     */
    public function syncUploadFile(UploadedFile $file, ?string $oldFileName, ?string $folderPrefix): string
    {
        if (! is_null($oldFileName)) {
            $this->deleteFile($oldFileName, $folderPrefix);
        }

        return $this->uploadFile($file, $folderPrefix);
    }

    /**
     * Upload multiple files
     */
    public function uploadMultipleFiles(array $files, ?string $folderPrefix): array|UploadException
    {
        if (is_array($files)) {
            $imagePath = [];

            foreach ($files as $file) {
                $imagePath[] = $this->uploadFile(file: $file, folderPrefix: $folderPrefix);
            }

            return $imagePath;
        }

        return new UploadException('The provided image request is not an array.');
    }

    /**
     * Delete file
     */
    public function deleteFile(string $fileName, string $folderPrefix): void
    {
        if (Storage::disk('public')->exists("{$folderPrefix}/{$fileName}")) {
            Storage::disk('public')->delete("{$folderPrefix}/{$fileName}");
        }
    }
}
