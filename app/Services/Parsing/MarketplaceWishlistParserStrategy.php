<?php

namespace App\Services\Parsing;

use App\DTO\ParsedWishlistItem;

interface MarketplaceWishlistParserStrategy
{
    public function marketplace(): string;

    public function parserVersion(): string;

    public function supports(string $url): bool;

    /**
     * @return list<ParsedWishlistItem>
     */
    public function parse(string $html, string $sourceUrl): array;
}
