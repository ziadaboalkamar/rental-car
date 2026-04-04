<?php

namespace App\Support;

class BranchLocationOptions
{
    /**
     * @return array<int, array{value:string,label:string}>
     */
    public static function countrySelectOptions(string $locale = 'en'): array
    {
        $isArabic = str_starts_with(strtolower($locale), 'ar');

        return collect(CountryOptions::all())
            ->map(fn (array $country) => [
                'value' => (string) $country['iso2'],
                'label' => $isArabic ? (string) $country['name_ar'] : (string) $country['name_en'],
            ])
            ->sortBy('label')
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<int, array{value:string,label:string}>>
     */
    public static function cityOptionsByCountry(string $locale = 'en'): array
    {
        $isArabic = str_starts_with(strtolower($locale), 'ar');

        return collect(self::cityMap())
            ->mapWithKeys(fn (array $cities, string $countryCode) => [
                $countryCode => collect($cities)
                    ->map(fn (array $city) => [
                        'value' => (string) $city['en'],
                        'label' => $isArabic ? (string) $city['ar'] : (string) $city['en'],
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    public static function cityOptionsForCountry(?string $countryCode, string $locale = 'en'): array
    {
        $code = strtoupper(trim((string) $countryCode));
        if ($code === '') {
            return [];
        }

        return self::cityOptionsByCountry($locale)[$code] ?? [];
    }

    public static function countryNameForCode(?string $countryCode, string $locale = 'en'): ?string
    {
        $code = strtoupper(trim((string) $countryCode));
        if ($code === '') {
            return null;
        }

        foreach (CountryOptions::all() as $country) {
            if (($country['iso2'] ?? '') !== $code) {
                continue;
            }

            return str_starts_with(strtolower($locale), 'ar')
                ? (string) $country['name_ar']
                : (string) $country['name_en'];
        }

        return $code;
    }

    /**
     * @return array<string, array<int, array{en:string,ar:string}>>
     */
    private static function cityMap(): array
    {
        static $cityMap = null;

        if (is_array($cityMap)) {
            return $cityMap;
        }

        $path = resource_path('data/branch-cities.json');
        if (!is_file($path)) {
            return $cityMap = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return $cityMap = [];
        }

        $cityMap = collect($decoded)
            ->mapWithKeys(function (mixed $cities, mixed $countryCode): array {
                $normalizedCode = strtoupper(trim((string) $countryCode));
                if ($normalizedCode === '' || !is_array($cities)) {
                    return [];
                }

                $normalizedCities = collect($cities)
                    ->filter(fn (mixed $city): bool => is_array($city) && !empty($city['en']))
                    ->map(fn (array $city): array => [
                        'en' => trim((string) ($city['en'] ?? '')),
                        'ar' => trim((string) ($city['ar'] ?? $city['en'] ?? '')),
                    ])
                    ->filter(fn (array $city): bool => $city['en'] !== '')
                    ->values()
                    ->all();

                return $normalizedCities === [] ? [] : [$normalizedCode => $normalizedCities];
            })
            ->all();

        return $cityMap;
    }
}
