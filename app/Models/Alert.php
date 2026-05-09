<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'user_product_id', 'price_snapshot_id', 'type', 'new_price_minor', 'previous_min_price_minor', 'sent_at', 'vk_message_id', 'payload'])]
class Alert extends Model
{
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userProduct(): BelongsTo
    {
        return $this->belongsTo(UserProduct::class);
    }

    public function priceSnapshot(): BelongsTo
    {
        return $this->belongsTo(PriceSnapshot::class);
    }
}
