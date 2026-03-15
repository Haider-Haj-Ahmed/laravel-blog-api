<?php

namespace App\Filament\Resources\RoadMaps\RelationManagers;

use App\Filament\Resources\RoadMaps\RoadMapResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
// use Illuminate\Support\Facades\Schema;

class NodesRelationManager extends RelationManager
{
    protected static string $relationship = 'nodes';
    protected static ?string $recordTitleAttribute = 'nodes';
    protected static ?string $relationshipTitle='Nodes';
    protected static ?string $title='Nodes';
    protected static ?string $label="Node";

    protected static ?string $relatedResource = RoadMapResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
            TextInput::make('step_number')
                ->label('Step Number')
                ->numeric()
                ->required(),

            TextInput::make('title')
                ->label('Title')
                ->required()
                ->maxLength(255),

            TextInput::make('url')
                ->label('Resource URL')
                ->url()
                ->required()
                ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('step_number')
                    ->label('Step')
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Title')
                    ->searchable(),

                TextColumn::make('url')
                    ->label('URL')
                    ->limit(40),
            ])
            ->defaultSort('step_number')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
