<?php

declare(strict_types=1);

namespace App\Enum;

enum ApplicationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case HandedOver = 'handed_over';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Approved => 'Acceptée',
            self::Rejected => 'Refusée',
            self::Cancelled => 'Annulée',
            self::HandedOver => 'Remise effectuée',
        };
    }

    public function isActive(): bool
    {
        return \in_array($this, [self::Pending, self::Approved], true);
    }
}
