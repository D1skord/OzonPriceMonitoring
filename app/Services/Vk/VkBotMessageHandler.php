<?php

namespace App\Services\Vk;

use App\Jobs\CrawlWishlistJob;
use App\Models\User;
use App\Models\UserProduct;
use App\Services\MoneyFormatter;
use App\Services\WishlistSyncService;
use Illuminate\Support\Facades\Bus;

final class VkBotMessageHandler
{
    public function __construct(
        private readonly WishlistSyncService $wishlistSyncService,
        private readonly MoneyFormatter $moneyFormatter,
    ) {}

    public function handle(int $vkUserId, int $peerId, string $text): string
    {
        $user = User::updateOrCreate(
            ['vk_user_id' => $vkUserId],
            [
                'vk_peer_id' => $peerId,
                'name' => 'VK '.$vkUserId,
                'last_seen_at' => now(),
            ],
        );

        $text = trim($text);
        $lower = mb_strtolower($text);

        if (in_array($lower, ['/start', 'start', 'начать'], true)) {
            return 'Пришли ссылку на Ozon wishlist. Я буду проверять цены раз в 6 часов и писать, когда товар обновит исторический минимум.';
        }

        if (in_array($lower, ['/help', 'help', 'помощь'], true)) {
            return implode("\n", [
                'Команды:',
                '/help - помощь',
                '/items - список твоих товаров',
                '/item ID - краткая статистика товара',
                'или пришли ссылку на Ozon wishlist.',
            ]);
        }

        if ($lower === '/items') {
            return $this->itemsMessage($user);
        }

        if (preg_match('/^\/item\s+(\d+)$/', $lower, $matches)) {
            return $this->itemMessage($user, (int) $matches[1]);
        }

        $wishlistUrl = $this->extractWishlistUrl($text);

        if ($wishlistUrl !== null) {
            $wishlist = $this->wishlistSyncService->bindWishlist($user, $wishlistUrl);
            Bus::dispatch(new CrawlWishlistJob($wishlist->id));

            return 'Wishlist привязан. Первый обход создаст baseline без алертов.';
        }

        return 'Не понял сообщение. Пришли ссылку на Ozon wishlist или команду /help.';
    }

    private function itemsMessage(User $user): string
    {
        $items = $user->userProducts()
            ->where('is_active', true)
            ->latest('last_seen_at')
            ->limit(20)
            ->get();

        if ($items->isEmpty()) {
            return 'Активных товаров пока нет. Пришли ссылку на Ozon wishlist.';
        }

        $lines = ['Твои товары:'];

        foreach ($items as $item) {
            $price = $item->current_price_minor !== null ? $this->moneyFormatter->rubles($item->current_price_minor) : 'цена неизвестна';
            $lines[] = '#'.$item->id.' '.$price.' - '.$item->title;
        }

        $lines[] = 'Для статистики: /item ID';

        return implode("\n", $lines);
    }

    private function itemMessage(User $user, int $id): string
    {
        $item = UserProduct::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $item) {
            return 'Товар не найден. Посмотри список через /items.';
        }

        $snapshots = $item->priceSnapshots()->latest('captured_at')->limit(5)->get();
        $lines = [
            $item->title,
            'Текущая цена: '.($item->current_price_minor !== null ? $this->moneyFormatter->rubles($item->current_price_minor) : 'неизвестно'),
            'Исторический минимум: '.($item->historical_min_price_minor !== null ? $this->moneyFormatter->rubles($item->historical_min_price_minor) : 'неизвестно'),
            'График: '.route('stats.user-product', $item),
            'Последние цены:',
        ];

        foreach ($snapshots as $snapshot) {
            $lines[] = $snapshot->captured_at->format('d.m H:i').' - '.$this->moneyFormatter->rubles($snapshot->price_minor);
        }

        return implode("\n", $lines);
    }

    private function extractWishlistUrl(string $text): ?string
    {
        if (! preg_match('~https?://[^\s]+~i', $text, $matches)) {
            return null;
        }

        $url = rtrim($matches[0], '.,;)');
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $path = parse_url($url, PHP_URL_PATH) ?: '';

        if (preg_match('/(^|\.)ozon\.ru$/i', $host) && preg_match('~^/my/favorites/?$~i', $path)) {
            return $url;
        }

        return null;
    }
}
