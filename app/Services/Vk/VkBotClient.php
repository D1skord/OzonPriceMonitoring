<?php

namespace App\Services\Vk;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class VkBotClient
{
    public function sendMessage(int $peerId, string $message): ?string
    {
        $response = $this->client()->post('messages.send', [
            'peer_id' => $peerId,
            'message' => $message,
            'keyboard' => json_encode($this->keyboard(), JSON_UNESCAPED_UNICODE),
            'random_id' => random_int(1, PHP_INT_MAX),
        ])->json();

        if (isset($response['error'])) {
            throw new RuntimeException($response['error']['error_msg'] ?? 'VK messages.send failed');
        }

        return isset($response['response']) ? (string) $response['response'] : null;
    }

    /**
     * @return array{server: string, key: string, ts: string}
     */
    public function getLongPollServer(): array
    {
        $response = $this->client()->get('groups.getLongPollServer', [
            'group_id' => config('services.vk.group_id'),
        ])->json();

        if (isset($response['error']) || ! isset($response['response'])) {
            throw new RuntimeException($response['error']['error_msg'] ?? 'VK Long Poll server failed');
        }

        return $response['response'];
    }

    /**
     * @return array<string, mixed>
     */
    public function poll(string $server, string $key, string $ts): array
    {
        return Http::timeout(((int) config('services.vk.long_poll_wait', 25)) + 5)->get($server, [
            'act' => 'a_check',
            'key' => $key,
            'ts' => $ts,
            'wait' => config('services.vk.long_poll_wait', 25),
        ])->json();
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl('https://api.vk.com/method/')
            ->timeout(20)
            ->asForm()
            ->withOptions(['query' => [
                'access_token' => config('services.vk.bot_token'),
                'v' => config('services.vk.api_version', '5.199'),
            ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function keyboard(): array
    {
        return [
            'one_time' => false,
            'inline' => false,
            'buttons' => [
                [
                    $this->textButton('Мои товары', 'primary'),
                    $this->textButton('Помощь', 'secondary'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function textButton(string $label, string $color): array
    {
        return [
            'action' => [
                'type' => 'text',
                'label' => $label,
                'payload' => json_encode(['command' => $label], JSON_UNESCAPED_UNICODE),
            ],
            'color' => $color,
        ];
    }
}
