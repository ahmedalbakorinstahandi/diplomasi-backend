<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrderHelper
{
    /**
     * تعيين ترتيب جديد تلقائي عند الإنشاء (بأعلى رقم موجود + 1).
     */
    public static function assign(Model $model, string $orderField = 'sort_order'): void
    {
        $max = $model->newQuery()->withTrashed()->max($orderField) ?? 0;
        $model->{$orderField} = $max + 1;
        $model->save();
    }

    /**
     * إعادة ترتيب عنصر بتحريكه إلى موقع جديد.
     */
    public static function reorder(Model $model, int $newOrder, string $orderField = 'sort_order'): void
    {
        $oldOrder = $model->{$orderField};

        if ($oldOrder === $newOrder) return;

        DB::transaction(function () use ($model, $oldOrder, $newOrder, $orderField) {
            $query = $model->newQuery()->withTrashed();

            if ($oldOrder < $newOrder) {
                // نقل من أعلى إلى أدنى → نقص العناصر بين القديم والجديد
                $query->where($orderField, '>', $oldOrder)
                      ->where($orderField, '<=', $newOrder)
                      ->decrement($orderField);
            } else {
                // نقل من أدنى إلى أعلى → زد العناصر بين الجديد والقديم
                $query->where($orderField, '>=', $newOrder)
                      ->where($orderField, '<', $oldOrder)
                      ->increment($orderField);
            }

            $model->{$orderField} = $newOrder;
            $model->save();
        });
    }
}
