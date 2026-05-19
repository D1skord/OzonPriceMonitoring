<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\PriceSnapshot;
use App\Models\UserProduct;

final class AlertPolicy
{
    private readonly float $minDropPercent;

    public function __construct(?float $minDropPercent = null)
    {
        $this->minDropPercent = max(0.0, $minDropPercent ?? (float) config('services.crawl.min_alert_drop_percent', 0.0));
    }

    public function createHistoricalMinAlert(UserProduct $userProduct, PriceSnapshot $snapshot, ?int $previousMin): ?Alert
    {
        if ($previousMin === null || $previousMin <= 0 || $snapshot->price_minor >= $previousMin) {
            return null;
        }

        $dropPercent = ($previousMin - $snapshot->price_minor) / $previousMin * 100;

        if ($dropPercent < $this->minDropPercent) {
            return null;
        }

        return Alert::create([
            'user_id' => $userProduct->user_id,
            'user_product_id' => $userProduct->id,
            'price_snapshot_id' => $snapshot->id,
            'type' => 'historical_min',
            'new_price_minor' => $snapshot->price_minor,
            'previous_min_price_minor' => $previousMin,
            'payload' => [
                'title' => $userProduct->title,
                'url' => $userProduct->canonical_url,
            ],
        ]);
    }
}
