<?php

declare(strict_types=1);

namespace App\Twig;

use App\Seo\SeoManager;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

final class SeoExtension extends AbstractExtension
{
    public function __construct(private readonly SeoManager $seoManager)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('seo_canonical', $this->seoManager->canonicalUrl(...)),
            new TwigFunction('seo_robots', $this->seoManager->robots(...)),
            new TwigFunction('seo_indexable', $this->seoManager->isIndexable(...)),
            new TwigFunction('seo_base_url', $this->seoManager->baseUrl(...)),
            new TwigFunction('seo_default_image', $this->seoManager->defaultImage(...)),
            new TwigFunction('seo_route', $this->seoManager->route(...)),
            new TwigFunction('seo_page_graph', $this->seoManager->pageGraph(...)),
            new TwigFunction('seo_listing', $this->seoManager->listing(...)),
            new TwigFunction('seo_item_list', $this->seoManager->itemList(...)),
            new TwigFunction('seo_breadcrumbs', $this->seoManager->breadcrumbs(...)),
            new TwigFunction('seo_faq', $this->seoManager->faq(...)),
            new TwigFunction('seo_json_ld', $this->jsonLd(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * @param array<mixed> $data
     */
    public function jsonLd(array $data): Markup
    {
        if (!isset($data['@context'])) {
            $nodes = array_is_list($data) ? $data : [$data];
            $data = ['@context' => 'https://schema.org', '@graph' => $nodes];
        }

        $json = json_encode(
            $data,
            \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_THROW_ON_ERROR,
        );

        return new Markup('<script type="application/ld+json">'.$json.'</script>', 'UTF-8');
    }
}
