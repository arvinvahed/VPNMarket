<?php

namespace Modules\Blog\Filament\Resources;

use Modules\Blog\Filament\Resources\BlogCategoryResource\Pages;
use Modules\Blog\Models\BlogCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BlogCategoryResource extends Resource
{
    protected static ?string $model = BlogCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'وبلاگ و محتوا';
    protected static ?string $navigationLabel = 'دسته‌بندی‌ها';
    protected static ?string $modelLabel = 'دسته‌بندی';
    protected static ?string $pluralModelLabel = 'دسته‌بندی‌ها';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('نام دسته‌بندی')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) =>
                        $operation === 'create' ? $set('slug', Str::slug($state, '-', null)) : null
                        ),

                    Forms\Components\TextInput::make('slug')
                        ->label('آدرس یکتا (Slug)')
                        ->required()
                        ->unique(ignoreRecord: true),

                    Forms\Components\Textarea::make('description')
                        ->label('توضیحات')
                        ->rows(3),

                    Forms\Components\Toggle::make('is_active')
                        ->label('فعال')
                        ->default(true),
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('نام')->searchable(),
                Tables\Columns\TextColumn::make('slug')->label('اسلاگ'),
                Tables\Columns\IconColumn::make('is_active')->label('وضعیت')->boolean(),
                Tables\Columns\TextColumn::make('posts_count')->counts('posts')->label('تعداد پست'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlogCategories::route('/'),
            'create' => Pages\CreateBlogCategory::route('/create'),
            'edit' => Pages\EditBlogCategory::route('/{record}/edit'),
        ];
    }
}
