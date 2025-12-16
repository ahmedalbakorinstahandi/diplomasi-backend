<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class MessageService
{


    public static function abort($status, $message, $replace = [])
    {
        abort(
            response()->json(
                [
                    'success' => false,
                    'message' => trans($message, $replace),
                    'key' => $message,
                ],
                $status
            )
        );
    }

    public static function success($message, $replace = [])
    {
        return response()->json([
            'success' => true,
            'message' => trans($message, $replace),
        ], 200);
    }

    public static function response($data, $status = 200)
    {
        abort(
            response()->json(
                $data,
                $status
            )
        );
    }
}
