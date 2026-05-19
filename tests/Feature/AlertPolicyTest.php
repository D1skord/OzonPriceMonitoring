<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\PriceSnapshot;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProduct;
use App\Models\Wishlist;
use App\Services\AlertPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AlertPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.crawl.min_alert_drop_percent', 1.0);
    }

    public function test_creates_alert_only_for_strict_historical_minimum(): void
    {
        [$userProduct, $snapshot] = $this->snapshot(399000);

        $alert = app(AlertPolicy::class)->createHistoricalMinAlert($userProduct, $snapshot, 499000);

        $this->assertNotNull($alert);
        $this->assertSame(399000, $alert->new_price_minor);
    }

    public function test_does_not_create_alert_for_baseline_or_same_price(): void
    {
        [$userProduct, $snapshot] = $this->snapshot(499000);

        $this->assertNull(app(AlertPolicy::class)->createHistoricalMinAlert($userProduct, $snapshot, null));
        $this->assertNull(app(AlertPolicy::class)->createHistoricalMinAlert($userProduct, $snapshot, 499000));
    }

    public function test_does_not_create_alert_when_drop_below_threshold(): void
    {
        // Падение 1000 / 500000 = 0.2%, ниже порога 1%
        [$userProduct, $snapshot] = $this->snapshot(499000);

        $alert = app(AlertPolicy::class)->createHistoricalMinAlert($userProduct, $snapshot, 500000);

        $this->assertNull($alert);
        $this->assertSame(0, Alert::query()->count());
    }

    public function test_creates_alert_when_drop_meets_threshold(): void
    {
        // Падение ровно 1% от 500000 = 5000
        [$userProduct, $snapshot] = $this->snapshot(495000);

        $alert = app(AlertPolicy::class)->createHistoricalMinAlert($userProduct, $snapshot, 500000);

        $this->assertNotNull($alert);
        $this->assertSame(495000, $alert->new_price_minor);
    }

    public function test_creates_alert_when_drop_exceeds_threshold(): void
    {
        // Падение 5% от 500000
        [$userProduct, $snapshot] = $this->snapshot(475000);

        $alert = app(AlertPolicy::class)->createHistoricalMinAlert($userProduct, $snapshot, 500000);

        $this->assertNotNull($alert);
        $this->assertSame(475000, $alert->new_price_minor);
    }

    public function test_zero_threshold_creates_alert_for_any_drop(): void
    {
        config()->set('services.crawl.min_alert_drop_percent', 0.0);

        [$userProduct, $snapshot] = $this->snapshot(499900);

        $alert = app(AlertPolicy::class)->createHistoricalMinAlert($userProduct, $snapshot, 500000);

        $this->assertNotNull($alert);
    }

    /**
     * @return array{UserProduct, PriceSnapshot}
     */
    private function snapshot(int $price): array
    {
        $user = User::factory()->create();
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'marketplace' => 'ozon',
            'url' => 'https://www.ozon.ru/my/favorites',
            'normalized_url' => 'https://www.ozon.ru/my/favorites',
            'is_active' => true,
        ]);
        $product = Product::create([
            'marketplace' => 'ozon',
            'marketplace_product_id' => '111',
            'title' => 'Товар',
            'canonical_url' => 'https://www.ozon.ru/product/a-111/',
        ]);
        $userProduct = UserProduct::create([
            'user_id' => $user->id,
            'wishlist_id' => $wishlist->id,
            'product_id' => $product->id,
            'external_key' => 'ozon:111:default',
            'title' => 'Товар',
            'canonical_url' => 'https://www.ozon.ru/product/a-111/',
        ]);
        $snapshot = PriceSnapshot::create([
            'user_product_id' => $userProduct->id,
            'product_id' => $product->id,
            'wishlist_id' => $wishlist->id,
            'price_minor' => $price,
            'currency' => 'RUB',
            'availability' => 'in_stock',
            'source_url' => 'https://www.ozon.ru/my/favorites',
            'parser_version' => 'test',
            'captured_at' => now(),
        ]);

        return [$userProduct, $snapshot];
    }
}
