<?php

namespace App\Services;

use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneService
{
    public static function passes($attribute, $value)
    {
        $phoneUtil = PhoneNumberUtil::getInstance();

        try {
            $numberProto = $phoneUtil->parse($attribute, null);
            $res = $phoneUtil->isValidNumber($numberProto);
            if (!$res) {
                MessageService::abort(
                    422,
                    'messages.phone.invalid',
                );
            }
            return true;
        } catch (\Exception $e) {
            MessageService::abort(
                422,
                'messages.phone.invalid',
            );
        }
    }

    public static function parsePhoneParts($rawPhone)
    {
        $rawPhone = str_replace(' ', '', $rawPhone);

        PhoneService::passes($rawPhone, null);

        $phoneUtil = PhoneNumberUtil::getInstance();
        $number = $phoneUtil->parse($rawPhone, null);

        return [
            'country_code' => $number->getCountryCode(),
            'national_number' => $number->getNationalNumber(),
            'formatted' => $phoneUtil->format($number, PhoneNumberFormat::E164),
        ];
    }
}
