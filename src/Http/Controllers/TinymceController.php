<?php

namespace Dcat\Admin\Http\Controllers;

use Dcat\Admin\Support\Security\SecureUploader;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class TinymceController
{
    public function upload(Request $request)
    {
        $file = $request->file('file');

        if (! $file || ! $file->isValid()) {
            return response()->json(['error' => 'Invalid file upload.'], 400);
        }

        try {
            // Validate and sanitize inputs
            $dir = SecureUploader::sanitizeDirectory($request->get('dir', ''));
            $disk = SecureUploader::validateDisk($request->get('disk'));

            // Validate file type (images only for editor)
            SecureUploader::validateExtension($file, 'image');
            SecureUploader::validateMimeType($file, 'image');

            // Generate secure filename
            $newName = SecureUploader::generateSecureFilename($file);

            // Store file
            $storage = Storage::disk($disk);
            $storage->putFileAs($dir, $file, $newName);

            return ['location' => $storage->url("{$dir}/{$newName}")];
        } catch (FileException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @return \Illuminate\Contracts\Filesystem\Filesystem|FilesystemAdapter
     */
    protected function disk()
    {
        $disk = SecureUploader::validateDisk(request()->get('disk'));

        return Storage::disk($disk);
    }
}
