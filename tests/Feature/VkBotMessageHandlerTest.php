<?php

namespace Tests\Feature;

use App\Jobs\CrawlWishlistJob;
use App\Models\User;
use App\Services\Vk\VkBotMessageHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

final class VkBotMessageHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_command_returns_intro(): void
    {
        $message = app(VkBotMessageHandler::class)->handle(10, 20, '/start');

        $this->assertStringContainsString('Ozon wishlist', $message);
        $this->assertDatabaseHas('users', ['vk_user_id' => 10, 'vk_peer_id' => 20]);
    }

    public function test_wishlist_link_binds_wishlist_and_dispatches_crawl(): void
    {
        Bus::fake();

        $message = app(VkBotMessageHandler::class)->handle(10, 20, 'https://ozon.ru/t/1EMSVNf');

        $this->assertStringContainsString('Wishlist привязан', $message);
        $this->assertDatabaseHas('wishlists', [
            'url' => 'https://ozon.ru/t/1EMSVNf',
            'is_active' => true,
        ]);
        Bus::assertDispatched(CrawlWishlistJob::class);
    }

    public function test_items_without_products_returns_empty_state(): void
    {
        User::factory()->create(['vk_user_id' => 10, 'vk_peer_id' => 20]);

        $message = app(VkBotMessageHandler::class)->handle(10, 20, '/items');

        $this->assertStringContainsString('Активных товаров пока нет', $message);
    }
}
