<?php

namespace Modules\Blog\Filament\Resources;

use Modules\Blog\Filament\Resources\BlogPostResource\Pages;
use Modules\Blog\Models\BlogPost;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;

class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'وبلاگ و محتوا';
    protected static ?string $navigationLabel = 'پست‌ها';
    protected static ?string $modelLabel = 'پست';
    protected static ?string $pluralModelLabel = 'لیست پست‌ها';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)->schema([
                    // ستون اصلی (بخش بزرگتر)
                    Grid::make(1)->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('عنوان پست')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) =>
                            $operation === 'create' ? $set('slug', Str::slug($state, '-', null)) : null
                            ),

                        Forms\Components\TextInput::make('slug')
                            ->label('آدرس یکتا (Slug)')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('برای سئو مهم است. ترجیحاً انگلیسی بنویسید.'),

                        Forms\Components\RichEditor::make('content')
                            ->label('محتوای مقاله')
                            ->required()
                            ->fileAttachmentsDirectory('blog_images'),

                        // بخش سئو
                        Section::make('تنظیمات سئو (SEO)')
                            ->schema([
                                Forms\Components\TextInput::make('seo_title')
                                    ->label('عنوان سئو (Title Tag)')
                                    ->maxLength(60),

                                Forms\Components\Textarea::make('seo_description')
                                    ->label('توضیحات سئو (Meta Description)')
                                    ->rows(2)
                                    ->maxLength(160),

                                Forms\Components\TagsInput::make('seo_keywords')
                                    ->label('کلمات کلیدی')
                                    ->separator(','),
                            ])
                            ->collapsed(), // به صورت پیش‌فرض بسته باشد
                    ])->columnSpan(2),

                    // ستون کناری (تنظیمات انتشار و عکس)
                    Grid::make(1)->schema([
                        Section::make('انتشار و مدیا')->schema([
                            Forms\Components\FileUpload::make('image')
                                ->label('تصویر شاخص')
                                ->image()
                                ->directory('blog_thumbnails')
                                ->required(),

                            Forms\Components\Select::make('category_id')
                                ->label('دسته‌بندی')
                                ->relationship('category', 'name')
                                ->preload()
                                ->searchable()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')->required(),
                                    Forms\Components\TextInput::make('slug')->required(),
                                ]),

                            Forms\Components\Toggle::make('is_published')
                                ->label('منتشر شود؟')
                                ->default(true),

                            Forms\Components\DateTimePicker::make('published_at')
                                ->label('تاریخ انتشار')
                                ->default(now()),
                        ]),
                    ])->columnSpan(1),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')->label('تصویر'),

                Tables\Columns\TextColumn::make('title')
                    ->label('عنوان')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('دسته')
                    ->badge(),

                Tables\Columns\IconColumn::make('is_published')
                    ->label('وضعیت')
                    ->boolean(),

                Tables\Columns\TextColumn::make('view_count')
                    ->label('بازدید')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ ایجاد')
                    ->dateTime('Y/m/d'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->label('دسته‌بندی'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlogPosts::route('/'),
            'create' => Pages\CreateBlogPost::route('/create'),
            'edit' => Pages\EditBlogPost::route('/{record}/edit'),
        ];
    }
}
