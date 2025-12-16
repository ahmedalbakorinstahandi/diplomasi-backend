<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class FilterService
{
    public static function applyFilters(
        Builder $query,
        array $filters = [],
        array $searchFields = [],
        array $numericFields = [],
        array $dateFields = [],
        array $exactMatchFields = [],
        array $inFields = [],
        $withPagination = true,
    ) {
        // Apply filters only if they exist to avoid unnecessary processing
        if (!empty($filters['search'])) {
            $query = FilterService::applySearch($query, $filters['search'], $searchFields);
        }
        
        if (!empty($filters)) {
            $query = FilterService::applyNumericFilters($query, $filters, $numericFields);
            $query = FilterService::applyDateFilters($query, $filters, $dateFields);
            $query = FilterService::applyExactMatchFilters($query, $filters, $exactMatchFields);
            $query = FilterService::applyInFilters($query, $filters, $inFields);
        }

        $defaultSortField = $filters['sort_field'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        $allowedSortFields = array_merge($searchFields, $numericFields, $dateFields, $exactMatchFields, $inFields);
        $sortField = in_array($filters['sort_field'] ?? $defaultSortField, $allowedSortFields)
            ? ($filters['sort_field'] ?? $defaultSortField)
            : $defaultSortField;

        if ($withPagination == true) {
            return FilterService::applySorting($query, $sortField, $sortOrder)->paginate($filters['per_page'] ?? 20);
        } else {
            return FilterService::applySorting($query, $sortField, $sortOrder);
        }
    }

    protected static function applySearch(Builder $query, $search = null, array $fields = [])
    {
        if ($search) {
            $query->where(function ($q) use ($search, $fields, $query) {
                foreach ($fields as $field) {
                    if (is_array($field)) {
                        if (strpos($field[0], '.') !== false && strpos($field[1], '.') !== false) {
                            [$relation, $relationField1] = explode('.', $field[0], 2);
                            [$relation, $relationField2] = explode('.', $field[1], 2);

                            $q->orWhereHas($relation, function ($query) use ($relationField1, $relationField2, $search) {
                                $query->whereRaw("CONCAT({$relationField1}, ' ', {$relationField2}) LIKE ?", ["%$search%"]);
                            });
                        } else {
                            $q->orWhereRaw("CONCAT({$field[0]}, ' ', {$field[1]}) LIKE ?", ["%$search%"]);
                        }
                    } elseif (strpos($field, '.') !== false) {
                        [$relation, $relationField] = static::parseField($field, $query);
                        $q->orWhereHas($relation, function ($query) use ($relationField, $search) {
                            $query->where($relationField, 'like', "%$search%");
                        });
                    } else {
                        $q->orWhere($field, 'like', "%$search%");
                    }
                }
            });
        }
        return $query;
    }

    protected static function applyNumericFilters(Builder $query, array $filters, array $numericFields = [])
    {
        foreach ($numericFields as $field) {
            if (preg_match('/^(AVG|SUM|COUNT|MIN|MAX)\\((.*)\\)$/i', $field, $matches)) {
                $function = strtoupper($matches[1]);
                $column = $matches[2];
                [$relation, $relationField] = static::parseField($column, $query);

                if ($relation) {
                    $query->whereHas($relation, function ($q) use ($function, $relationField, $filters, $field) {
                        $q->selectRaw("{$function}({$relationField}) as {$function}_{$relationField}");

                        if (isset($filters["{$function}_{$relationField}_min"])) {
                            $q->having("{$function}_{$relationField}", '>=', $filters["{$function}_{$relationField}_min"]);
                        }
                        if (isset($filters["{$function}_{$relationField}_max"])) {
                            $q->having("{$function}_{$relationField}", '<=', $filters["{$function}_{$relationField}_max"]);
                        }
                    });
                } else {
                    $query->selectRaw("{$function}({$column}) as {$function}_{$column}");

                    if (isset($filters["{$function}_{$column}_min"])) {
                        $query->having("{$function}_{$column}", '>=', $filters["{$function}_{$column}_min"]);
                    }
                    if (isset($filters["{$function}_{$column}_max"])) {
                        $query->having("{$function}_{$column}", '<=', $filters["{$function}_{$column}_max"]);
                    }
                }
            } else {
                [$relation, $relationField] = static::parseField($field, $query);

                if ($relation) {
                    if (isset($filters["{$field}_min"])) {
                        $query->whereHas($relation, function ($q) use ($relationField, $filters, $field) {
                            $q->where($relationField, '>=', $filters["{$field}_min"]);
                        });
                    }
                    if (isset($filters["{$field}_max"])) {
                        $query->whereHas($relation, function ($q) use ($relationField, $filters, $field) {
                            $q->where($relationField, '<=', $filters["{$field}_max"]);
                        });
                    }
                } else {
                    if (isset($filters["{$field}_min"])) {
                        $query->where($field, '>=', $filters["{$field}_min"]);
                    }
                    if (isset($filters["{$field}_max"])) {
                        $query->where($field, '<=', $filters["{$field}_max"]);
                    }
                }
            }
        }
        return $query;
    }


    protected static function applyDateFilters(Builder $query, array $filters, array $dateFields = [])
    {
        foreach ($dateFields as $field) {
            [$relation, $relationField] = static::parseField($field, $query);

            if ($relation) {
                if (isset($filters["{$field}_from"])) {
                    $query->whereHas($relation, function ($q) use ($relationField, $filters, $field) {
                        $q->whereDate($relationField, '>=', $filters["{$field}_from"]);
                    });
                }
                if (isset($filters["{$field}_to"])) {
                    $query->whereHas($relation, function ($q) use ($relationField, $filters, $field) {
                        $q->whereDate($relationField, '<=', $filters["{$field}_to"]);
                    });
                }
            } else {
                if (isset($filters["{$field}_from"])) {
                    $query->whereDate($field, '>=', $filters["{$field}_from"]);
                }
                if (isset($filters["{$field}_to"])) {
                    $query->whereDate($field, '<=', $filters["{$field}_to"]);
                }
            }
        }
        return $query;
    }

    protected static function applyExactMatchFilters(Builder $query, array $filters, array $exactMatchFields = [])
    {
        foreach ($exactMatchFields as $field) {
            [$relation, $relationField] = static::parseField($field, $query);

            if ($relation) {
                if (isset($filters[$field])) {
                    $query->whereHas($relation, function ($q) use ($relationField, $filters, $field) {
                        $q->where($relationField, $filters[$field]);
                    });
                }
            } else {
                if (isset($filters[$field])) {
                    $query->where($field, $filters[$field]);
                }
            }
        }
        return $query;
    }

    protected static function applyInFilters(Builder $query, array $filters, array $inFields = [])
    {
        foreach ($inFields as $field) {
            [$relation, $relationField] = static::parseField($field, $query);

            $inKey = "in_{$field}";
            if (isset($filters[$inKey]) && is_array($filters[$inKey])) {
                if ($relation) {
                    $query->whereHas($relation, function ($q) use ($relationField, $filters, $inKey) {
                        $q->whereIn($relationField, $filters[$inKey]);
                    });
                } else {
                    $query->whereIn($field, $filters[$inKey]);
                }
            }

            $notInKey = "not_in_{$field}";
            if (isset($filters[$notInKey]) && is_array($filters[$notInKey])) {
                if ($relation) {
                    $query->whereDoesntHave($relation, function ($q) use ($relationField, $filters, $notInKey) {
                        $q->whereIn($relationField, $filters[$notInKey]);
                    });
                } else {
                    $query->whereNotIn($field, $filters[$notInKey]);
                }
            }
        }
        return $query;
    }

    protected static function applySorting(Builder $query, $sortField, $sortOrder)
    {
        $sortOrder = in_array($sortOrder, ['asc', 'desc']) ? $sortOrder : 'desc';
        [$relation, $relationField] = static::parseField($sortField, $query);

        if ($relation) {
            $query->with([$relation => function ($q) use ($relationField, $sortOrder) {
                $q->orderBy($relationField, $sortOrder);
            }]);
        } else {
            $query->orderBy($sortField, $sortOrder);
        }
        return $query;
    }

    protected static function parseField(string $field, Builder $query): array
    {
        if (strpos($field, '.') !== false) {
            $relations = explode('.', $field);
            $mainRelation = array_shift($relations);
            $finalField = array_pop($relations);
            $nestedRelations = implode('.', $relations);

            if (method_exists($query->getModel(), $mainRelation)) {
                return [$mainRelation . ($nestedRelations ? '.' . $nestedRelations : ''), $finalField];
            }
        }

        return [null, $field];
    }
}
