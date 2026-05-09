<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WishlistResource\Pages\ManageWishlists;
use App\Models\Wishlist;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class WishlistResource extends Resource
{
    protected static ?string $model = Wishlist::class;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.vk_user_id')->label('VK user')->sortable(),
                TextColumn::make('marketplace')->badge(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('last_crawled_at')->dateTime()->sortable(),
                TextColumn::make('next_crawl_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWishlists::route('/'),
        ];
    }
}
