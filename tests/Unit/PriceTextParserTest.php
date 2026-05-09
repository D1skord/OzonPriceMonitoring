<?php

namespace Tests\Unit;

use App\Services\Parsing\PriceTextParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PriceTextParserTest extends TestCase
{
    #[DataProvider('prices')]
    public function test_parses_ruble_price_to_minor_units(string $text, ?int $expected): void
    {
        $this->assertSame($expected, (new PriceTextParser)->parseToMinor($text));
    }

    public static function prices(): array
    {
        return [
            ['4 990 ₽', 499000],
            ['от 1 299 руб.', 129900],
            ["12\u{00a0}345 ₽", 1234500],
            ['без цены', null],
        ];
    }
}
