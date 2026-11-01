<?php

declare(strict_types=1);

namespace App\Animal;

enum Species: string
{
    case Cat = 'cat';
    case Dog = 'dog';

    /** Seuil légal bloquant (art. L.214-8 Code rural). */
    public function minWeaningWeeks(): int
    {
        return 8;
    }

    /** Seuil recommandé : au-delà, aucun avertissement. */
    public function recommendedWeaningWeeks(): int
    {
        return match ($this) {
            self::Cat => 12,
            self::Dog => 8,
        };
    }
}
