<?php

declare(strict_types=1);

namespace App\Enum;

enum UserType: string
{
    case Adoptant = 'adoptant';
    case Cedant = 'cedant';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Adoptant => 'Adoptant',
            self::Cedant => 'Cédant',
            self::Admin => 'Administrateur',
        };
    }

    public function role(): string
    {
        return match ($this) {
            self::Adoptant => 'ROLE_ADOPTANT',
            self::Cedant => 'ROLE_CEDANT',
            self::Admin => 'ROLE_ADMIN',
        };
    }
}
