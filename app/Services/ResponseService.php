<?php

namespace App\Services;

class ResponseService
{
    public static function meta($collection)
    {
        return
            [
                'current_page' => $collection->currentPage(),
                'last_page' => $collection->lastPage(),
                'per_page' => $collection->perPage(),
                'total' => $collection->total(),
            ];
    }


    public static function response(array $params, $status = 200)
    {
        $status = $status ?? 200;

        if (isset($params['status'])) {
            $status = $params['status'] ?? 200;
        }

        $replace = $params['replace'] ?? [];
        $response = [];

        foreach ($params as $key => $value) {
            switch ($key) {
                case 'status':
                case 'replace':
                case 'resource':
                    break;

                case 'message':
                    $response['message'] = trans($value, $replace);
                    $response['key'] = $value;
                    break;

                case 'data':
                    if (isset($params['resource']) && $value) {
                        $response['data'] = self::isCollectionLike($value)
                            ? $params['resource']::collection($value)
                            : new $params['resource']($value);
                    } else {
                        $response['data'] = $value;
                    }
                    break;

                case 'meta':
                    if ($value && isset($params['data'])) {
                        $response['meta'] = self::meta($params['data']);
                    }
                    break;

                case 'info':
                    $response['info'] = $value;
                    break;

                default:
                    $response[$key] = $value;
                    break;
            }
        }

        $response = ['success' => $status >= 200 && $status < 300] + $response;

        return response()->json($response, $status);
    }

    protected static function isCollectionLike($value): bool
    {
        return $value instanceof \Illuminate\Support\Collection
            || $value instanceof \Illuminate\Contracts\Pagination\Paginator;
    }
}
