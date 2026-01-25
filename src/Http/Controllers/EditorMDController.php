<?php

namespace Dcat\Admin\Http\Controllers;

use Dcat\Admin\Support\Security\SecureUploader;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class EditorMDController
{
    public function upload(Request $request)
    {
        $file = $request->file('editormd-image-file');

        if (! $file || ! $file->isValid()) {
            return ['success' => 0, 'message' => 'Invalid file upload.'];
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

            return ['success' => 1, 'url' => $storage->url("{$dir}/{$newName}")];
        } catch (FileException $e) {
            return ['success' => 0, 'message' => $e->getMessage()];
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
