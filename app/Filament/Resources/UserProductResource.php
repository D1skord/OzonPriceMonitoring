<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserProductResource\Pages\ManageUserProducts;
use App\Models\UserProduct;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class UserProductResource extends Resource
{
    protected static ?string $model = UserProduct::class;

    protected static ?string $navigationLabel = 'User Products';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.vk_user_id')->label('VK user')->sortable(),
                TextColumn::make('title')->searchable()->limit(70),
                TextColumn::make('current_price_minor')->numeric()->sortable(),
                TextColumn::make('historical_min_price_minor')->numeric()->sortable(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('last_seen_at')->dateTime()->sortable(),
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
            'index' => ManageUserProducts::route('/'),
        ];
    }
}
