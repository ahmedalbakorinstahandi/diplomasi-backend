<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class FileService
{
    public static function storeFile($file, $folder)
    {
        $path = $file->store($folder, 'public');

        return $path;
    }

    public static function updateFile($file, $folder, $oldFilePath)
    {
        if (Storage::disk('public')->exists($oldFilePath)) {
            Storage::disk('public')->delete($oldFilePath);
        }
        return self::storeFile($file, $folder);
    }

    public static function deleteFile($filePath)
    {
        if (Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
            return true;
        }
        return false;
    }

    public static function storeContent(string $content, string $path): string
    {
        Storage::disk('public')->put($path, $content);
        return $path;
    }

    public static function readContent(string $path): ?string
    {
        if (!Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->get($path);
    }

    public static function fileSize(string $path): int
    {
        if (!Storage::disk('public')->exists($path)) {
            return 0;
        }

        return (int) Storage::disk('public')->size($path);
    }

    public static function fileUrl(string $path): string
    {
        return asset('storage/' . ltrim($path, '/'));
    }
}
