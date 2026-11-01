<?php

declare(strict_types=1);

namespace App\Seo;

use App\Animal\Species;
use App\Entity\AnimalListing;
use App\Entity\User;
use App\Enum\CedantStatus;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Construit les métadonnées SEO et les données structurées schema.org.
 *
 * Les URL sont générées avec le contexte de la requête courante ; hors requête
 * HTTP (commande, test), le routeur retombe sur DEFAULT_URI.
 */
final class SeoManager
{
    public const SITE_NAME = 'LeBonFoyer';
    public const SITE_DESCRIPTION = "Plateforme française d'adoption responsable de chats et de chiens : annonces vérifiées, sevrage contrôlé et remise en mains propres.";

    /** @var list<string> */
    private const NOINDEX_ROUTE_PREFIXES = [
        'app_admin_',
        'app_account_',
        'app_message_',
        'app_application_',
        'app_listing_mine',
        'app_listing_new',
        'app_listing_edit',
        'app_listing_photo_',
        'app_listing_publish',
        'app_listing_suspend',
        'app_listing_delete',
        '_preview_error',
        '_profiler',
        '_wdt',
    ];

    /** @var list<string> */
    private const NOINDEX_ROUTES = [
        'app_login',
        'app_logout',
        'app_register',
        'app_verify_email',
        'app_forgot_password_request',
        'app_check_email',
        'app_reset_password',
    ];

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        private readonly string $defaultUri,
        private readonly string $contactEmail,
    ) {
    }

    public function canonicalUrl(): string
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            return rtrim($this->defaultUri, '/').'/';
        }

        $params = $request->query->all();
        if (isset($params['page']) && '1' === (string) $params['page']) {
            unset($params['page']);
        }
        if (isset($params['sort']) && 'recent' === (string) $params['sort']) {
            unset($params['sort']);
        }
        foreach (array_keys($params) as $key) {
            if (str_starts_with((string) $key, 'utm_') || \in_array($key, ['fbclid', 'gclid', 'msclkid', 'mc_cid', 'mc_eid', 'ref'], true)) {
                unset($params[$key]);
            }
        }
        $params = array_filter($params, static fn (mixed $value): bool => !\is_string($value) || '' !== $value);
        ksort($params);

        $url = $request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo();
        if ([] !== $params) {
            $url .= '?'.http_build_query($params, '', '&', \PHP_QUERY_RFC3986);
        }

        return $url;
    }

    public function robots(): string
    {
        $request = $this->requestStack->getMainRequest();
        $route = null !== $request ? (string) $request->attributes->get('_route', '') : '';

        foreach (self::NOINDEX_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return 'noindex,nofollow';
            }
        }

        if (\in_array($route, self::NOINDEX_ROUTES, true)) {
            return 'noindex,nofollow';
        }

        if ('app_listing_index' === $route && '' !== trim((string) $request->query->get('q', ''))) {
            return 'noindex,follow';
        }

        return 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1';
    }

    public function isIndexable(): bool
    {
        return str_starts_with($this->robots(), 'index');
    }

    /**
     * Graphe schema.org commun à toutes les pages : organisation, site et page.
     *
     * @return array<string, mixed>
     */
    public function pageGraph(string $title, string $description): array
    {
        $canonical = $this->canonicalUrl();
        $base = $this->baseUrl();

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                $this->organization(),
                $this->website(),
                [
                    '@type' => 'WebPage',
                    '@id' => $canonical.'#webpage',
                    'url' => $canonical,
                    'name' => $title,
                    'description' => $description,
                    'inLanguage' => 'fr-FR',
                    'isPartOf' => ['@id' => $base.'/#website'],
                    'about' => ['@id' => $base.'/#organization'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function organization(): array
    {
        $base = $this->baseUrl();

        return [
            '@type' => 'Organization',
            '@id' => $base.'/#organization',
            'name' => self::SITE_NAME,
            'url' => $base.'/',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => $this->absoluteAsset('images/logo.png'),
                'width' => 512,
                'height' => 512,
            ],
            'description' => self::SITE_DESCRIPTION,
            'email' => $this->contactEmail,
            'areaServed' => ['@type' => 'Country', 'name' => 'France'],
            'contactPoint' => [[
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'email' => $this->contactEmail,
                'availableLanguage' => ['French'],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    public function website(): array
    {
        $base = $this->baseUrl();

        return [
            '@type' => 'WebSite',
            '@id' => $base.'/#website',
            'url' => $base.'/',
            'name' => self::SITE_NAME,
            'description' => self::SITE_DESCRIPTION,
            'inLanguage' => 'fr-FR',
            'publisher' => ['@id' => $base.'/#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $this->route('app_listing_index').'?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function listing(AnimalListing $listing): array
    {
        $url = $this->route('app_listing_show', ['id' => (string) $listing->getId()]);
        $cedant = $listing->getCedant();

        $images = [];
        foreach ($listing->getPhotos() as $photo) {
            $image = [
                '@type' => 'ImageObject',
                'url' => $this->absoluteAsset($photo->getFilename()),
                'caption' => $listing->getName().', '.$this->speciesLabel($listing->getSpecies()).' à adopter à '.$listing->getCity(),
            ];
            if (null !== $photo->getWidth() && null !== $photo->getHeight()) {
                $image['width'] = $photo->getWidth();
                $image['height'] = $photo->getHeight();
            }
            $images[] = $image;
        }
        if ([] === $images) {
            $images[] = [
                '@type' => 'ImageObject',
                'url' => $this->defaultImage(),
                'width' => 1200,
                'height' => 630,
                'caption' => self::SITE_NAME.' — adoption responsable de chats et de chiens',
            ];
        }

        $availability = match (true) {
            $listing->isPublished() => 'https://schema.org/InStock',
            $listing->isReserved() => 'https://schema.org/LimitedAvailability',
            default => 'https://schema.org/SoldOut',
        };

        $price = (float) ($listing->getPrice() ?? 0.0);
        if ($this->isPrivateDonation($cedant)) {
            $price = 0.0;
        }

        return [
            '@type' => 'Product',
            '@id' => $url.'#product',
            'name' => $listing->getName(),
            'description' => trim(strip_tags($listing->getDescription())),
            'url' => $url,
            'sku' => 'LF-'.$listing->getId(),
            'category' => $this->speciesLabel($listing->getSpecies()),
            'image' => $images,
            'brand' => ['@type' => 'Organization', 'name' => self::SITE_NAME],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            'additionalProperty' => $this->listingProperties($listing),
            'offers' => [
                '@type' => 'Offer',
                '@id' => $url.'#offer',
                'url' => $url,
                'price' => number_format($price, 2, '.', ''),
                'priceCurrency' => 'EUR',
                'availability' => $availability,
                'seller' => $this->seller($cedant),
            ],
            'datePublished' => $listing->getPublishedAt()?->format(\DateTimeInterface::ATOM),
            'dateModified' => $listing->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param list<AnimalListing> $listings
     *
     * @return array<string, mixed>
     */
    public function itemList(array $listings, string $name): array
    {
        $elements = [];
        foreach ($listings as $index => $listing) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'url' => $this->route('app_listing_show', ['id' => (string) $listing->getId()]),
                'name' => $listing->getName(),
            ];
        }

        return [
            '@type' => 'ItemList',
            'name' => $name,
            'numberOfItems' => \count($elements),
            'itemListElement' => $elements,
        ];
    }

    /**
     * @param list<array{name: string, url: string}> $items
     *
     * @return array<string, mixed>
     */
    public function breadcrumbs(array $items): array
    {
        $elements = [];
        foreach ($items as $index => $item) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ];
        }

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * @param list<array{question: string, answer: string}> $items
     *
     * @return array<string, mixed>
     */
    public function faq(array $items): array
    {
        $questions = [];
        foreach ($items as $item) {
            $questions[] = [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'],
                ],
            ];
        }

        return [
            '@type' => 'FAQPage',
            'mainEntity' => $questions,
        ];
    }

    /**
     * @param array<string, string> $parameters
     */
    public function route(string $name, array $parameters = []): string
    {
        return $this->urlGenerator->generate($name, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function absoluteAsset(string $path): string
    {
        return $this->baseUrl().'/'.ltrim($path, '/');
    }

    public function defaultImage(): string
    {
        return $this->absoluteAsset('images/og-default.png');
    }

    public function baseUrl(): string
    {
        $request = $this->requestStack->getMainRequest();
        if (null !== $request) {
            return $request->getSchemeAndHttpHost();
        }

        return rtrim($this->defaultUri, '/');
    }

    public function speciesLabel(Species $species): string
    {
        return Species::Cat === $species ? 'Chat' : 'Chien';
    }

    private function isPrivateDonation(?User $cedant): bool
    {
        return null === $cedant || null === $cedant->getCedantStatus() || CedantStatus::Particulier === $cedant->getCedantStatus();
    }

    /** @return array<string, mixed> */
    private function seller(?User $cedant): array
    {
        if (null === $cedant) {
            return ['@type' => 'Organization', 'name' => self::SITE_NAME];
        }

        if ($this->isPrivateDonation($cedant)) {
            return ['@type' => 'Person', 'name' => $cedant->getFullName()];
        }

        return [
            '@type' => 'Organization',
            'name' => $cedant->getOrganizationName() ?? $cedant->getFullName(),
        ];
    }

    /** @return list<array<string, string>> */
    private function listingProperties(AnimalListing $listing): array
    {
        $properties = [
            ['@type' => 'PropertyValue', 'name' => 'Espèce', 'value' => $this->speciesLabel($listing->getSpecies())],
            ['@type' => 'PropertyValue', 'name' => 'Ville', 'value' => $listing->getCity().' ('.$listing->getPostalCode().')'],
        ];

        if (null !== $listing->getBirthDate()) {
            $properties[] = [
                '@type' => 'PropertyValue',
                'name' => 'Date de naissance',
                'value' => $listing->getBirthDate()->format('Y-m-d'),
            ];
        }

        return $properties;
    }
}
