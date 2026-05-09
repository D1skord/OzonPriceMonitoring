<?php

namespace App\Services\Crawling;

use App\Models\Wishlist;
use RuntimeException;
use Symfony\Component\Process\Process;

final class OzonWishlistCrawler
{
    public function fetchHtml(Wishlist $wishlist, bool $headless = false): string
    {
        $command = [
            'node',
            base_path('resources/playwright/ozon-wishlist-crawler.mjs'),
        ];
        $environment = [
            'OZON_WISHLIST_URL' => $wishlist->url,
            'OZON_PROFILE_PATH' => (string) config('services.ozon.profile_path'),
            'CRAWLER_HEADLESS' => $headless ? '1' : '0',
        ];

        if (! $headless) {
            $command = [
                'bash',
                '-lc',
                implode(' ', [
                    'set -e;',
                    'rm -f /tmp/.X98-lock;',
                    'Xvfb :98 -screen 0 1440x1200x24 >/tmp/ozon-crawler-xvfb.log 2>&1 &',
                    'xvfb_pid=$!;',
                    'trap "kill $xvfb_pid 2>/dev/null || true" EXIT;',
                    'for _ in {1..50}; do [ -S /tmp/.X11-unix/X98 ] && break; sleep 0.1; done;',
                    'DISPLAY=:98 node resources/playwright/ozon-wishlist-crawler.mjs;',
                ]),
            ];
        }

        $process = new Process(
            $command,
            base_path(),
            $environment,
            null,
            120
        );

        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Ozon crawler process failed.');
        }

        $html = $process->getOutput();

        if (! is_string($html) || $html === '') {
            throw new RuntimeException('Ozon crawler returned empty HTML.');
        }

        if (str_contains($html, 'Доступ ограничен') || str_contains($html, 'abt-challenge')) {
            throw new RuntimeException('Ozon blocked crawler access.');
        }

        return $html;
    }
}
