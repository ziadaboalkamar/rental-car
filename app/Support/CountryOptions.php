<?php

namespace App\Support;

use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Intl\Countries;

class CountryOptions
{
    /**
     * @return array<int, array{iso2:string,name_en:string,name_ar:string,dial_code:string}>
     */
    public static function all(): array
    {
        static $countries = null;

        if (is_array($countries)) {
            return $countries;
        }

        $namesEn = Countries::getNames('en');
        $namesAr = Countries::getNames('ar');
        $phoneUtil = PhoneNumberUtil::getInstance();
        $items = [];

        foreach ($namesEn as $iso2 => $nameEn) {
            $iso = strtoupper((string) $iso2);

            if (strlen($iso) !== 2) {
                continue;
            }

            $countryCode = (int) $phoneUtil->getCountryCodeForRegion($iso);
            if ($countryCode <= 0) {
                continue;
            }

            $items[] = [
                'iso2' => $iso,
                'name_en' => $nameEn,
                'name_ar' => $namesAr[$iso] ?? $nameEn,
                'dial_code' => '+'.$countryCode,
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) $a['name_en'], (string) $b['name_en']));
        $countries = $items;

        return $countries;
    }
}
