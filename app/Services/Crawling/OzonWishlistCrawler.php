<?php

namespace App\Services\Crawling;

use App\Models\Wishlist;
use RuntimeException;
use Symfony\Component\Process\Process;

final class OzonWishlistCrawler
{
    public function fetchHtml(Wishlist $wishlist, bool $headless = true): string
    {
        $process = new Process(
            [
                'node',
                base_path('resources/playwright/ozon-wishlist-crawler.mjs'),
            ],
            base_path(),
            [
                'OZON_WISHLIST_URL' => $wishlist->url,
                'OZON_PROFILE_PATH' => (string) config('services.ozon.profile_path'),
                'CRAWLER_HEADLESS' => $headless ? '1' : '0',
            ],
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

        return $html;
    }
}
