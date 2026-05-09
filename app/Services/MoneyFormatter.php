<?php

namespace App\Services;

final class MoneyFormatter
{
    public function rubles(int $minor): string
    {
        return number_format($minor / 100, 0, '.', ' ').' ₽';
    }
}
