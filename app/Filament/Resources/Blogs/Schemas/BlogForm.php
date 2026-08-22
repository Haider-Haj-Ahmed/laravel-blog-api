<?php

namespace App\Filament\Resources\Blogs\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BlogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('cover_image_path')
                    ->image(),
                TextInput::make('reading_time')
                    ->default(null),
                TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                TextInput::make('title')
                    ->required(),
                Textarea::make('subtitle')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('is_published')
                    ->required(),
                Toggle::make('is_modified')
                    ->required(),
                TextInput::make('comments_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('likes_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('views_count')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
