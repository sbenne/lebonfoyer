<?php

declare(strict_types=1);

namespace App\Welfare;

use App\Animal\Species;
use Psr\Clock\ClockInterface;

/**
 * Règles de bien-être : sevrage et dates de remise.
 * Une réservation peut précéder le sevrage; la remise, jamais.
 */
final class WelfareChecker
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function earliestHandoverDate(Species $species, \DateTimeImmutable $birthDate): \DateTimeImmutable
    {
        return $this->day($birthDate)->modify(sprintf('+%d weeks', $species->minWeaningWeeks()));
    }

    public function recommendedHandoverDate(Species $species, \DateTimeImmutable $birthDate): \DateTimeImmutable
    {
        return $this->day($birthDate)->modify(sprintf('+%d weeks', $species->recommendedWeaningWeeks()));
    }

    public function check(Species $species, \DateTimeImmutable $birthDate, \DateTimeImmutable $handoverDate): WelfareReport
    {
        $today = $this->day($this->clock->now());
        $birth = $this->day($birthDate);
        $handover = $this->day($handoverDate);

        $errors = [];
        $warnings = [];

        if ($birth > $today) {
            $errors[] = WelfareIssue::BirthDateInFuture;
        }
        if ($handover < $today) {
            $errors[] = WelfareIssue::HandoverInPast;
        }

        if ($handover < $this->earliestHandoverDate($species, $birth)) {
            $errors[] = WelfareIssue::HandoverBeforeWeaning;
        } elseif ($handover < $this->recommendedHandoverDate($species, $birth)) {
            $warnings[] = WelfareIssue::HandoverBeforeRecommendedAge;
        }

        return new WelfareReport($errors, $warnings);
    }

    private function day(\DateTimeInterface $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
    }
}
