<?php

namespace App\Filament\Resources\RoadMaps;

use App\Filament\Resources\RoadMaps\Pages\CreateRoadMap;
use App\Filament\Resources\RoadMaps\Pages\EditRoadMap;
use App\Filament\Resources\RoadMaps\Pages\ListRoadMaps;
use App\Filament\Resources\RoadMaps\RelationManagers\NodesRelationManager;
use App\Filament\Resources\RoadMaps\Schemas\RoadMapForm;
use App\Filament\Resources\RoadMaps\Tables\RoadMapsTable;
use App\Models\RoadMap;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RoadMapResource extends Resource
{
    protected static ?string $model = RoadMap::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'road maps';

    public static function form(Schema $schema): Schema
    {
        return RoadMapForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoadMapsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            NodesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoadMaps::route('/'),
            'create' => CreateRoadMap::route('/create'),
            'edit' => EditRoadMap::route('/{record}/edit'),
        ];
    }
}
