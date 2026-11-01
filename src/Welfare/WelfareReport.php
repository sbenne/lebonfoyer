<?php

declare(strict_types=1);

namespace App\Welfare;

final readonly class WelfareReport
{
    /**
     * @param list<WelfareIssue> $errors
     * @param list<WelfareIssue> $warnings
     */
    public function __construct(
        public array $errors = [],
        public array $warnings = [],
    ) {
    }

    public function isAllowed(): bool
    {
        return [] === $this->errors;
    }

    public function requiresConfirmation(): bool
    {
        return $this->isAllowed() && [] !== $this->warnings;
    }
}
