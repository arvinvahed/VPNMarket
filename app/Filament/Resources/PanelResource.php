<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PanelResource\Pages;
use App\Filament\Resources\PanelResource\RelationManagers;
use App\Models\Panel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PanelResource extends Resource
{
    protected static ?string $model = Panel::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationGroup = 'مدیریت پنل‌ها';

    protected static ?string $navigationLabel = 'پنل‌های V2Ray';

    protected static ?string $pluralModelLabel = 'پنل‌های V2Ray';

    protected static ?string $modelLabel = 'پنل V2Ray';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('نام پنل')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('url')
                    ->label('آدرس URL پنل')
                    ->required()
                    ->url()
                    ->maxLength(255)
                    ->helperText('مثال: https://panel.example.com'),
                Forms\Components\Select::make('panel_type')
                    ->label('نوع پنل')
                    ->options([
                        'marzban' => 'مرزبان',
                        'marzneshin' => 'مرزنشین',
                        'xui' => 'سنایی / X-UI',
                        'v2ray' => 'V2Ray',
                        'other' => 'سایر',
                    ])
                    ->required()
                    ->default('marzban'),
                Forms\Components\TextInput::make('username')
                    ->label('نام کاربری')
                    ->maxLength(255)
                    ->helperText('نام کاربری ادمین پنل'),
                Forms\Components\TextInput::make('password')
                    ->label('رمز عبور')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->helperText('رمز عبور به صورت رمزنگاری شده ذخیره می‌شود'),
                Forms\Components\TextInput::make('api_token')
                    ->label('توکن API')
                    ->password()
                    ->dehydrated(fn ($state) => filled($state))
                    ->helperText('در صورت نیاز، توکن API پنل را وارد کنید'),
                Forms\Components\KeyValue::make('extra')
                    ->label('تنظیمات اضافی')
                    ->helperText('تنظیمات خاص پنل (مثل node_hostname، default_inbound_id و...)'),
                Forms\Components\Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('نام پنل')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('url')
                    ->label('آدرس URL')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('panel_type')
                    ->label('نوع پنل')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'marzban' => 'success',
                        'marzneshin' => 'info',
                        'xui' => 'warning',
                        'v2ray' => 'primary',
                        'other' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'marzban' => 'مرزبان',
                        'marzneshin' => 'مرزنشین',
                        'xui' => 'سنایی / X-UI',
                        'v2ray' => 'V2Ray',
                        'other' => 'سایر',
                        default => $state,
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ ایجاد')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePanels::route('/'),
        ];
    }
}
