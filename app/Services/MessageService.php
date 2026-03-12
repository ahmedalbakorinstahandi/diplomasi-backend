<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class MessageService
{


    public static function abort($status, $message, $replace = [], $extra = [])
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? [];

        $details = [
            'file' => $trace['file'] ?? null,
            'line' => $trace['line'] ?? null,
            'route' => request()->path(),
            'method' => request()->method(),
        ];

        abort(
            response()->json(
                [
                    'success' => false,
                    'message' => trans($message, $replace),
                    'key' => $message,
                    'details' => array_merge($details, $extra),
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
