<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('marketplace')->default('ozon');
            $table->text('url');
            $table->text('normalized_url');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('baseline_crawled_at')->nullable();
            $table->timestamp('last_crawled_at')->nullable();
            $table->timestamp('next_crawl_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('marketplace')->default('ozon');
            $table->string('marketplace_product_id');
            $table->string('title');
            $table->text('canonical_url');
            $table->text('image_url')->nullable();
            $table->string('currency', 3)->default('RUB');
            $table->timestamps();

            $table->unique(['marketplace', 'marketplace_product_id']);
        });

        Schema::create('user_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wishlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('external_key');
            $table->string('title');
            $table->text('canonical_url');
            $table->text('image_url')->nullable();
            $table->unsignedBigInteger('current_price_minor')->nullable();
            $table->unsignedBigInteger('historical_min_price_minor')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['user_id', 'product_id']);
        });

        Schema::create('price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wishlist_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('price_minor');
            $table->unsignedBigInteger('old_price_minor')->nullable();
            $table->string('currency', 3)->default('RUB');
            $table->string('availability')->default('unknown');
            $table->text('source_url');
            $table->string('parser_version');
            $table->json('raw')->nullable();
            $table->timestamp('captured_at')->index();
            $table->timestamps();
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_snapshot_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('historical_min');
            $table->unsignedBigInteger('new_price_minor');
            $table->unsignedBigInteger('previous_min_price_minor')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('vk_message_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('crawl_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wishlist_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->index();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('products_found')->default(0);
            $table->unsignedInteger('snapshots_created')->default(0);
            $table->unsignedInteger('alerts_created')->default(0);
            $table->string('error_type')->nullable();
            $table->text('error_message')->nullable();
            $table->json('error_context')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_runs');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('price_snapshots');
        Schema::dropIfExists('user_products');
        Schema::dropIfExists('products');
        Schema::dropIfExists('wishlists');
    }
};
