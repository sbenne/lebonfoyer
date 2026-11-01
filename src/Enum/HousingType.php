<?php

declare(strict_types=1);

namespace App\Enum;

enum HousingType: string
{
    case Appartement = 'appartement';
    case Maison = 'maison';

    public function label(): string
    {
        return match ($this) {
            self::Appartement => 'Appartement',
            self::Maison => 'Maison',
        };
    }
}
