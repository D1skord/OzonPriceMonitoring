<?php

namespace App\Services\Parsing;

final class PriceTextParser
{
    public function parseToMinor(string $text): ?int
    {
        $normalized = preg_replace('/\x{00a0}|\x{202f}/u', ' ', $text) ?? $text;
        $normalized = preg_replace('/^от\s+/iu', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/[₽руб.]/iu', '', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        if (! preg_match('/\d[\d\s.,]*/u', $normalized, $matches)) {
            return null;
        }

        $integerText = preg_replace('/[^\d]/', '', $matches[0]) ?? '';

        if ($integerText === '') {
            return null;
        }

        return ((int) $integerText) * 100;
    }
}
