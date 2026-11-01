<?php

declare(strict_types=1);

namespace App\Enum;

enum ListingStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Reserved = 'reserved';
    case Closed = 'closed';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Published => 'Publiée',
            self::Reserved => 'Réservée',
            self::Closed => 'Clôturée',
            self::Suspended => 'Suspendue',
        };
    }

    public function isVisible(): bool
    {
        return \in_array($this, [self::Published, self::Reserved], true);
    }
}
