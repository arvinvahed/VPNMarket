<?php

namespace Modules\Blog\Filament\Resources\BlogCategoryResource\Pages;

use Modules\Blog\Filament\Resources\BlogCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlogCategory extends CreateRecord
{
    protected static string $resource = BlogCategoryResource::class;
}
