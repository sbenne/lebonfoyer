<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AnimalListingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SitemapController extends AbstractController
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(AnimalListingRepository $listingRepository): Response
    {
        $urls = [
            $this->url('app_home', 1.0, 'daily'),
            $this->url('app_listing_index', 0.9, 'daily'),
            $this->url('app_listing_index', 0.8, 'weekly', ['species' => 'cat']),
            $this->url('app_listing_index', 0.8, 'weekly', ['species' => 'dog']),
            $this->url('app_welfare_rules', 0.6, 'monthly'),
            $this->url('app_privacy', 0.3, 'yearly'),
            $this->url('app_legal_mentions', 0.3, 'yearly'),
        ];

        foreach ($listingRepository->findVisible() as $listing) {
            $urls[] = $this->url(
                'app_listing_show',
                0.7,
                'weekly',
                ['id' => (string) $listing->getId()],
                $listing->getUpdatedAt(),
            );
        }

        $response = $this->render('seo/sitemap.xml.twig', ['urls' => $urls]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }

    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /compte',
            'Disallow: /connexion',
            'Disallow: /inscription',
            'Disallow: /mot-de-passe-oublie',
            'Disallow: /reinitialiser-mot-de-passe',
            'Disallow: /verifier-email',
            'Disallow: /mes-annonces',
            'Disallow: /mes-demandes',
            'Disallow: /demandes',
            'Disallow: /messages',
            'Disallow: /annonces/*/reserver',
            'Disallow: /*/envoyer',
            'Disallow: /*/supprimer',
            'Disallow: /_profiler',
            'Disallow: /_error',
            '',
            'Sitemap: '.$this->urlGenerator->generate('app_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL),
            '',
        ];

        return new Response(implode("\n", $lines), Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * @param array<string, string> $parameters
     *
     * @return array{loc: string, changefreq: string, priority: string, lastmod: ?string}
     */
    private function url(
        string $route,
        float $priority,
        string $changefreq,
        array $parameters = [],
        ?\DateTimeInterface $lastmod = null,
    ): array {
        return [
            'loc' => $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL),
            'changefreq' => $changefreq,
            'priority' => number_format($priority, 1, '.', ''),
            'lastmod' => $lastmod?->format(\DateTimeInterface::ATOM),
        ];
    }
}
