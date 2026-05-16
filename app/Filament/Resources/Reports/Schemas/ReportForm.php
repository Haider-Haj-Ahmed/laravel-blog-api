<?php

namespace App\Filament\Resources\Reports\Schemas;

use App\Models\Report;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('status')
                    ->options([
                        Report::STATUS_PENDING => 'Pending',
                        Report::STATUS_REVIEWED => 'Reviewed',
                        Report::STATUS_DISMISSED => 'Dismissed',
                        Report::STATUS_ACTION_TAKEN => 'Action taken',
                    ])
                    ->required(),
                Textarea::make('admin_notes')
                    ->label('Admin notes')
                    ->rows(4),
            ]);
    }
}
