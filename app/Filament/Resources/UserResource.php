<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages\ManageUsers;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'VK Users';

    protected static ?string $modelLabel = 'VK User';

    protected static ?string $pluralModelLabel = 'VK Users';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('vk_user_id')->searchable()->sortable(),
                TextColumn::make('vk_peer_id')->searchable(),
                TextColumn::make('name')->searchable(),
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
            'index' => ManageUsers::route('/'),
        ];
    }
}
