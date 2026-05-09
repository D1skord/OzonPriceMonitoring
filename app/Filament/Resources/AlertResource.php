<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AlertResource\Pages\ManageAlerts;
use App\Models\Alert;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class AlertResource extends Resource
{
    protected static ?string $model = Alert::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->sortable(),
            TextColumn::make('user.vk_user_id')->label('VK user')->sortable(),
            TextColumn::make('userProduct.title')->label('Product')->limit(70),
            TextColumn::make('new_price_minor')->numeric()->sortable(),
            TextColumn::make('previous_min_price_minor')->numeric()->sortable(),
            TextColumn::make('sent_at')->dateTime()->sortable(),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAlerts::route('/'),
        ];
    }
}
