<?php

namespace App\Enums;

enum ClientDocumentType: string
{
    case DRIVER_LICENSE_FRONT = 'driver_license_front';
    case DRIVER_LICENSE_BACK = 'driver_license_back';
    case ID_CARD_FRONT = 'id_card_front';
    case ID_CARD_BACK = 'id_card_back';
    case PASSPORT = 'passport';

    public function label(): string
    {
        return match ($this) {
            self::DRIVER_LICENSE_FRONT => 'Driver License Front',
            self::DRIVER_LICENSE_BACK => 'Driver License Back',
            self::ID_CARD_FRONT => 'ID Card Front',
            self::ID_CARD_BACK => 'ID Card Back',
            self::PASSPORT => 'Passport',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::DRIVER_LICENSE_FRONT => 'Upload the front side of the driver license.',
            self::DRIVER_LICENSE_BACK => 'Upload the back side of the driver license if available.',
            self::ID_CARD_FRONT => 'Upload the front side of the national ID card.',
            self::ID_CARD_BACK => 'Upload the back side of the national ID card if available.',
            self::PASSPORT => 'Upload the passport identification page.',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(static fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'description' => $case->description(),
        ], self::cases());
    }
}
