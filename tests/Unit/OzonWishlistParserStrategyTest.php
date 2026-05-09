<?php

namespace Tests\Unit;

use App\Services\Parsing\MarketplaceWishlistParserRegistry;
use App\Services\Parsing\OzonWishlistParserStrategy;
use App\Services\Parsing\PriceTextParser;
use App\Services\Parsing\ProductUrlNormalizer;
use PHPUnit\Framework\TestCase;

final class OzonWishlistParserStrategyTest extends TestCase
{
    public function test_parses_ozon_wishlist_fixture(): void
    {
        $parser = new OzonWishlistParserStrategy(new PriceTextParser, new ProductUrlNormalizer);
        $items = $parser->parse(
            file_get_contents(__DIR__.'/../Fixtures/ozon-wishlist.html'),
            'https://www.ozon.ru/my/favorites',
        );

        $this->assertCount(2, $items);
        $this->assertSame('111', $items[0]->marketplaceProductId);
        $this->assertSame(499000, $items[0]->finalPriceMinor);
        $this->assertSame(699000, $items[0]->oldPriceMinor);
        $this->assertSame('ozon-wishlist-mvp-2', $items[0]->parserVersion);
        $this->assertStringStartsWith('fallback_', $items[1]->marketplaceProductId);
        $this->assertSame('productId_fallback_hash', $items[1]->raw['debug']);
    }

    public function test_registry_selects_ozon_strategy(): void
    {
        $strategy = new OzonWishlistParserStrategy(new PriceTextParser, new ProductUrlNormalizer);
        $registry = new MarketplaceWishlistParserRegistry([$strategy]);

        $this->assertSame($strategy, $registry->forUrl('https://www.ozon.ru/my/favorites'));
        $this->assertSame($strategy, $registry->forUrl('https://ozon.ru/t/1EMSVNf'));
    }
}
