<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\FileService;
use App\Services\IframelyService;
use App\Services\ImageService;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp,avif|max:8192',
            'folder' => 'required|string',
        ]);

        $imageName = ImageService::storeImage($request->image, $request->folder);

        return response()->json([
            'success' => true,
            'data' => [
                'image_name' => $imageName,
                'image_url' => asset('storage/' . $imageName),
            ],
            'message' => trans('messages.image.uploaded'),
            'key' => 'messages.image.uploaded',
        ]);
    }

    // upload file
    public function uploadFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg,gif,webp,mp4,mov,avi,mkv,mp3,wav,m4a,ogg,webm,txt,csv,json,xml,zip,rar,7z,tar,gz,bz2,iso,dmg,pkg,deb,rpm,exe,app,msi,cab,jar,war,ear,whl,whl.gz,whl.zip,whl.whl,whl.tar,whl.gz.tar,whl.zip.tar,whl.whl.tar,whl.gz.zip,whl.zip.zip,whl.whl.zip,whl.gz.whl,whl.zip.whl,whl.whl.whl',
            'folder' => 'required|string',
        ]);


        $fileName = FileService::storeFile($request->file, $request->folder);

        return response()->json([
            'success' => true,
            'data' => [
                'file_name' => $fileName,
                'file_url' => asset('storage/' . $fileName),
            ],
            'message' => trans('messages.image.file_uploaded'),
            'key' => 'messages.image.file_uploaded',
        ]);
    }


    public function fetchMedia(Request $request)
    {


        $request->validate([
            'url' => 'required|url',
        ]);



        $media = IframelyService::fetch($request->url);


        return response()->json([
            'success' => true,
            'data' => $media,
            'message' => trans('messages.image.media_fetched'),
            'key' => 'messages.image.media_fetched',
        ]);
    }
}
