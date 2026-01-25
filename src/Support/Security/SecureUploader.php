<?php

namespace Dcat\Admin\Support\Security;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

/**
 * Secure file upload handler for preventing file upload vulnerabilities.
 *
 * This class provides validation for:
 * - Path traversal attacks
 * - Dangerous file extensions
 * - MIME type validation
 * - Disk parameter validation
 * - Chunked upload parameter validation
 */
class SecureUploader
{
    /**
     * Default allowed image extensions.
     *
     * @var array<string>
     */
    protected static array $defaultImageExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico',
    ];

    /**
     * Default allowed file extensions.
     *
     * @var array<string>
     */
    protected static array $defaultFileExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv', 'zip', 'rar', '7z',
    ];

    /**
     * Dangerous extensions that should never be allowed.
     *
     * @var array<string>
     */
    protected static array $dangerousExtensions = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps',
        'phar', 'inc', 'hphp', 'cgi', 'pl', 'py', 'pyc', 'pyo',
        'asp', 'aspx', 'jsp', 'jspx', 'sh', 'bash', 'zsh',
        'exe', 'msi', 'bat', 'cmd', 'com', 'scr', 'vbs', 'vbe',
        'js', 'jse', 'ws', 'wsf', 'wsc', 'wsh', 'ps1', 'ps1xml',
        'htaccess', 'htpasswd', 'user.ini',
    ];

    /**
     * Validate and sanitize directory path.
     *
     * @param  string  $dir  The directory path to sanitize
     * @return string The sanitized directory path
     *
     * @throws FileException
     */
    public static function sanitizeDirectory(string $dir): string
    {
        // Remove any null bytes
        $dir = str_replace("\0", '', $dir);

        // Normalize slashes
        $dir = str_replace('\\', '/', $dir);

        // Remove path traversal attempts
        $dir = preg_replace('#\.\.+#', '', $dir);
        $dir = preg_replace('#//+#', '/', $dir);

        // Remove leading/trailing slashes and dots
        $dir = trim($dir, '/.');

        // Validate: only allow alphanumeric, dash, underscore, slash
        if (! preg_match('#^[a-zA-Z0-9/_-]*$#', $dir)) {
            throw new FileException('Invalid directory path.');
        }

        return $dir;
    }

    /**
     * Validate disk name against configured disks.
     *
     * @param  string|null  $disk  The disk name to validate
     * @return string The validated disk name
     *
     * @throws FileException
     */
    public static function validateDisk(?string $disk): string
    {
        $allowedDisks = config('admin.security.allowed_upload_disks', ['public', 'local']);
        $defaultDisk = config('admin.upload.disk', 'public');

        if (empty($disk)) {
            return $defaultDisk;
        }

        if (! in_array($disk, $allowedDisks, true)) {
            throw new FileException("Disk [{$disk}] is not allowed for uploads.");
        }

        // Also verify disk is actually configured
        if (! config("filesystems.disks.{$disk}")) {
            throw new FileException("Disk [{$disk}] is not configured.");
        }

        return $disk;
    }

    /**
     * Validate file extension.
     *
     * @param  UploadedFile  $file  The uploaded file
     * @param  string  $type  The type: 'image' or 'file'
     * @return string The validated extension
     *
     * @throws FileException
     */
    public static function validateExtension(UploadedFile $file, string $type = 'file'): string
    {
        // Get extension from the actual file, not client-provided name
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());

        // Block dangerous extensions regardless of settings
        if (in_array($extension, static::$dangerousExtensions, true)) {
            throw new FileException("File type [{$extension}] is not allowed.");
        }

        // Get allowed extensions from config or defaults
        $allowedExtensions = $type === 'image'
            ? config('admin.security.allowed_image_extensions', static::$defaultImageExtensions)
            : config('admin.security.allowed_file_extensions', static::$defaultFileExtensions);

        if (! in_array($extension, $allowedExtensions, true)) {
            throw new FileException(
                "File type [{$extension}] is not allowed. ".
                'Allowed types: '.implode(', ', $allowedExtensions)
            );
        }

        return $extension;
    }

    /**
     * Validate MIME type matches extension.
     *
     * @param  UploadedFile  $file  The uploaded file
     * @param  string  $type  The type: 'image' or 'file'
     *
     * @throws FileException
     */
    public static function validateMimeType(UploadedFile $file, string $type = 'file'): void
    {
        $mimeType = $file->getMimeType();
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());

        // Basic MIME/extension consistency check for images
        if ($type === 'image') {
            $imageMimes = [
                'jpg' => ['image/jpeg'],
                'jpeg' => ['image/jpeg'],
                'png' => ['image/png'],
                'gif' => ['image/gif'],
                'webp' => ['image/webp'],
                'svg' => ['image/svg+xml', 'text/html', 'text/plain'],
                'bmp' => ['image/bmp', 'image/x-bmp', 'image/x-ms-bmp'],
                'ico' => ['image/x-icon', 'image/vnd.microsoft.icon'],
            ];

            if (isset($imageMimes[$extension])) {
                if (! in_array($mimeType, $imageMimes[$extension], true)) {
                    throw new FileException(
                        "File MIME type [{$mimeType}] does not match extension [{$extension}]."
                    );
                }
            }
        }
    }

    /**
     * Generate a secure filename.
     *
     * @param  UploadedFile  $file  The uploaded file
     * @return string The secure filename
     */
    public static function generateSecureFilename(UploadedFile $file): string
    {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());

        // Use hash of original name + random for uniqueness
        return md5($file->getClientOriginalName().uniqid((string) mt_rand(), true)).'.'.$extension;
    }

    /**
     * Validate chunk upload parameters.
     *
     * @param  string  $id  The chunk upload ID
     * @param  int  $chunk  The current chunk number
     * @param  int  $chunks  The total number of chunks
     *
     * @throws FileException
     */
    public static function validateChunkParams(string $id, int $chunk, int $chunks): void
    {
        // Sanitize ID (alphanumeric only)
        if (! preg_match('/^[a-zA-Z0-9]+$/', $id)) {
            throw new FileException('Invalid chunk ID.');
        }

        // Validate chunk numbers
        if ($chunk < 0 || $chunks < 1 || $chunk >= $chunks) {
            throw new FileException('Invalid chunk parameters.');
        }

        // Limit total chunks
        $maxChunks = config('admin.security.max_upload_chunks', 1000);
        if ($chunks > $maxChunks) {
            throw new FileException("Too many chunks. Maximum allowed: {$maxChunks}");
        }
    }

    /**
     * Validate upload ID for path safety.
     *
     * @param  string  $id  The upload ID to validate
     * @return string The validated ID
     *
     * @throws FileException
     */
    public static function validateUploadId(string $id): string
    {
        // Only allow alphanumeric, dash, underscore
        if (! preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
            throw new FileException('Invalid upload ID.');
        }

        // Limit length
        if (strlen($id) > 64) {
            throw new FileException('Upload ID too long.');
        }

        return $id;
    }
}
