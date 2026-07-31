<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class OrderHelper
{
    /**
     * استعلام يشمل المحذوف ناعماً فقط إذا كان النموذج يستخدم SoftDeletes.
     */
    private static function queryIncludingTrashed(Model $model): Builder
    {
        $query = $model->newQuery();

        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            return $query->withTrashed();
        }

        return $query;
    }

    /**
     * تعيين ترتيب جديد تلقائي عند الإنشاء (بأعلى رقم موجود + 1).
     * order_index فريد على مستوى الجدول كامل (global) ما لم يُمرَّر $scope
     * لتقييد الحساب (مثلاً ضمن negotiation_level_id).
     *
     * @param  callable(Builder): void|null  $scope
     */
    public static function assign(Model $model, string $orderField = 'order_index', ?callable $scope = null): void
    {
        $query = static::queryIncludingTrashed($model);

        if ($scope !== null) {
            $scope($query);
        }

        $max = $query->max($orderField) ?? 0;
        $model->{$orderField} = $max + 1;
        $model->save();
    }

    /**
     * إعادة ترتيب عنصر بتحريكه إلى موقع جديد.
     * مرّر $scope لتقييد الإزاحة بمجموعة فرعية (مثلاً نفس المستوى).
     *
     * @param  callable(Builder): void|null  $scope
     */
    public static function reorder(Model $model, int $newOrder, string $orderField = 'order_index', ?callable $scope = null): void
    {
        $oldOrder = $model->{$orderField};

        if ($oldOrder === $newOrder) {
            return;
        }

        DB::transaction(function () use ($model, $oldOrder, $newOrder, $orderField, $scope) {
            $query = static::queryIncludingTrashed($model);

            if ($scope !== null) {
                $scope($query);
            }

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
