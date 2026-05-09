<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PriceSnapshotResource\Pages\ManagePriceSnapshots;
use App\Models\PriceSnapshot;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class PriceSnapshotResource extends Resource
{
    protected static ?string $model = PriceSnapshot::class;

    protected static ?string $navigationLabel = 'Snapshots';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->sortable(),
            TextColumn::make('userProduct.title')->label('Product')->limit(70),
            TextColumn::make('price_minor')->numeric()->sortable(),
            TextColumn::make('availability')->badge(),
            TextColumn::make('parser_version')->badge(),
            TextColumn::make('captured_at')->dateTime()->sortable(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePriceSnapshots::route('/'),
        ];
    }
}
