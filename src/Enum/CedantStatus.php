<?php

declare(strict_types=1);

namespace App\Enum;

enum CedantStatus: string
{
    case Particulier = 'particulier';
    case Association = 'association';
    case Professionnel = 'professionnel';

    public function label(): string
    {
        return match ($this) {
            self::Particulier => 'Particulier',
            self::Association => 'Association',
            self::Professionnel => 'Professionnel',
        };
    }

    public function allowsPrice(): bool
    {
        return self::Particulier !== $this;
    }
}
