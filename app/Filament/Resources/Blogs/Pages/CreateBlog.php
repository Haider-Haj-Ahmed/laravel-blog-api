<?php

namespace App\Filament\Resources\Blogs\Pages;

use App\Filament\Resources\Blogs\BlogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlog extends CreateRecord
{
    protected static string $resource = BlogResource::class;
}
