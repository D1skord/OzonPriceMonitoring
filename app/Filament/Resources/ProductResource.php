<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages\ManageProducts;
use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('marketplace')->badge(),
                TextColumn::make('marketplace_product_id')->searchable(),
                TextColumn::make('title')->searchable()->limit(70),
                TextColumn::make('updated_at')->dateTime()->sortable(),
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
            'index' => ManageProducts::route('/'),
        ];
    }
}
