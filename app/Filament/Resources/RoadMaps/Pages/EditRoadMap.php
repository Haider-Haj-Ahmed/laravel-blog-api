<?php

namespace App\Filament\Resources\RoadMaps\Pages;

use App\Filament\Resources\RoadMaps\RoadMapResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRoadMap extends EditRecord
{
    protected static string $resource = RoadMapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
