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
}
