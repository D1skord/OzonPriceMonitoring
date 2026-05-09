<?php

namespace App\Services;

use App\DTO\ParsedWishlistItem;
use App\DTO\WishlistSyncResult;
use App\Models\Alert;
use App\Models\PriceSnapshot;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProduct;
use App\Models\Wishlist;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class WishlistSyncService
{
    public function __construct(private readonly AlertPolicy $alertPolicy) {}

    public function bindWishlist(User $user, string $url): Wishlist
    {
        return DB::transaction(function () use ($user, $url): Wishlist {
            Wishlist::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            return Wishlist::create([
                'user_id' => $user->id,
                'marketplace' => 'ozon',
                'url' => $url,
                'normalized_url' => $this->normalizeWishlistUrl($url),
                'is_active' => true,
                'next_crawl_at' => now(),
            ]);
        });
    }

    /**
     * @param  list<ParsedWishlistItem>  $items
     */
    public function syncParsedItems(Wishlist $wishlist, array $items, Carbon $capturedAt): WishlistSyncResult
    {
        return DB::transaction(function () use ($wishlist, $items, $capturedAt): WishlistSyncResult {
            $alerts = [];
            $snapshotsCreated = 0;
            $isBaseline = $wishlist->baseline_crawled_at === null;
            $seenUserProductIds = [];

            foreach ($items as $item) {
                $product = Product::updateOrCreate(
                    [
                        'marketplace' => $item->marketplace,
                        'marketplace_product_id' => $item->marketplaceProductId,
                    ],
                    [
                        'title' => $item->title,
                        'canonical_url' => $item->canonicalUrl,
                        'image_url' => $item->imageUrl,
                        'currency' => $item->currency,
                    ],
                );

                $userProduct = UserProduct::updateOrCreate(
                    [
                        'user_id' => $wishlist->user_id,
                        'product_id' => $product->id,
                    ],
                    [
                        'wishlist_id' => $wishlist->id,
                        'external_key' => $item->key,
                        'title' => $item->title,
                        'canonical_url' => $item->canonicalUrl,
                        'image_url' => $item->imageUrl,
                        'current_price_minor' => $item->finalPriceMinor,
                        'last_seen_at' => $capturedAt,
                        'is_active' => true,
                    ],
                );

                $previousMin = $userProduct->historical_min_price_minor;

                $snapshot = PriceSnapshot::create([
                    'user_product_id' => $userProduct->id,
                    'product_id' => $product->id,
                    'wishlist_id' => $wishlist->id,
                    'price_minor' => $item->finalPriceMinor,
                    'old_price_minor' => $item->oldPriceMinor,
                    'currency' => $item->currency,
                    'availability' => $item->availability,
                    'source_url' => $item->sourceUrl,
                    'parser_version' => $item->parserVersion,
                    'raw' => array_filter($item->raw, fn (mixed $value): bool => $value !== null),
                    'captured_at' => $capturedAt,
                ]);

                $snapshotsCreated++;
                $seenUserProductIds[] = $userProduct->id;

                if ($previousMin === null || $item->finalPriceMinor < $previousMin) {
                    $userProduct->historical_min_price_minor = $item->finalPriceMinor;
                    $userProduct->save();
                }

                if (! $isBaseline) {
                    $alert = $this->alertPolicy->createHistoricalMinAlert($userProduct, $snapshot, $previousMin);

                    if ($alert instanceof Alert) {
                        $alerts[] = $alert;
                    }
                }
            }

            UserProduct::query()
                ->where('wishlist_id', $wishlist->id)
                ->whereNotIn('id', $seenUserProductIds ?: [0])
                ->update(['is_active' => false]);

            $wishlist->update([
                'baseline_crawled_at' => $wishlist->baseline_crawled_at ?? $capturedAt,
                'last_crawled_at' => $capturedAt,
                'next_crawl_at' => $capturedAt->copy()->addMinutes((int) config('services.crawl.interval_minutes', 15)),
            ]);

            return new WishlistSyncResult(
                productsFound: count($items),
                snapshotsCreated: $snapshotsCreated,
                alertsCreated: count($alerts),
                alerts: $alerts,
            );
        });
    }

    private function normalizeWishlistUrl(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            return $url;
        }

        return ($parts['scheme'] ?? 'https').'://'.$parts['host'].($parts['path'] ?? '/');
    }
}
