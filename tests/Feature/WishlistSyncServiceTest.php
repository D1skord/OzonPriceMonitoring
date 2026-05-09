<?php

namespace Tests\Feature;

use App\DTO\ParsedWishlistItem;
use App\Models\Alert;
use App\Models\User;
use App\Services\WishlistSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WishlistSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_sync_creates_baseline_without_alerts(): void
    {
        $user = User::factory()->create();
        $service = app(WishlistSyncService::class);
        $wishlist = $service->bindWishlist($user, 'https://www.ozon.ru/my/favorites');

        $result = $service->syncParsedItems($wishlist, [$this->item(499000)], now());

        $this->assertSame(1, $result->productsFound);
        $this->assertSame(0, $result->alertsCreated);
        $this->assertDatabaseCount('price_snapshots', 1);
        $this->assertDatabaseCount('alerts', 0);
    }

    public function test_new_historical_min_creates_alert_after_baseline(): void
    {
        $user = User::factory()->create();
        $service = app(WishlistSyncService::class);
        $wishlist = $service->bindWishlist($user, 'https://www.ozon.ru/my/favorites');

        $service->syncParsedItems($wishlist, [$this->item(499000)], now());
        $wishlist->refresh();
        $result = $service->syncParsedItems($wishlist, [$this->item(399000)], now()->addHour());

        $this->assertSame(1, $result->alertsCreated);
        $this->assertDatabaseCount('alerts', 1);
        $this->assertSame(399000, Alert::first()->new_price_minor);
    }

    private function item(int $price): ParsedWishlistItem
    {
        return new ParsedWishlistItem(
            key: 'ozon:111:default',
            marketplace: 'ozon',
            marketplaceProductId: '111',
            title: 'Супер чайник',
            canonicalUrl: 'https://www.ozon.ru/product/super-chainik-111/',
            imageUrl: null,
            finalPriceMinor: $price,
            oldPriceMinor: null,
            currency: 'RUB',
            availability: 'in_stock',
            sourcePage: 'wishlist',
            sourceUrl: 'https://www.ozon.ru/my/favorites',
            parserVersion: 'test',
        );
    }
}
