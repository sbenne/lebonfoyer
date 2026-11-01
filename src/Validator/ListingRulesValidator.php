<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\AnimalListing;
use App\Enum\CedantStatus;
use Psr\Clock\ClockInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ListingRulesValidator extends ConstraintValidator
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ListingRules) {
            throw new UnexpectedTypeException($constraint, ListingRules::class);
        }

        if (!$value instanceof AnimalListing) {
            return;
        }

        if (null === $value->getCedant()) {
            $this->context->buildViolation('Le cédant est obligatoire.')->addViolation();
        }

        if ('' === $value->getName()) {
            $this->context->buildViolation('Le nom de l\'animal est obligatoire.')->atPath('name')->addViolation();
        }

        $birthDate = $value->getBirthDate();
        if (null === $birthDate) {
            $this->context->buildViolation('La date de naissance est obligatoire.')->atPath('birthDate')->addViolation();
        } elseif ($birthDate > $this->clock->now()->setTime(0, 0)) {
            $this->context->buildViolation('La date de naissance ne peut pas être dans le futur.')->atPath('birthDate')->addViolation();
        }

        if ('' === $value->getIdentificationNumber()) {
            $this->context->buildViolation('Le numéro d\'identification (puce ou tatouage) est obligatoire.')
                ->atPath('identificationNumber')
                ->addViolation();
        } elseif (1 !== preg_match('/^(?:\d{15}|[A-Z]{3}\d{3,}[A-Z0-9]*)$/i', $value->getIdentificationNumber())) {
            $this->context->buildViolation('Numéro d\'identification invalide : 15 chiffres (puce) ou format tatouage.')
                ->atPath('identificationNumber')
                ->addViolation();
        }

        if ('' === $value->getCity() || '' === $value->getPostalCode()) {
            $this->context->buildViolation('La ville et le code postal sont obligatoires.')->atPath('city')->addViolation();
        }

        $cedant = $value->getCedant();
        if (null !== $cedant) {
            $status = $cedant->getCedantStatus();

            if (CedantStatus::Particulier === $status && null !== $value->getPrice()) {
                $this->context->buildViolation('Un particulier ne peut pas demander de prix : le don est obligatoire.')
                    ->atPath('price')
                    ->addViolation();
            }

            if (CedantStatus::Association === $status && null === $cedant->getRna() && null === $cedant->getSiren()) {
                $this->context->buildViolation('Une association doit renseigner son numéro RNA ou SIREN.')
                    ->atPath('cedant')
                    ->addViolation();
            }

            if (CedantStatus::Professionnel === $status) {
                if (null === $cedant->getSiren()) {
                    $this->context->buildViolation('Un professionnel doit renseigner son SIREN.')
                        ->atPath('cedant')
                        ->addViolation();
                }
                if (null === $cedant->getBreedingNumber()) {
                    $this->context->buildViolation('Un professionnel doit renseigner son numéro de portée/élevage.')
                        ->atPath('cedant')
                        ->addViolation();
                }
            }

            if (null !== $value->getPrice() && $value->getPrice() < 0) {
                $this->context->buildViolation('Le prix ne peut pas être négatif.')->atPath('price')->addViolation();
            }
        }
    }
}
