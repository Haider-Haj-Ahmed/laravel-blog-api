<?php

namespace App\Filament\Widgets;

use App\Models\Profile;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopRankingUsersWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Top 10 Ranking Users')
            ->query(
                Profile::query()
                    ->with('user')
                    ->whereHas('user')
                    ->orderByDesc('ranking_points')
                    ->limit(10)
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('rank')
                    ->label('#')
                    ->rowIndex()
                    ->badge()
                    ->color(fn (string $state) => match (true) {
                        (int) $state === 1 => 'warning',
                        (int) $state <= 3 => 'success',
                        default => 'gray',
                    }),
                ImageColumn::make('avatar')
                    ->circular()
                    ->defaultImageUrl(fn (Profile $record) => 'https://ui-avatars.com/api/?name='.urlencode($record->user->name ?? '?')),
                TextColumn::make('user.name')
                    ->label('User')
                    ->description(fn (Profile $record) => $record->user->username ? '@'.$record->user->username : null)
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('ranking_points')
                    ->label('Points')
                    ->numeric()
                    ->sortable()
                    ->icon(Heroicon::Trophy)
                    ->color('primary')
                    ->weight('bold'),
                TextColumn::make('badge')
                    ->label('Level')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'expert' => 'success',
                        'senior' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->since()
                    ->placeholder('Never')
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}
