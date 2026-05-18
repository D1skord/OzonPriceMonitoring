<?php

namespace App\Services\Vk;

use App\Jobs\CrawlWishlistJob;
use App\Models\User;
use App\Models\UserProduct;
use App\Services\MoneyFormatter;
use App\Services\WishlistSyncService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

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
            return 'Пришли ссылку на Ozon wishlist. Я буду проверять цены каждые 15 минут и писать, когда товар обновит исторический минимум.';
        }

        if (in_array($lower, ['/help', 'help', 'помощь'], true)) {
            return implode("\n", [
                'Команды:',
                'Помощь - помощь',
                'Мои товары - список твоих товаров',
                '/item ID - краткая статистика товара',
                'или пришли ссылку на Ozon wishlist.',
            ]);
        }

        if (in_array($lower, ['/items', 'мои товары'], true)) {
            return $this->itemsMessage($user);
        }

        if (preg_match('/^\/item\s+(\d+)$/', $lower, $matches)) {
            return $this->itemMessage($user, (int) $matches[1]);
        }

        $wishlistUrl = $this->extractWishlistUrl($text);

        if ($wishlistUrl !== null) {
            $wishlist = $this->wishlistSyncService->bindWishlist($user, $wishlistUrl);
            Bus::dispatch(new CrawlWishlistJob($wishlist->id));

            return implode("\n\n", [
                'Вишлист подключён! Загружаю товары — это займёт пару минут.',
                'Первые цены зафиксирую без уведомлений, чтобы не присылать сразу всё. Уведомления начнут приходить со следующего обхода.',
            ]);
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
            $hasWishlist = $user->wishlists()->where('is_active', true)->exists();

            if ($hasWishlist) {
                return 'Вишлист подключён, но товары ещё не загружены. Подожди пару минут — идёт первый обход.';
            }

            return 'Активных товаров нет. Пришли ссылку на Ozon wishlist.';
        }

        $blocks = ['Твои товары ('.$items->count().')'];

        foreach ($items as $item) {
            $price = $item->current_price_minor !== null ? $this->moneyFormatter->rubles($item->current_price_minor) : 'цена неизвестна';
            $block = $this->shortTitle($item->title).' — '.$price;
            if ($item->canonical_url) {
                $block .= "\n".$item->canonical_url;
            }
            $blocks[] = $block;
        }

        $blocks[] = 'Подробная статистика: /item ID';

        return implode("\n\n", $blocks);
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
            $this->shortTitle($item->title),
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

    private function shortTitle(string $title, int $limit = 45): string
    {
        return Str::limit($title, $limit, '…');
    }

    private function extractWishlistUrl(string $text): ?string
    {
        if (! preg_match('~https?://[^\s]+~i', $text, $matches)) {
            return null;
        }

        $url = rtrim($matches[0], '.,;)');
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $path = parse_url($url, PHP_URL_PATH) ?: '';

        if (preg_match('/(^|\.)ozon\.ru$/i', $host) && preg_match('~^(?:/my/favorites/?|/t/[A-Za-z0-9_-]+/?)$~i', $path)) {
            return $url;
        }

        return null;
    }
}
