<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'marketplace', 'url', 'normalized_url', 'is_active', 'baseline_crawled_at', 'last_crawled_at', 'next_crawl_at'])]
class Wishlist extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'baseline_crawled_at' => 'datetime',
            'last_crawled_at' => 'datetime',
            'next_crawl_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userProducts(): HasMany
    {
        return $this->hasMany(UserProduct::class);
    }

    public function crawlRuns(): HasMany
    {
        return $this->hasMany(CrawlRun::class);
    }
}
