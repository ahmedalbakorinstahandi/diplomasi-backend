<?php

namespace App\Services;

use App\Models\Image;
use App\Models\Media;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageService
{
    private static function MakeFolder($folderName)
    {
        $pathFolder = storage_path(sprintf('app/public/%s', $folderName));

        if (!File::isDirectory($pathFolder)) {
            File::makeDirectory($pathFolder, 0755, true, true);
        }
    }




    public static function storeImage($image, $folder, $name = null)
    {
        self::MakeFolder($folder);

        $baseName = $name ? $name . '-' . uniqid() : uniqid();
        $imageName = $baseName . '.webp';
        $new_path = storage_path("app/public/{$folder}/{$imageName}");

        $manager = new ImageManager(new Driver());

        // Read image and get original size in bytes
        $originalSize = is_string($image) && file_exists($image) ? filesize($image) : (is_object($image) && method_exists($image, 'getSize') ? $image->getSize() : null);

        // Default quality
        $quality = 90;

        // If original size > 300KB, reduce quality
        if ($originalSize && $originalSize > 300 * 1024) {
            // Try to estimate quality needed to get under 300KB
            // Start from 90, decrease by 10 until under 300KB or reach 10
            $tempQuality = 90;
            do {
                $imageContent = $manager->read($image)->toWebp(quality: $tempQuality);
                $tempFile = tempnam(sys_get_temp_dir(), 'img_');
                $imageContent->save($tempFile);
                $newSize = filesize($tempFile);
                unlink($tempFile);

                if ($newSize <= 300 * 1024) {
                    $quality = $tempQuality;
                    break;
                }
                $tempQuality -= 10;
            } while ($tempQuality >= 10);

            // If still too big, set to lowest quality
            if ($newSize > 300 * 1024) {
                $quality = 10;
            }
        }

        $imageContent = $manager->read($image)->toWebp(quality: $quality);
        $imageContent->save($new_path);

        return "{$folder}/{$imageName}";
    }

    /**
     * حفظ الصورة مع الاحتفاظ باسمها الأصلي
     */
    public static function storeImageWithOriginalName($image, $folder, $originalName)
    {
        self::MakeFolder($folder);

        // تنظيف الاسم من الأحرف غير المسموحة
        $cleanName = preg_replace('/[^a-zA-Z0-9\-\_\.]/', '_', $originalName);
        $cleanName = trim($cleanName, '._-');
        
        // إضافة timestamp لتجنب تكرار الأسماء
        $timestamp = time();
        $imageName = "{$cleanName}.webp";
        
        $new_path = storage_path("app/public/{$folder}/{$imageName}");

        $manager = new ImageManager(new Driver());

        // Read image and get original size in bytes
        $originalSize = is_string($image) && file_exists($image) ? filesize($image) : (is_object($image) && method_exists($image, 'getSize') ? $image->getSize() : null);

        // Default quality
        $quality = 90;

        // If original size > 300KB, reduce quality
        if ($originalSize && $originalSize > 300 * 1024) {
            // Try to estimate quality needed to get under 300KB
            // Start from 90, decrease by 10 until under 300KB or reach 10
            $tempQuality = 90;
            do {
                $imageContent = $manager->read($image)->toWebp(quality: $tempQuality);
                $tempFile = tempnam(sys_get_temp_dir(), 'img_');
                $imageContent->save($tempFile);
                $newSize = filesize($tempFile);
                unlink($tempFile);

                if ($newSize <= 300 * 1024) {
                    $quality = $tempQuality;
                    break;
                }
                $tempQuality -= 10;
            } while ($tempQuality >= 10);

            // If still too big, set to lowest quality
            if ($newSize > 300 * 1024) {
                $quality = 10;
            }
        }

        $imageContent = $manager->read($image)->toWebp(quality: $quality);
        $imageContent->save($new_path);

        return "{$folder}/{$imageName}";
    }


    public static function updateImage($image, $folder, $oldImageName): string|null
    {
        // return ImageService::deleteImage($oldImageName) ? ImageService::storeImage($image, $folder) : null;
        return ImageService::storeImage($image, $folder);
    }


    public static function deleteImage($imagePath)
    {
        if (strpos($imagePath, asset("storage/")) !== false) {
            $imagePath = str_replace(asset("storage/"), "", $imagePath);
        }

        if (Storage::disk('public')->exists($imagePath)) {
            return  Storage::disk('public')->delete($imagePath);
        }

        return false;
    }


    public static function removeImages($ids)
    {
        if (!is_array($ids)) {
            abort(
                response()->json([
                    'success' => false,
                    'message' => trans('messages.image.ids_must_be_array'),
                    'key' => 'messages.image.ids_must_be_array',
                ], 422),
            );
        }

        $existingImages = Media::whereIn('id', $ids)->get();

        if (count($ids) !== $existingImages->count()) {
            abort(
                response()->json([
                    'success' => false,
                    'message' => trans('messages.image.some_images_not_found'),
                    'key' => 'messages.image.some_images_not_found',
                ], 422),
            );
        }



        foreach ($existingImages as $image) {
            if (Storage::exists("public/" . $image->path)) {
                Storage::delete("public/" . $image->path);
            }
            $image->delete();
        }
    }
}
