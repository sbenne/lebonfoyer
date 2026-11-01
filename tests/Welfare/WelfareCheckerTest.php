<?php

declare(strict_types=1);

namespace App\Tests\Welfare;

use App\Animal\Species;
use App\Welfare\WelfareChecker;
use App\Welfare\WelfareIssue;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class WelfareCheckerTest extends TestCase
{
    private WelfareChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new WelfareChecker(new MockClock('2026-11-01 12:00:00'));
    }

    private function d(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date);
    }

    public function testEarliestAndRecommendedDates(): void
    {
        $birth = $this->d('2026-09-01');
        self::assertSame('2026-10-27', $this->checker->earliestHandoverDate(Species::Cat, $birth)->format('Y-m-d'));
        self::assertSame('2026-11-24', $this->checker->recommendedHandoverDate(Species::Cat, $birth)->format('Y-m-d'));
        self::assertSame('2026-10-27', $this->checker->recommendedHandoverDate(Species::Dog, $birth)->format('Y-m-d'));
    }

    public function testCatBeforeEightWeeksIsBlocked(): void
    {
        // né le 2026-09-20 : 6 semaines au 2026-11-01
        $report = $this->checker->check(Species::Cat, $this->d('2026-09-20'), $this->d('2026-11-01'));
        self::assertFalse($report->isAllowed());
        self::assertContains(WelfareIssue::HandoverBeforeWeaning, $report->errors);
    }

    public function testCatBetweenEightAndTwelveWeeksNeedsConfirmation(): void
    {
        // né le 2026-09-06 : exactement 8 semaines au 2026-11-01
        $report = $this->checker->check(Species::Cat, $this->d('2026-09-06'), $this->d('2026-11-01'));
        self::assertTrue($report->isAllowed());
        self::assertTrue($report->requiresConfirmation());
        self::assertContains(WelfareIssue::HandoverBeforeRecommendedAge, $report->warnings);
    }

    public function testCatAtTwelveWeeksHasNoWarning(): void
    {
        // né le 2026-08-09 : 12 semaines au 2026-11-01
        $report = $this->checker->check(Species::Cat, $this->d('2026-08-09'), $this->d('2026-11-01'));
        self::assertTrue($report->isAllowed());
        self::assertFalse($report->requiresConfirmation());
    }

    public function testDogAtEightWeeksIsAllowedWithoutWarning(): void
    {
        $report = $this->checker->check(Species::Dog, $this->d('2026-09-06'), $this->d('2026-11-01'));
        self::assertTrue($report->isAllowed());
        self::assertSame([], $report->warnings);
    }

    public function testReservationBeforeWeaningIsFineIfHandoverIsAfter(): void
    {
        // Chiot né il y a 5 semaines; remise prévue dans 4 semaines (9 semaines d'âge)
        $report = $this->checker->check(Species::Dog, $this->d('2026-09-27'), $this->d('2026-11-29'));
        self::assertTrue($report->isAllowed());
    }

    public function testBirthDateInFutureIsBlocked(): void
    {
        $report = $this->checker->check(Species::Cat, $this->d('2026-11-10'), $this->d('2027-03-01'));
        self::assertContains(WelfareIssue::BirthDateInFuture, $report->errors);
    }

    public function testHandoverInPastIsBlocked(): void
    {
        $report = $this->checker->check(Species::Dog, $this->d('2026-01-01'), $this->d('2026-10-01'));
        self::assertContains(WelfareIssue::HandoverInPast, $report->errors);
    }
}
