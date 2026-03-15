<?php

namespace App\Filament\Resources\RoadMaps\Pages;

use App\Filament\Resources\RoadMaps\RoadMapResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoadMaps extends ListRecords
{
    protected static string $resource = RoadMapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
