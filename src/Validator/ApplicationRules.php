<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class ApplicationRules extends Constraint
{
    public string $message = 'La demande d\'adoption est invalide.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
