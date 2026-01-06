<?php
namespace Modules\Blog\Filament\Resources\BlogPostResource\Pages;

use Modules\Blog\Filament\Resources\BlogPostResource;
use Filament\Resources\Pages\EditRecord;

class EditBlogPost extends EditRecord
{
    protected static string $resource = BlogPostResource::class;
}
