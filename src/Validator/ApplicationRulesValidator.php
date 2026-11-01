<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\AdoptionApplication;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ApplicationRulesValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ApplicationRules) {
            throw new UnexpectedTypeException($constraint, ApplicationRules::class);
        }

        if (!$value instanceof AdoptionApplication) {
            return;
        }

        $listing = $value->getListing();
        $adoptant = $value->getAdoptant();

        if (null === $listing || null === $adoptant) {
            $this->context->buildViolation('La demande est incomplète.')->addViolation();

            return;
        }

        if (!$listing->isVisible()) {
            $this->context->buildViolation('Cette annonce n\'est plus disponible.')
                ->atPath('listing')
                ->addViolation();
        }

        if ($listing->getCedant()?->getId() === $adoptant->getId()) {
            $this->context->buildViolation('Vous ne pouvez pas adopter votre propre animal.')
                ->atPath('adoptant')
                ->addViolation();
        }

        if (!$adoptant->isVerified()) {
            $this->context->buildViolation('Votre adresse email doit être vérifiée avant de réserver.')
                ->atPath('adoptant')
                ->addViolation();
        }

        if (null === $value->getHandoverDate()) {
            $this->context->buildViolation('Choisissez une date de remise.')
                ->atPath('handoverDate')
                ->addViolation();
        }
    }
}
