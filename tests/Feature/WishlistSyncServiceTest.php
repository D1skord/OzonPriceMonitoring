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
        $capturedAt = now();

        $result = $service->syncParsedItems($wishlist, [$this->item(499000)], $capturedAt);

        $this->assertSame(1, $result->productsFound);
        $this->assertSame(0, $result->alertsCreated);
        $this->assertDatabaseCount('price_snapshots', 1);
        $this->assertDatabaseCount('alerts', 0);
        $this->assertSame(
            $capturedAt->copy()->addMinutes(15)->toDateTimeString(),
            $wishlist->refresh()->next_crawl_at->toDateTimeString(),
        );
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

    public function test_bind_wishlist_stores_shared_ozon_link(): void
    {
        $user = User::factory()->create();
        $wishlist = app(WishlistSyncService::class)->bindWishlist($user, 'https://ozon.ru/t/1EMSVNf?utm_source=vk');

        $this->assertSame('https://ozon.ru/t/1EMSVNf?utm_source=vk', $wishlist->url);
        $this->assertSame('https://ozon.ru/t/1EMSVNf', $wishlist->normalized_url);
        $this->assertTrue($wishlist->is_active);
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
