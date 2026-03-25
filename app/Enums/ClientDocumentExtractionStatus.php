<?php

namespace App\Enums;

enum ClientDocumentExtractionStatus: string
{
    case NOT_REQUESTED = 'not_requested';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case REVIEWED = 'reviewed';

    public function label(): string
    {
        return match ($this) {
            self::NOT_REQUESTED => 'Not Requested',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::REVIEWED => 'Reviewed',
        };
    }
}
