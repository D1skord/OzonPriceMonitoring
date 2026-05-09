<?php

namespace App\Filament\Resources\UserProductResource\Pages;

use App\Filament\Resources\UserProductResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageUserProducts extends ManageRecords
{
    protected static string $resource = UserProductResource::class;
}
