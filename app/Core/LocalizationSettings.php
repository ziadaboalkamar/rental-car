<?php

namespace App\Core;

use App\Models\SiteSetting;

class LocalizationSettings
{
    public const KEY = 'localization_settings';

    public static function defaults(): array
    {
        $supported = (array) config('laravellocalization.supportedLocales', []);
        $rows = [];

        foreach ($supported as $code => $meta) {
            $rows[] = [
                'code' => (string) $code,
                'name' => trim((string) ($meta['name'] ?? strtoupper((string) $code))),
                'native' => trim((string) ($meta['native'] ?? strtoupper((string) $code))),
                'regional' => trim((string) ($meta['regional'] ?? '')),
                'script' => trim((string) ($meta['script'] ?? '')),
                'direction' => self::normalizeDirection($meta['direction'] ?? null, (string) $code),
            ];
        }

        if (empty($rows)) {
            $rows[] = [
                'code' => 'en',
                'name' => 'English',
                'native' => 'English',
                'regional' => 'en_US',
                'script' => 'Latn',
                'direction' => 'ltr',
            ];
        }

        return [
            'default_locale' => self::pickDefaultLocale((string) config('app.locale', 'en'), $rows),
            'locales' => $rows,
        ];
    }

    public static function normalize(?array $data): array
    {
        $settings = array_replace_recursive(self::defaults(), is_array($data) ? $data : []);
        $inputRows = is_array($settings['locales'] ?? null) ? $settings['locales'] : [];

        $rows = [];
        $seen = [];
        foreach ($inputRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $code = self::normalizeLocaleCode((string) ($row['code'] ?? ''));
            if ($code === '' || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;

            $rows[] = [
                'code' => $code,
                'name' => trim((string) ($row['name'] ?? strtoupper($code))),
                'native' => trim((string) ($row['native'] ?? strtoupper($code))),
                'regional' => trim((string) ($row['regional'] ?? '')),
                'script' => trim((string) ($row['script'] ?? '')),
                'direction' => self::normalizeDirection($row['direction'] ?? null, $code),
            ];
        }

        if (empty($rows)) {
            $rows = self::defaults()['locales'];
        }

        $defaultLocale = self::pickDefaultLocale((string) ($settings['default_locale'] ?? ''), $rows);

        return [
            'default_locale' => $defaultLocale,
            'locales' => $rows,
        ];
    }

    public static function load(): array
    {
        $stored = SiteSetting::query()
            ->where('key', self::KEY)
            ->value('value');

        return self::normalize(is_array($stored) ? $stored : null);
    }

    public static function toLaravelLocalizationConfig(array $settings): array
    {
        $normalized = self::normalize($settings);
        $supported = [];

        foreach ($normalized['locales'] as $row) {
            $supported[$row['code']] = [
                'name' => $row['name'],
                'native' => $row['native'],
                'regional' => $row['regional'] !== '' ? $row['regional'] : self::regionalFromCode($row['code']),
                'script' => $row['script'] !== '' ? $row['script'] : self::scriptFromDirection($row['direction']),
            ];
        }

        return $supported;
    }

    public static function localeCodes(array $settings): array
    {
        $normalized = self::normalize($settings);

        return array_values(array_map(
            fn (array $row): string => (string) $row['code'],
            $normalized['locales']
        ));
    }

    public static function defaultLocale(array $settings): string
    {
        return (string) self::normalize($settings)['default_locale'];
    }

    private static function pickDefaultLocale(string $candidate, array $rows): string
    {
        $candidate = self::normalizeLocaleCode($candidate);
        $codes = array_values(array_map(fn (array $row): string => (string) $row['code'], $rows));

        if ($candidate !== '' && in_array($candidate, $codes, true)) {
            return $candidate;
        }

        if (in_array('en', $codes, true)) {
            return 'en';
        }

        return (string) ($codes[0] ?? 'en');
    }

    private static function normalizeLocaleCode(string $code): string
    {
        $code = trim(str_replace('_', '-', $code));
        if ($code === '') {
            return '';
        }

        $parts = array_values(array_filter(explode('-', $code), fn ($part) => $part !== ''));
        if (empty($parts)) {
            return '';
        }

        $lang = strtolower($parts[0]);
        if (!preg_match('/^[a-z]{2,3}$/', $lang)) {
            return '';
        }

        if (count($parts) === 1) {
            return $lang;
        }

        $region = strtoupper($parts[1]);
        if (!preg_match('/^[A-Z]{2}$/', $region)) {
            return '';
        }

        return $lang.'-'.$region;
    }

    private static function normalizeDirection(mixed $value, string $code): string
    {
        $value = strtolower(trim((string) ($value ?? '')));
        if (in_array($value, ['ltr', 'rtl'], true)) {
            return $value;
        }

        return str_starts_with($code, 'ar') ? 'rtl' : 'ltr';
    }

    private static function regionalFromCode(string $code): string
    {
        if (str_contains($code, '-')) {
            return str_replace('-', '_', $code);
        }

        return strtolower($code).'_'.strtoupper($code);
    }

    private static function scriptFromDirection(string $direction): string
    {
        return $direction === 'rtl' ? 'Arab' : 'Latn';
    }
}

