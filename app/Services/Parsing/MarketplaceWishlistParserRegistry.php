<?php

namespace App\Services\Parsing;

use InvalidArgumentException;

final class MarketplaceWishlistParserRegistry
{
    /**
     * @param  iterable<MarketplaceWishlistParserStrategy>  $strategies
     */
    public function __construct(private readonly iterable $strategies) {}

    public function forUrl(string $url): MarketplaceWishlistParserStrategy
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($url)) {
                return $strategy;
            }
        }

        throw new InvalidArgumentException('Unsupported wishlist URL.');
    }
}
