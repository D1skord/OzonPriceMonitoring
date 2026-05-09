<?php

namespace App\Services\Parsing;

final class ProductUrlNormalizer
{
    public function normalize(string $url, string $baseUrl): string
    {
        $absolute = $this->toAbsolute($url, $baseUrl);
        $parts = parse_url($absolute);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return $absolute;
        }

        $path = $parts['path'] ?? '/';

        return $parts['scheme'].'://'.$parts['host'].$path;
    }

    public function extractProductId(string $url, string $marketplace): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';

        if ($marketplace === 'wildberries') {
            return preg_match('~/catalog/(\d+)/detail\.aspx~i', $path, $matches) ? $matches[1] : null;
        }

        if (preg_match('/-(\d+)\/?$/', $path, $matches)) {
            return $matches[1];
        }

        return preg_match('~/product/(?:[^/]+/)?(\d+)/?$~i', $path, $matches) ? $matches[1] : null;
    }

    public function fallbackProductId(string $url, string $title): string
    {
        return 'fallback_'.substr(sha1($url.'|'.$title), 0, 12);
    }

    private function toAbsolute(string $url, string $baseUrl): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? 'www.ozon.ru';

        if (str_starts_with($url, '//')) {
            return $scheme.':'.$url;
        }

        if (str_starts_with($url, '/')) {
            return $scheme.'://'.$host.$url;
        }

        return $scheme.'://'.$host.'/'.$url;
    }
}
