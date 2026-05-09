<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['marketplace', 'marketplace_product_id', 'title', 'canonical_url', 'image_url', 'currency'])]
class Product extends Model
{
    public function userProducts(): HasMany
    {
        return $this->hasMany(UserProduct::class);
    }
}
