<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CityGeocoder
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{name: string, postalCode: string, latitude: float, longitude: float}|null
     */
    public function geocode(string $city, ?string $postalCode = null): ?array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://geo.api.gouv.fr/communes', [
                'query' => [
                    'nom' => $city,
                    'codePostal' => $postalCode,
                    'fields' => 'nom,code,centre,codesPostaux',
                    'boost' => 'population',
                    'limit' => 1,
                ],
                'timeout' => 3,
            ]);

            $data = $response->toArray(false);
        } catch (\Throwable $e) {
            $this->logger->warning('Géocodage impossible pour {city} : {message}', [
                'city' => $city,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        $commune = $data[0] ?? null;
        if (!\is_array($commune) || !isset($commune['centre']['coordinates'])) {
            return null;
        }

        [$lng, $lat] = $commune['centre']['coordinates'];

        return [
            'name' => (string) ($commune['nom'] ?? $city),
            'postalCode' => (string) ($commune['codesPostaux'][0] ?? $postalCode ?? ''),
            'latitude' => (float) $lat,
            'longitude' => (float) $lng,
        ];
    }

    public function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
