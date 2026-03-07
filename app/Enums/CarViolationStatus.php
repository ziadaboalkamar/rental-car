<?php

namespace App\Enums;

enum CarViolationStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case DISPUTED = 'disputed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PAID => 'Paid',
            self::DISPUTED => 'Disputed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => '#F59E0B',
            self::PAID => '#10B981',
            self::DISPUTED => '#3B82F6',
            self::CANCELLED => '#6B7280',
        };
    }
}

