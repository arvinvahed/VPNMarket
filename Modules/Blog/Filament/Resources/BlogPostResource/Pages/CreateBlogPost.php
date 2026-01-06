<?php
namespace Modules\Blog\Filament\Resources\BlogPostResource\Pages;

use Modules\Blog\Filament\Resources\BlogPostResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlogPost extends CreateRecord
{
    protected static string $resource = BlogPostResource::class;
}
