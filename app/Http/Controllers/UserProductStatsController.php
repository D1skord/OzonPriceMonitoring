<?php

namespace App\Http\Controllers;

use App\Models\UserProduct;

final class UserProductStatsController
{
    public function __invoke(UserProduct $userProduct)
    {
        $userProduct->load('product');
        $snapshots = $userProduct->priceSnapshots()->oldest('captured_at')->get();

        $chartSeries = $snapshots->map(fn ($s) => [
            'x' => $s->captured_at->getTimestampMs(),
            'y' => round($s->price_minor / 100, 2),
        ])->values();

        return view('stats.user-product', [
            'userProduct' => $userProduct,
            'chartSeries' => $chartSeries,
        ]);
    }
}
