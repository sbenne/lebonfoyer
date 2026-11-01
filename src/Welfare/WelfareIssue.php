<?php

declare(strict_types=1);

namespace App\Welfare;

enum WelfareIssue: string
{
    // Bloquants
    case BirthDateInFuture = 'birth_date_in_future';
    case HandoverInPast = 'handover_in_past';
    case HandoverBeforeWeaning = 'handover_before_weaning';

    // Avertissement (à confirmer explicitement par le cédant)
    case HandoverBeforeRecommendedAge = 'handover_before_recommended_age';

    public function isBlocking(): bool
    {
        return self::HandoverBeforeRecommendedAge !== $this;
    }
}
