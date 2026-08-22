<?php

namespace App\Filament\Resources\Blogs\Pages;

use App\Filament\Resources\Blogs\BlogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlogs extends ListRecords
{
    protected static string $resource = BlogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
