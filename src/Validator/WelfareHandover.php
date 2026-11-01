<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class WelfareHandover extends Constraint
{
    public string $message = 'La date de remise ne respecte pas les règles de sevrage.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
