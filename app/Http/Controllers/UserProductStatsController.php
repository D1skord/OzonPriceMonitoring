<?php

namespace App\Http\Controllers;

use App\Models\UserProduct;

final class UserProductStatsController
{
    public function __invoke(UserProduct $userProduct)
    {
        $userProduct->load('product');
        $snapshots = $userProduct->priceSnapshots()->oldest('captured_at')->get();

        return view('stats.user-product', [
            'userProduct' => $userProduct,
            'snapshots' => $snapshots,
        ]);
    }
}
