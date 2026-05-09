<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\PriceSnapshot;
use App\Models\UserProduct;

final class AlertPolicy
{
    public function createHistoricalMinAlert(UserProduct $userProduct, PriceSnapshot $snapshot, ?int $previousMin): ?Alert
    {
        if ($previousMin === null || $snapshot->price_minor >= $previousMin) {
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
