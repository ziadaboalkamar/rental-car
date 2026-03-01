<?php

namespace App\Core;

use App\Models\SiteSetting;

class AiAutomationSettings
{
    public const KEY = 'ai_automation';

    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'contracts_extraction_enabled' => false,
        ];
    }

    public static function normalize(?array $data): array
    {
        $settings = array_replace(self::defaults(), is_array($data) ? $data : []);

        return [
            'enabled' => (bool) ($settings['enabled'] ?? false),
            'contracts_extraction_enabled' => (bool) ($settings['contracts_extraction_enabled'] ?? false),
        ];
    }

    public static function load(): array
    {
        $stored = SiteSetting::query()
            ->where('key', self::KEY)
            ->value('value');

        return self::normalize(is_array($stored) ? $stored : null);
    }

    public static function isContractsExtractionEnabled(): bool
    {
        $settings = self::load();

        return (bool) ($settings['enabled'] && $settings['contracts_extraction_enabled']);
    }
}

