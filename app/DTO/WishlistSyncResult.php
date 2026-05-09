<?php

namespace App\DTO;

use App\Models\Alert;

final readonly class WishlistSyncResult
{
    /**
     * @param  list<Alert>  $alerts
     */
    public function __construct(
        public int $productsFound,
        public int $snapshotsCreated,
        public int $alertsCreated,
        public array $alerts,
    ) {}
}
