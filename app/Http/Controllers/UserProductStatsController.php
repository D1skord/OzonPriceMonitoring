<?php

namespace App\Http\Controllers;

use App\Models\UserProduct;

final class UserProductStatsController
{
    public function __invoke(UserProduct $userProduct)
    {
        $userProduct->load('product');
        $snapshots = $userProduct->priceSnapshots()->oldest('captured_at')->get();

        // Keep only points where price changes — removes flat runs of identical prices.
        // Also keep the last point of each run so the step chart shows correct duration.
        $chartSeries = $snapshots
            ->map(fn ($s) => [
                'x' => $s->captured_at->getTimestampMs(),
                'y' => round($s->price_minor / 100, 2),
            ])
            ->pipe(function ($points) {
                $result = collect();
                $prev = null;

                foreach ($points as $i => $point) {
                    $next = $points->get($i + 1);

                    $priceChanged = $prev === null || $point['y'] !== $prev['y'];
                    $priceAboutToChange = $next !== null && $next['y'] !== $point['y'];
                    $isLast = $next === null;

                    if ($priceChanged || $priceAboutToChange || $isLast) {
                        $result->push($point);
                    }

                    $prev = $point;
                }

                return $result;
            })
            ->values();

        return view('stats.user-product', [
            'userProduct' => $userProduct,
            'chartSeries' => $chartSeries,
        ]);
    }
}
