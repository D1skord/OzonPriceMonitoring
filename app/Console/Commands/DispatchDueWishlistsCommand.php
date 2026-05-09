<?php

namespace App\Console\Commands;

use App\Jobs\CrawlWishlistJob;
use App\Models\Wishlist;
use Illuminate\Console\Command;

final class DispatchDueWishlistsCommand extends Command
{
    protected $signature = 'wishlists:dispatch-due';

    protected $description = 'Dispatch crawl jobs for due active wishlists.';

    public function handle(): int
    {
        $count = 0;

        Wishlist::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('next_crawl_at')
                    ->orWhere('next_crawl_at', '<=', now());
            })
            ->orderBy('id')
            ->each(function (Wishlist $wishlist) use (&$count): void {
                CrawlWishlistJob::dispatch($wishlist->id);
                $count++;
            });

        $this->info("Dispatched {$count} wishlist crawl jobs.");

        return self::SUCCESS;
    }
}
