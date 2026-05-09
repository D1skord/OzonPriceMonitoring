<?php

namespace App\DTO;

final readonly class ParsedWishlistItem
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $key,
        public string $marketplace,
        public string $marketplaceProductId,
        public string $title,
        public string $canonicalUrl,
        public ?string $imageUrl,
        public int $finalPriceMinor,
        public ?int $oldPriceMinor,
        public string $currency,
        public string $availability,
        public string $sourcePage,
        public string $sourceUrl,
        public string $parserVersion,
        public array $raw = [],
    ) {}
}
