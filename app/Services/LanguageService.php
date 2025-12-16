<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class LanguageService
{


    public static function getLocale()
    {
        $acceptLanguage = request()->header('Accept-Language');
        if (!$acceptLanguage) {
            return 'en';
        }

        $languages = explode(',', $acceptLanguage);
        $primaryLang = explode(';', $languages[0])[0];

        return in_array($primaryLang, ['ar', 'en']) ? $primaryLang : 'en';
    }


    public static function getMultiLanguage()
    {
        // Get_Multi_Language
        // return request()->header('Get-Multi-Language', false);
        return true;
    }


    public static function translatableFieldRules(string $baseRule): array
    {
        $defaultLocale = config('translatable.default_locale');
        $locales = config('translatable.locales');
        $ruleParts = explode('|', $baseRule);
        $isRequired = in_array('required', $ruleParts);
        $isNullable = in_array('nullable', $ruleParts);

        $rules = [];

        if ($isRequired) {
            $rules[] = 'required';
        } elseif ($isNullable) {
            $rules[] = 'nullable';
        }

        $rules[] = 'array';

        $rules[] = function ($attribute, $value, $fail) use ($defaultLocale, $ruleParts, $isRequired, $locales) {
            if (!is_array($value)) {
                return $fail("The $attribute field must be an array.");
            }

            if ($isRequired && empty($value[$defaultLocale])) {
                return $fail("The $attribute field is required in $defaultLocale.");
            }

            $cleanRule = collect($ruleParts)->reject(fn($r) => $r === 'required' || $r === 'nullable')->implode('|');

            foreach ($locales as $locale) {
                if (isset($value[$locale]) && $value[$locale] !== null) {
                    $validator = validator([$locale => $value[$locale]], [$locale => $cleanRule]);
                    if ($validator->fails()) {
                        return $fail("Invalid value for $attribute in $locale: " . $validator->errors()->first($locale));
                    }
                }
            }
        };

        return $rules;
    }







    public static function prepareTranslatableData(array $data, $existingEntity = null): array
    {
        $translatableFields = (new $existingEntity)->getTranslatableAttributes();
        $locales = config('translatable.locales');

        foreach ($translatableFields as $field) {
            $currentTranslations = $existingEntity ? $existingEntity->getTranslations($field) : [];

            if (isset($data[$field]) && is_array($data[$field])) {
                foreach ($locales as $locale) {
                    if (array_key_exists($locale, $data[$field])) {
                        $newValue = $data[$field][$locale];


                        if ($newValue === null || $newValue === "" || trim($newValue) === "") {
                            $currentTranslations[$locale] = "";
                            continue;
                        }

                        $currentTranslations[$locale] = $newValue;
                    }
                }
            }

            $data[$field] = $currentTranslations;
        }

        return $data;
    }
}
