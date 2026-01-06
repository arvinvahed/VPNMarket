<?php

namespace Modules\Blog\Filament\Resources\BlogCategoryResource\Pages;

use Modules\Blog\Filament\Resources\BlogCategoryResource;
use Filament\Resources\Pages\EditRecord;

class EditBlogCategory extends EditRecord
{
    protected static string $resource = BlogCategoryResource::class;
}
