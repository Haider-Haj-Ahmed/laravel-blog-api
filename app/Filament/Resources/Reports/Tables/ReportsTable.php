<?php

namespace App\Filament\Resources\Reports\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('reporter.email')->label('Reporter')->searchable(),
                TextColumn::make('reportable_type')->label('Type')->toggleable(),
                TextColumn::make('reportable_id')->label('Target ID')->toggleable(),
                TextColumn::make('reason')->limit(24),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
