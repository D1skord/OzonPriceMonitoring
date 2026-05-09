<?php

namespace App\Services\Parsing;

use App\DTO\ParsedWishlistItem;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

final class OzonWishlistParserStrategy implements MarketplaceWishlistParserStrategy
{
    private const MARKETPLACE = 'ozon';

    private const VERSION = 'ozon-wishlist-mvp-2';

    public function __construct(
        private readonly PriceTextParser $priceTextParser,
        private readonly ProductUrlNormalizer $urlNormalizer,
    ) {}

    public function marketplace(): string
    {
        return self::MARKETPLACE;
    }

    public function parserVersion(): string
    {
        return self::VERSION;
    }

    public function supports(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $path = parse_url($url, PHP_URL_PATH) ?: '';

        return (bool) preg_match('/(^|\.)ozon\.ru$/i', $host)
            && (bool) preg_match('~^(?:/my/favorites/?|/t/[A-Za-z0-9_-]+/?)$~i', $path);
    }

    public function parse(string $html, string $sourceUrl): array
    {
        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $items = [];

        foreach ($this->findProductCards($xpath) as $card) {
            $item = $this->parseCard($xpath, $card, $sourceUrl);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @return list<DOMElement>
     */
    private function findProductCards(DOMXPath $xpath): array
    {
        $cards = $this->queryElements($xpath, '//*[contains(concat(" ", normalize-space(@class), " "), " tile-root ")]');

        if ($cards !== []) {
            return $cards;
        }

        $links = $this->queryElements($xpath, '//a[contains(@href, "/product/")]');
        $fallback = [];

        foreach ($links as $link) {
            $card = $this->closestCard($link);

            if ($card !== null) {
                $fallback[spl_object_id($card)] = $card;
            }
        }

        return array_values($fallback);
    }

    private function parseCard(DOMXPath $xpath, DOMElement $card, string $sourceUrl): ?ParsedWishlistItem
    {
        $link = $this->findProductLink($xpath, $card);
        $href = $link?->getAttribute('href');

        if (! $href) {
            return null;
        }

        $canonicalUrl = $this->urlNormalizer->normalize($href, $sourceUrl);
        $title = $this->findTitle($xpath, $card, $link);
        $priceTexts = $this->findOzonPriceTexts($xpath, $card);

        if ($priceTexts === null) {
            return null;
        }

        $finalPriceMinor = $this->priceTextParser->parseToMinor($priceTexts['final']);

        if ($finalPriceMinor === null) {
            return null;
        }

        $productId = $this->urlNormalizer->extractProductId($canonicalUrl, self::MARKETPLACE)
            ?? $card->getAttribute('data-sku')
            ?: $card->getAttribute('data-product-id')
            ?: $this->urlNormalizer->fallbackProductId($canonicalUrl, $title);
        $oldPriceText = $priceTexts['old'] ?? $this->findOldPriceText($xpath, $card, $priceTexts['final']);
        $oldPriceMinor = $oldPriceText ? $this->priceTextParser->parseToMinor($oldPriceText) : null;

        return new ParsedWishlistItem(
            key: self::MARKETPLACE.':'.$productId.':default',
            marketplace: self::MARKETPLACE,
            marketplaceProductId: $productId,
            title: $title,
            canonicalUrl: $canonicalUrl,
            imageUrl: $this->findImage($xpath, $card, $sourceUrl),
            finalPriceMinor: $finalPriceMinor,
            oldPriceMinor: $oldPriceMinor,
            currency: 'RUB',
            availability: $this->availabilityFromText($card),
            sourcePage: 'wishlist',
            sourceUrl: $sourceUrl,
            parserVersion: self::VERSION,
            raw: [
                'finalPriceText' => $priceTexts['final'],
                'oldPriceText' => $oldPriceText,
                'titleText' => $title,
                'debug' => str_starts_with($productId, 'fallback_') ? 'productId_fallback_hash' : null,
            ],
        );
    }

    private function findProductLink(DOMXPath $xpath, DOMElement $card): ?DOMElement
    {
        $links = $this->queryElements($xpath, './/a[contains(concat(" ", normalize-space(@class), " "), " tile-clickable-element ") and contains(@href, "/product/")]', $card);

        return $links[0] ?? $this->queryElements($xpath, './/a[contains(@href, "/product/")]', $card)[0] ?? null;
    }

    private function findTitle(DOMXPath $xpath, DOMElement $card, ?DOMElement $link): string
    {
        $titleLinks = $this->queryElements($xpath, './/a[contains(concat(" ", normalize-space(@class), " "), " tile-clickable-element ") and contains(@href, "/product/")]', $card);

        foreach ($titleLinks as $titleLink) {
            foreach ($this->queryElements($xpath, './/span', $titleLink) as $span) {
                $text = $this->readText($span);

                if ($this->isLikelyTitleText($text)) {
                    return $text;
                }
            }
        }

        foreach ($this->queryElements($xpath, './/span', $link ?? $card) as $span) {
            $text = $this->readText($span);

            if ($this->isLikelyTitleText($text)) {
                return $text;
            }
        }

        return trim($link?->getAttribute('aria-label') ?: $link?->getAttribute('title') ?: '') ?: 'Товар Ozon';
    }

    private function findImage(DOMXPath $xpath, DOMElement $card, string $sourceUrl): ?string
    {
        $image = $this->queryElements($xpath, './/img', $card)[0] ?? null;
        $src = $image?->getAttribute('src') ?: null;

        return $src ? $this->urlNormalizer->normalize($src, $sourceUrl) : null;
    }

    /**
     * @return array{final: string, old?: string}|null
     */
    private function findOzonPriceTexts(DOMXPath $xpath, DOMElement $card): ?array
    {
        $candidates = $this->collectOzonPriceCandidates($xpath, $card);
        $final = null;

        foreach ($candidates as $candidate) {
            if (! $this->isOldPriceElement($candidate['element'])) {
                $final = $candidate;
                break;
            }
        }

        if ($final === null) {
            return null;
        }

        $old = null;

        foreach ($candidates as $candidate) {
            if ($candidate['text'] !== $final['text'] && $this->isOldPriceElement($candidate['element'])) {
                $old = $candidate;
                break;
            }
        }

        if ($old === null) {
            foreach ($candidates as $candidate) {
                if ($candidate['text'] !== $final['text'] && $candidate['priceMinor'] > $final['priceMinor']) {
                    $old = $candidate;
                    break;
                }
            }
        }

        return [
            'final' => $final['text'],
            'old' => $old['text'] ?? null,
        ];
    }

    /**
     * @return list<array{text: string, priceMinor: int, element: DOMElement, score: int}>
     */
    private function collectOzonPriceCandidates(DOMXPath $xpath, DOMElement $card): array
    {
        $candidates = [];
        $seen = [];

        foreach ($this->queryElements($xpath, './/span | .//div | .//ins | .//s | .//del', $card) as $element) {
            $text = $this->readText($element);

            if ($text === '' || ! str_contains($text, '₽') || $this->isNoisePriceText($text)) {
                continue;
            }

            $priceMinor = $this->priceTextParser->parseToMinor($text);

            if ($priceMinor === null) {
                continue;
            }

            $key = $text.':'.$priceMinor.':'.$element->getAttribute('class');

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $className = $element->getAttribute('class');
            $score = 0;

            if (preg_match('/tsHeadline/i', $className)) {
                $score += 100;
            }

            if (preg_match('/tsBody/i', $className)) {
                $score += 20;
            }

            if ($this->isOldPriceElement($element)) {
                $score -= 80;
            }

            if (str_contains($text, '%')) {
                $score -= 20;
            }

            if (strlen($text) > 24) {
                $score -= 15;
            }

            $candidates[] = compact('text', 'priceMinor', 'element', 'score');
        }

        usort($candidates, fn (array $left, array $right): int => $right['score'] <=> $left['score']);

        return $candidates;
    }

    private function findOldPriceText(DOMXPath $xpath, DOMElement $card, string $finalPriceText): ?string
    {
        $query = './/del | .//s | .//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "old")] | .//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "cross")] | .//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "discount")]';

        foreach ($this->queryElements($xpath, $query, $card) as $element) {
            $text = $this->readText($element);

            if ($text !== '' && $text !== $finalPriceText && $this->priceTextParser->parseToMinor($text) !== null) {
                return $text;
            }
        }

        return null;
    }

    private function availabilityFromText(DOMElement $card): string
    {
        $text = mb_strtolower($this->readText($card));

        if (preg_match('/нет в наличии|закончился|недоступен/u', $text)) {
            return 'out_of_stock';
        }

        if (preg_match('/в корзину|купить|добавить/u', $text)) {
            return 'in_stock';
        }

        return 'unknown';
    }

    private function isLikelyTitleText(string $text): bool
    {
        return mb_strlen($text) > 12
            && ! preg_match('/[₽%]/u', $text)
            && ! preg_match('/отзыв|завтра|распродажа|скидка|балл|рассроч/iu', $text);
    }

    private function isOldPriceElement(DOMElement $element): bool
    {
        return in_array($element->tagName, ['s', 'del'], true)
            || (bool) preg_match('/old|cross|strike|strok|line-through/i', $element->getAttribute('class').' '.$element->getAttribute('style'));
    }

    private function isNoisePriceText(string $text): bool
    {
        return (bool) preg_match('/(^|\s)\d+\s+и\s+\d+\s*₽/iu', $text)
            || (bool) preg_match('/до\s+\d+\s+дн/iu', $text);
    }

    private function readText(DOMNode $node): string
    {
        return trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
    }

    private function closestCard(DOMElement $link): ?DOMElement
    {
        $node = $link->parentNode;

        while ($node instanceof DOMElement) {
            if (in_array($node->tagName, ['article', 'li'], true) || str_contains(' '.$node->getAttribute('class').' ', ' tile-root ')) {
                return $node;
            }

            $node = $node->parentNode;
        }

        return null;
    }

    /**
     * @return list<DOMElement>
     */
    private function queryElements(DOMXPath $xpath, string $query, ?DOMNode $context = null): array
    {
        $nodes = $xpath->query($query, $context);
        $elements = [];

        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }
}
