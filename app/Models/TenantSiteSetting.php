<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use MohamedGaldi\ViltFilepond\Traits\HasFiles;

class TenantSiteSetting extends Model
{
    use HasFiles;

    protected $fillable = [
        'tenant_id',
        'site_name',
        'logo_url',
        'primary_color',
        'secondary_color',
        'hero',
        'contact',
        'footer',
    ];

    protected $casts = [
        'hero' => 'array',
        'contact' => 'array',
        'footer' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function defaults(): array
    {
        return [
            'site_name' => null,
            'logo_url' => null,
            'primary_color' => '#f97316',
            'secondary_color' => '#ea580c',
            'hero' => [
                'title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'description' => [
                    'en' => null,
                    'ar' => null,
                ],
                'button_text' => [
                    'en' => null,
                    'ar' => null,
                ],
                'button_link' => null,
            ],
            'contact' => [
                'phone' => null,
                'email' => null,
                'address' => [
                    'en' => null,
                    'ar' => null,
                ],
            ],
            'footer' => [
                'description' => [
                    'en' => null,
                    'ar' => null,
                ],
            ],
        ];
    }

    public static function normalize(?self $settings): array
    {
        $defaults = self::defaults();
        $data = $settings?->toArray() ?? [];
        $logoUrl = null;

        if ($settings) {
            $file = $settings->relationLoaded('files')
                ? $settings->files->firstWhere('collection', 'logo')
                : $settings->files()->where('collection', 'logo')->first();

            if ($file && $file->path) {
                $logoUrl = self::publicUrlFromPath($file->path);
            }
        }

        return [
            'site_name' => self::nullableString($data['site_name'] ?? $defaults['site_name']),
            'logo_url' => self::nullableString($logoUrl ?: ($data['logo_url'] ?? $defaults['logo_url'])),
            'primary_color' => self::normalizeHexColor($data['primary_color'] ?? $defaults['primary_color'], $defaults['primary_color']),
            'secondary_color' => self::normalizeHexColor($data['secondary_color'] ?? $defaults['secondary_color'], $defaults['secondary_color']),
            'hero' => [
                'title' => [
                    'en' => self::nullableString(data_get($data, 'hero.title.en')),
                    'ar' => self::nullableString(data_get($data, 'hero.title.ar')),
                ],
                'description' => [
                    'en' => self::nullableString(data_get($data, 'hero.description.en')),
                    'ar' => self::nullableString(data_get($data, 'hero.description.ar')),
                ],
                'button_text' => [
                    'en' => self::nullableString(data_get($data, 'hero.button_text.en')),
                    'ar' => self::nullableString(data_get($data, 'hero.button_text.ar')),
                ],
                'button_link' => self::nullableString(data_get($data, 'hero.button_link')),
            ],
            'contact' => [
                'phone' => self::nullableString(data_get($data, 'contact.phone')),
                'email' => self::nullableString(data_get($data, 'contact.email')),
                'address' => [
                    'en' => self::nullableString(data_get($data, 'contact.address.en')),
                    'ar' => self::nullableString(data_get($data, 'contact.address.ar')),
                ],
            ],
            'footer' => [
                'description' => [
                    'en' => self::nullableString(data_get($data, 'footer.description.en')),
                    'ar' => self::nullableString(data_get($data, 'footer.description.ar')),
                ],
            ],
        ];
    }

    public static function forTenant(Tenant $tenant): array
    {
        return self::normalize($tenant->siteSetting);
    }

    public static function publicUrlFromPath(?string $path): ?string
    {
        $path = trim((string) ($path ?? ''));
        if ($path === '') {
            return null;
        }

        $normalized = ltrim($path, '/');
        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        return Storage::url($normalized);
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private static function normalizeHexColor(mixed $value, string $fallback): string
    {
        $value = trim((string) ($value ?? ''));

        if ($value !== '' && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
            return strtolower($value);
        }

        return strtolower($fallback);
    }
}
