<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrawlRunResource\Pages\ManageCrawlRuns;
use App\Models\CrawlRun;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CrawlRunResource extends Resource
{
    protected static ?string $model = CrawlRun::class;

    protected static ?string $navigationLabel = 'Crawl Runs';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->sortable(),
            TextColumn::make('wishlist_id')->sortable(),
            TextColumn::make('user.vk_user_id')->label('VK user')->sortable(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('products_found')->numeric()->sortable(),
            TextColumn::make('alerts_created')->numeric()->sortable(),
            TextColumn::make('error_type')->searchable()->limit(40),
            TextColumn::make('error_message')->searchable()->limit(80),
            TextColumn::make('started_at')->dateTime()->sortable(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCrawlRuns::route('/'),
        ];
    }
}
