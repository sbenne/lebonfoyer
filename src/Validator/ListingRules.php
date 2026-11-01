<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class ListingRules extends Constraint
{
    public string $message = 'L\'annonce est incomplète.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
