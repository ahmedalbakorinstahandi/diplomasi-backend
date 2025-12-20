<?php

namespace App\Http\Services\System;

use App\Http\Permissions\System\SettingPermission;
use App\Models\System\Setting;
use App\Services\FilterService;
use App\Services\MessageService;

class SettingService
{
    public function index($filters = [])
    {
        $query = Setting::query();

        $filters['per_page'] = $filters['per_page'] ?? 100;
        $filters['sort_field'] = $filters['sort_field'] ?? 'key_name';
        $filters['sort_order'] = $filters['sort_order'] ?? 'asc';

        $searchFields = ['key_name', 'value'];
        $numericFields = [];
        $dateFields = ['created_at'];
        $exactMatchFields = ['type', 'is_settings'];
        $inFields = ['type'];

        $query = SettingPermission::filterIndex($query);

        $query = FilterService::applyFilters(
            $query,
            $filters,
            $searchFields,
            $numericFields,
            $dateFields,
            $exactMatchFields,
            $inFields
        );

        return $query;
    }

    public function show($idOrKey)
    {
        $setting = is_numeric($idOrKey)
            ? Setting::where('id', $idOrKey)->first()
            : Setting::where('key_name', $idOrKey)->first();

        if (!$setting) {
            MessageService::abort(404, 'messages.setting.not_found');
        }

        return $setting;
    }

    public function create($data)
    {
        $setting = Setting::create($data);

        return $setting;
    }

    public function update($data, $setting)
    {
        $setting->update($data);

        return $setting;
    }

    public function delete($setting)
    {
        $setting->delete();
    }

    public function getByKey(string $key)
    {
        return $this->show($key);
    }

    public function getValue(string $key, $default = null)
    {
        return Setting::getValue($key, $default);
    }

    public function setValue(string $key, $value, string $type = 'text')
    {
        Setting::setValue($key, $value, $type);

        return $this->show($key);
    }

    public function updateMany($data)
    {
        $updated = [];

        foreach ($data as $key => $value) {
            if (is_array($value) && isset($value['value'])) {
                $type = $value['type'] ?? 'text';
                $updated[] = $this->setValue($key, $value['value'], $type);
            } else {
                $updated[] = $this->setValue($key, $value);
            }
        }

        return $updated;
    }
}
