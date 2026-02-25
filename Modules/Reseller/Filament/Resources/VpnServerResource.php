<?php

namespace Modules\Reseller\Filament\Resources;

use Modules\Reseller\Filament\Resources\VpnServerResource\Pages;
use Modules\Reseller\Models\VpnServer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VpnServerResource extends Resource
{
    protected static ?string $model = VpnServer::class;

    protected static ?string $navigationIcon = 'heroicon-o-server';

    protected static ?string $navigationGroup = 'مدیریت نمایندگی';

    protected static ?string $navigationLabel = 'سرورهای VPN';

    protected static ?string $pluralLabel = 'سرورهای VPN';

    protected static ?string $label = 'سرور VPN';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('اطلاعات سرور')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('نام سرور')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('مثال: سرور ایران 1'),
                        Forms\Components\Select::make('type')
                            ->label('نوع پنل')
                            ->options([
                                'sanaei' => 'سنایی (X-UI)',
                                'marzban' => 'مرزبان',
                            ])
                            ->required()
                            ->helperText('نوع پنل مدیریت سرور'),
                        Forms\Components\TextInput::make('ip_address')
                            ->label('آدرس IP / دامنه')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('مثال: 192.168.1.1 یا server.example.com')
                            ->helperText('آدرس اتصال به سرور'),
                        Forms\Components\TextInput::make('port')
                            ->label('پورت')
                            ->numeric()
                            ->default(2053)
                            ->required()
                            ->helperText('پورت اتصال به پنل'),
                        Forms\Components\TextInput::make('subscription_port')
                            ->label('پورت اینباند / کانفیگ')
                            ->numeric()
                            ->nullable()
                            ->helperText('پورتی که لینک سابسکرایب و کانفیگ روی آن ساخته می‌شود (مثلاً 2096)'),
                        Forms\Components\Select::make('subscription_mode')
                            ->label('نوع لینک خروجی')
                            ->options([
                                'subscribe' => 'لینک سابسکرایب (sub/...)',
                                'single' => 'لینک تکی کانفیگ (vless://...)',
                            ])
                            ->default('subscribe')
                            ->required()
                            ->helperText('انتخاب کن لینک خروجی برای این سرور سابسکرایب باشد یا لینک تکی.'),
                        Forms\Components\Toggle::make('is_https')
                            ->label('استفاده از HTTPS')
                            ->default(false)
                            ->helperText('آیا اتصال امن HTTPS استفاده شود؟'),
                        Forms\Components\TextInput::make('username')
                            ->label('نام کاربری')
                            ->maxLength(255)
                            ->placeholder('نام کاربری پنل')
                            ->helperText('نام کاربری ورود به پنل'),
                        Forms\Components\TextInput::make('password')
                            ->label('رمز عبور')
                            ->password()
                            ->maxLength(255)
                            ->helperText('رمز عبور ورود به پنل'),
                        Forms\Components\TextInput::make('api_path')
                            ->label('مسیر API')
                            ->default('/panel/api/inbounds')
                            ->required()
                            ->helperText('مسیر API پنل برای دریافت اطلاعات'),
                        Forms\Components\TextInput::make('sub_url_template')
                            ->label('قالب آدرس اشتراک')
                            ->placeholder('https://sub.example.com')
                            ->helperText('اختیاری. اگر خالی باشد، از آدرس API استفاده می‌شود.')
                            ->prefix(fn (Forms\Get $get) => $get('is_https') ? 'https://' : 'http://'),
                        Forms\Components\KeyValue::make('config')
                            ->label('تنظیمات اضافی')
                            ->keyLabel('کلید')
                            ->valueLabel('مقدار')
                            ->helperText('تنظیمات سفارشی برای این سرور'),
                        Forms\Components\TextInput::make('capacity')
                            ->label('ظرفیت')
                            ->numeric()
                            ->default(0)
                            ->helperText('0 یعنی نامحدود')
                            ->suffix('کاربر'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('فعال')
                            ->default(true)
                            ->helperText('وضعیت فعال بودن سرور'),
                        Forms\Components\Textarea::make('description')
                            ->label('توضیحات')
                            ->columnSpanFull()
                            ->placeholder('توضیحات تکمیلی درباره این سرور...'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('نام سرور')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('نوع پنل')
                    ->badge()
                    ->colors([
                        'primary' => 'sanaei',
                        'success' => 'marzban',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sanaei' => 'سنایی',
                        'marzban' => 'مرزبان',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('آدرس')
                    ->formatStateUsing(fn (string $state, VpnServer $record): string => 
                        $record->is_https ? 'https://' . $state : 'http://' . $state
                    ),
                Tables\Columns\TextColumn::make('port')
                    ->label('پورت')
                    ->suffix(fn (VpnServer $record): string => $record->is_https ? ' (HTTPS)' : ' (HTTP)'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('وضعیت')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\TextColumn::make('capacity')
                    ->label('ظرفیت')
                    ->numeric()
                    ->formatStateUsing(fn (?int $state): string => $state === 0 ? 'نامحدود' : number_format($state)),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('تعداد محصولات')
                    ->counts('products')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ ایجاد')
                    ->date('Y/m/d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('نوع پنل')
                    ->options([
                        'sanaei' => 'سنایی',
                        'marzban' => 'مرزبان',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('وضعیت فعال')
                    ->trueLabel('فعال')
                    ->falseLabel('غیرفعال')
                    ->placeholder('همه'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('ویرایش'),
                Tables\Actions\DeleteAction::make()
                    ->label('حذف'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('حذف انتخاب شده‌ها'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('هیچ سروری یافت نشد')
            ->emptyStateDescription('سرورهای VPN را در این بخش مدیریت کنید');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVpnServers::route('/'),
            'create' => Pages\CreateVpnServer::route('/create'),
            'edit' => Pages\EditVpnServer::route('/{record}/edit'),
        ];
    }
}
