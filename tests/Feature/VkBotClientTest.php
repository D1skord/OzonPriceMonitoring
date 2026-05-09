<?php

namespace Tests\Feature;

use App\Services\Vk\VkBotClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class VkBotClientTest extends TestCase
{
    public function test_send_message_adds_persistent_keyboard(): void
    {
        Http::fake([
            'https://api.vk.com/method/messages.send*' => Http::response(['response' => 123]),
        ]);

        app(VkBotClient::class)->sendMessage(10, 'Привет');

        Http::assertSent(function (Request $request): bool {
            $keyboard = json_decode((string) $request['keyboard'], true);

            return str_starts_with($request->url(), 'https://api.vk.com/method/messages.send?')
                && (int) $request['peer_id'] === 10
                && $request['message'] === 'Привет'
                && $keyboard['one_time'] === false
                && $keyboard['buttons'][0][0]['action']['label'] === 'Мои товары'
                && $keyboard['buttons'][0][1]['action']['label'] === 'Помощь';
        });
    }
}
