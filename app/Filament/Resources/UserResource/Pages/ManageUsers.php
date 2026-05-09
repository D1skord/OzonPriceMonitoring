<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;
}
