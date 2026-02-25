<?php

namespace Modules\Reseller\Filament\Resources;

use Modules\Reseller\Filament\Resources\VpnProductResource\Pages;
use Modules\Reseller\Models\VpnProduct;
use Modules\Reseller\Models\VpnServer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VpnProductResource extends Resource
{
    protected static ?string $model = VpnProduct::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'مدیریت نمایندگی';

    protected static ?string $navigationLabel = 'محصولات VPN';

    protected static ?string $pluralLabel = 'محصولات VPN';

    protected static ?string $label = 'محصول VPN';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('اطلاعات محصول')
                    ->schema([
                        Forms\Components\Select::make('server_id')
                            ->label('سرور')
                            ->relationship('server', 'name')
                            ->required()
                            ->searchable()
                            ->helperText('سرور میزبانی این محصول'),
                        Forms\Components\TextInput::make('name')
                            ->label('نام محصول')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('مثال: VLESS TCP ایران'),
                        Forms\Components\TextInput::make('remote_id')
                            ->label('شناسه ریموت (Inbound ID / Tag)')
                            ->helperText('سنایی: Inbound ID (مثال: 1). مرزبان: Inbound Tag (مثال: INBOUND_VLESS_TCP).')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('1 یا INBOUND_VLESS_TCP'),
                        Forms\Components\Select::make('protocol')
                            ->label('پروتکل')
                            ->options([
                                'vless' => 'VLESS',
                                'vmess' => 'VMess',
                                'trojan' => 'Trojan',
                                'shadowsocks' => 'Shadowsocks',
                            ])
                            ->required()
                            ->helperText('پروتکل اتصال VPN'),
                        Forms\Components\TextInput::make('traffic_limit')
                            ->label('محدودیت ترافیک (گیگابایت)')
                            ->numeric()
                            ->default(0)
                            ->helperText('0 یعنی نامحدود')
                            ->suffix('GB'),
                        Forms\Components\TextInput::make('period_days')
                            ->label('مدت زمان (روز)')
                            ->numeric()
                            ->default(30)
                            ->helperText('مدت اعتبار اکانت‌ها به روز')
                            ->suffix('روز'),
                        Forms\Components\TextInput::make('base_price')
                            ->label('قیمت پایه برای نماینده')
                            ->numeric()
                            ->prefix('تومان')
                            ->default(0)
                            ->helperText('قیمت پایه فروش به نمایندگان'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('فعال')
                            ->default(true)
                            ->helperText('وضعیت فعال بودن محصول'),
                        Forms\Components\Textarea::make('description')
                            ->label('توضیحات')
                            ->columnSpanFull()
                            ->placeholder('توضیحات تکمیلی درباره این محصول...'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('server.name')
                    ->label('سرور')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('نام محصول')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('protocol')
                    ->label('پروتکل')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'vless' => 'success',
                        'vmess' => 'warning',
                        'trojan' => 'danger',
                        'shadowsocks' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('traffic_limit')
                    ->label('محدودیت ترافیک')
                    ->numeric()
                    ->formatStateUsing(fn (?int $state): string => $state === 0 ? 'نامحدود' : number_format($state) . ' GB')
                    ->sortable(),
                Tables\Columns\TextColumn::make('period_days')
                    ->label('مدت اعتبار')
                    ->numeric()
                    ->suffix(' روز')
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_price')
                    ->label('قیمت پایه')
                    ->money('IRR', divideBy: 1)
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('وضعیت')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ ایجاد')
                    ->date('Y/m/d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('server')
                    ->label('سرور')
                    ->relationship('server', 'name'),
                Tables\Filters\SelectFilter::make('protocol')
                    ->label('پروتکل')
                    ->options([
                        'vless' => 'VLESS',
                        'vmess' => 'VMess',
                        'trojan' => 'Trojan',
                        'shadowsocks' => 'Shadowsocks',
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
            ->emptyStateHeading('هیچ محصولی یافت نشد')
            ->emptyStateDescription('محصولات VPN را در این بخش مدیریت کنید');
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
            'index' => Pages\ListVpnProducts::route('/'),
            'create' => Pages\CreateVpnProduct::route('/create'),
            'edit' => Pages\EditVpnProduct::route('/{record}/edit'),
        ];
    }
}