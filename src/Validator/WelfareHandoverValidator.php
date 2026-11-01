<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\AdoptionApplication;
use App\Welfare\WelfareChecker;
use App\Welfare\WelfareIssue;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class WelfareHandoverValidator extends ConstraintValidator
{
    public function __construct(private readonly WelfareChecker $welfareChecker)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof WelfareHandover) {
            throw new UnexpectedTypeException($constraint, WelfareHandover::class);
        }

        if (!$value instanceof AdoptionApplication) {
            return;
        }

        $listing = $value->getListing();
        $handoverDate = $value->getHandoverDate();

        if (null === $listing || null === $listing->getBirthDate() || null === $handoverDate) {
            return;
        }

        $report = $this->welfareChecker->check(
            $listing->getSpecies(),
            $listing->getBirthDate(),
            $handoverDate,
        );

        foreach ($report->errors as $issue) {
            $this->context->buildViolation($this->messageFor($issue))
                ->atPath('handoverDate')
                ->addViolation();
        }

        if ($report->requiresConfirmation() && !$value->isWarningConfirmed()) {
            $this->context->buildViolation($this->messageFor(WelfareIssue::HandoverBeforeRecommendedAge).' Vous devez confirmer ce choix.')
                ->atPath('warningConfirmed')
                ->addViolation();
        }
    }

    private function messageFor(WelfareIssue $issue): string
    {
        return match ($issue) {
            WelfareIssue::BirthDateInFuture => 'La date de naissance est dans le futur.',
            WelfareIssue::HandoverInPast => 'La date de remise est déjà passée.',
            WelfareIssue::HandoverBeforeWeaning => 'La remise ne peut pas avoir lieu avant le sevrage (8 semaines minimum).',
            WelfareIssue::HandoverBeforeRecommendedAge => 'La remise a lieu avant l\'âge recommandé par nos règles de bien-être.',
        };
    }
}
