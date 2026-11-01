<?php

declare(strict_types=1);

namespace App\Twig;

use App\Animal\Species;
use App\Enum\ApplicationStatus;
use App\Enum\CedantStatus;
use App\Enum\HousingType;
use App\Enum\ListingStatus;
use App\Enum\UserType;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    private const MONTHS = [
        1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    ];

    public function __construct(private readonly \App\Welfare\WelfareChecker $welfareChecker)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('age', $this->age(...)),
            new TwigFilter('species_label', $this->speciesLabel(...)),
            new TwigFilter('listing_status_label', $this->listingStatusLabel(...)),
            new TwigFilter('application_status_label', $this->applicationStatusLabel(...)),
            new TwigFilter('user_type_label', static fn (UserType $type): string => $type->label()),
            new TwigFilter('cedant_status_label', static fn (CedantStatus $status): string => $status->label()),
            new TwigFilter('housing_label', static fn (?HousingType $type): string => $type?->label() ?? 'Non renseigné'),
            new TwigFilter('price_label', $this->priceLabel(...)),
            new TwigFilter('fr_date', $this->formatDate(...)),
            new TwigFilter('fr_datetime', $this->formatDateTime(...)),
            new TwigFilter('time_ago', $this->timeAgo(...)),
            new TwigFilter('initials', $this->initials(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('species_label', $this->speciesLabel(...)),
            new TwigFunction('current_year', static fn (): int => (int) date('Y')),
            new TwigFunction('earliest_handover', $this->earliestHandover(...)),
            new TwigFunction('recommended_handover', $this->recommendedHandover(...)),
        ];
    }

    public function earliestHandover(Species $species, \DateTimeImmutable $birthDate): \DateTimeImmutable
    {
        return $this->welfareChecker->earliestHandoverDate($species, $birthDate);
    }

    public function recommendedHandover(Species $species, \DateTimeImmutable $birthDate): \DateTimeImmutable
    {
        return $this->welfareChecker->recommendedHandoverDate($species, $birthDate);
    }

    public function age(?\DateTimeInterface $birthDate): string
    {
        if (null === $birthDate) {
            return 'âge inconnu';
        }

        $now = new \DateTimeImmutable('today');
        $birth = \DateTimeImmutable::createFromInterface($birthDate)->setTime(0, 0);
        $diff = $birth->diff($now);

        if ($diff->y > 0) {
            return sprintf('%d an%s', $diff->y, $diff->y > 1 ? 's' : '');
        }

        if ($diff->m > 0) {
            return sprintf('%d mois', $diff->m);
        }

        return sprintf('%d semaine%s', max(0, (int) floor($diff->days / 7)), $diff->days >= 14 ? 's' : '');
    }

    public function speciesLabel(Species|string $species): string
    {
        $value = $species instanceof Species ? $species : Species::from($species);

        return Species::Cat === $value ? 'Chat' : 'Chien';
    }

    public function listingStatusLabel(ListingStatus $status): string
    {
        return $status->label();
    }

    public function applicationStatusLabel(ApplicationStatus $status): string
    {
        return $status->label();
    }

    public function priceLabel(?float $price, ?CedantStatus $status): string
    {
        if (null === $price || CedantStatus::Particulier === $status) {
            return 'Don';
        }

        return number_format($price, 2, ',', ' ').' €';
    }

    public function formatDate(?\DateTimeInterface $date): string
    {
        if (null === $date) {
            return '—';
        }

        return sprintf('%d %s %d', (int) $date->format('j'), self::MONTHS[(int) $date->format('n')], (int) $date->format('Y'));
    }

    public function formatDateTime(?\DateTimeInterface $date): string
    {
        if (null === $date) {
            return '—';
        }

        return $this->formatDate($date).' à '.$date->format('H:i');
    }

    public function timeAgo(\DateTimeInterface $date): string
    {
        $diff = $date->diff(new \DateTimeImmutable());

        if ($diff->y > 0) {
            return sprintf('il y a %d an%s', $diff->y, $diff->y > 1 ? 's' : '');
        }
        if ($diff->m > 0) {
            return sprintf('il y a %d mois', $diff->m);
        }
        if ($diff->d > 0) {
            return sprintf('il y a %d jour%s', $diff->d, $diff->d > 1 ? 's' : '');
        }
        if ($diff->h > 0) {
            return sprintf('il y a %d heure%s', $diff->h, $diff->h > 1 ? 's' : '');
        }
        if ($diff->i > 0) {
            return sprintf('il y a %d minute%s', $diff->i, $diff->i > 1 ? 's' : '');
        }

        return 'à l\'instant';
    }

    public function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';
        foreach (\array_slice($words, 0, 2) as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return '' !== $initials ? $initials : '?';
    }
}
