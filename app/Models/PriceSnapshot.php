<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_product_id', 'product_id', 'wishlist_id', 'price_minor', 'old_price_minor', 'currency', 'availability', 'source_url', 'parser_version', 'raw', 'captured_at'])]
class PriceSnapshot extends Model
{
    protected function casts(): array
    {
        return [
            'raw' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function userProduct(): BelongsTo
    {
        return $this->belongsTo(UserProduct::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(Wishlist::class);
    }
}
