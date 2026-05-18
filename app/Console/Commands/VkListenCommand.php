<?php

namespace App\Console\Commands;

use App\Services\Vk\VkBotClient;
use App\Services\Vk\VkBotMessageHandler;
use Illuminate\Console\Command;
use Throwable;

final class VkListenCommand extends Command
{
    protected $signature = 'vk:listen';

    protected $description = 'Listen VK Bot Long Poll events.';

    public function handle(VkBotClient $client, VkBotMessageHandler $handler): int
    {
        if (! $client->isEnabled()) {
            $this->warn('VK_BOT_TOKEN is not set — vk:listen is disabled in this environment.');

            return self::SUCCESS;
        }

        $server = $client->getLongPollServer();

        while (true) {
            try {
                $payload = $client->poll($server['server'], $server['key'], (string) $server['ts']);

                if (isset($payload['failed'])) {
                    $server = $client->getLongPollServer();

                    continue;
                }

                $server['ts'] = (string) ($payload['ts'] ?? $server['ts']);

                foreach (($payload['updates'] ?? []) as $update) {
                    if (($update['type'] ?? null) !== 'message_new') {
                        continue;
                    }

                    $message = $update['object']['message'] ?? [];
                    $peerId = (int) ($message['peer_id'] ?? 0);
                    $vkUserId = (int) ($message['from_id'] ?? 0);
                    $text = (string) ($message['text'] ?? '');

                    if ($peerId <= 0 || $vkUserId <= 0 || $text === '') {
                        continue;
                    }

                    $client->sendMessage($peerId, $handler->handle($vkUserId, $peerId, $text));
                }
            } catch (Throwable $exception) {
                report($exception);
                sleep(3);
                $server = $client->getLongPollServer();
            }
        }
    }
}
