<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Models\CrawlRun;
use App\Models\Wishlist;
use App\Services\Crawling\OzonWishlistCrawler;
use App\Services\MoneyFormatter;
use App\Services\Parsing\MarketplaceWishlistParserRegistry;
use App\Services\Vk\VkBotClient;
use App\Services\WishlistSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

final class CrawlWishlistJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public readonly int $wishlistId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('wishlist-'.$this->wishlistId))->expireAfter(900)];
    }

    public function handle(
        OzonWishlistCrawler $crawler,
        MarketplaceWishlistParserRegistry $parserRegistry,
        WishlistSyncService $syncService,
        VkBotClient $vkBotClient,
        MoneyFormatter $moneyFormatter,
    ): void {
        $wishlist = Wishlist::query()->with('user')->findOrFail($this->wishlistId);
        $run = CrawlRun::create([
            'wishlist_id' => $wishlist->id,
            'user_id' => $wishlist->user_id,
            'status' => CrawlRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        try {
            $html = $crawler->fetchHtml($wishlist);
            $items = $parserRegistry->forUrl($wishlist->url)->parse($html, $wishlist->url);
            $result = $syncService->syncParsedItems($wishlist, $items, now());

            foreach ($result->alerts as $alert) {
                $this->sendAlert($alert, $vkBotClient, $moneyFormatter);
            }

            $run->update([
                'status' => CrawlRun::STATUS_SUCCESS,
                'finished_at' => now(),
                'products_found' => $result->productsFound,
                'snapshots_created' => $result->snapshotsCreated,
                'alerts_created' => $result->alertsCreated,
            ]);
        } catch (Throwable $exception) {
            $run->update([
                'status' => CrawlRun::STATUS_FAILED,
                'finished_at' => now(),
                'error_type' => $exception::class,
                'error_message' => $exception->getMessage(),
                'error_context' => ['wishlist_id' => $wishlist->id],
            ]);

            throw $exception;
        }
    }

    private function sendAlert(Alert $alert, VkBotClient $vkBotClient, MoneyFormatter $moneyFormatter): void
    {
        $alert->loadMissing('user', 'userProduct');
        $userProduct = $alert->userProduct;
        $url = route('stats.user-product', $userProduct);
        $message = implode("\n", [
            'Новый исторический минимум:',
            $userProduct->title,
            'Цена: '.$moneyFormatter->rubles($alert->new_price_minor),
            'Было минимум: '.$moneyFormatter->rubles((int) $alert->previous_min_price_minor),
            'Статистика: '.$url,
        ]);

        $messageId = $vkBotClient->sendMessage((int) $alert->user->vk_peer_id, $message);

        $alert->update([
            'sent_at' => now(),
            'vk_message_id' => $messageId,
        ]);
    }
}
