<?php

namespace Modules\Reseller\Filament\Resources;

use Modules\Reseller\Filament\Resources\ResellerPlanResource\Pages;
use Modules\Reseller\Models\ResellerPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ResellerPlanResource extends Resource
{
    protected static ?string $model = ResellerPlan::class;

    protected static ?string $navigationGroup = 'مدیریت نمایندگی';

    protected static ?string $navigationLabel = 'پلن‌های نمایندگی';

    protected static ?string $pluralLabel = 'پلن‌های نمایندگی';

    protected static ?string $label = 'پلن نمایندگی';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('تنظیمات پلن')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('نام پلن')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('مثال: پلن طلایی'),
                        Forms\Components\Select::make('type')
                            ->label('نوع پلن')
                            ->options([
                                'quota' => 'پلن محدود (تعداد مشخص)',  
                                'pay_as_you_go' => 'پلن نامحدود (پرداخت به ازای هر اکانت)',
                            ])
                            ->required()
                            ->reactive()
                            ->helperText('نوع پلن را انتخاب کنید'),
                        Forms\Components\TextInput::make('price')
                            ->label('هزینه اشتراک (تومان)')
                            ->numeric()
                            ->prefix('تومان')
                            ->default(0)
                            ->helperText('هزینه اشتراک ماهانه پلن'),
                        Forms\Components\TextInput::make('price_per_account')
                            ->label('هزینه هر اکانت (تومان)')
                            ->numeric()
                            ->prefix('تومان')
                            ->default(0)
                            ->visible(fn (Forms\Get $get) => $get('type') === 'pay_as_you_go')
                            ->helperText('هزینه ساخت هر اکانت VPN برای پلن نامحدود'),
                        Forms\Components\TextInput::make('account_limit')
                            ->label('حداکثر تعداد اکانت')
                            ->numeric()
                            ->visible(fn (Forms\Get $get) => $get('type') === 'quota')
                            ->required(fn (Forms\Get $get) => $get('type') === 'quota')
                            ->helperText('حداکثر تعداد اکانت‌های مجاز برای این پلن'),
                        Forms\Components\TextInput::make('discount_percent')
                            ->label('درصد تخفیف')
                            ->numeric()
                            ->suffix('%')
                            ->default(0)
                            ->helperText('درصد تخفیف برای نمایندگان این پلن'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('فعال')
                            ->default(true)
                            ->helperText('وضعیت فعال بودن پلن'),
                        Forms\Components\Textarea::make('description')
                            ->label('توضیحات')
                            ->columnSpanFull()
                            ->placeholder('توضیحات کامل درباره این پلن و مزایای آن...'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('نام پلن')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('نوع پلن')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'quota' => 'پلن محدود',
                        'pay_as_you_go' => 'پلن نامحدود',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'quota' => 'warning',
                        'pay_as_you_go' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('price')
                    ->label('هزینه اشتراک')
                    ->money('IRR', divideBy: 1)
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_per_account')
                    ->label('هزینه هر اکانت')
                    ->money('IRR', divideBy: 1)
                    ->sortable(),
                Tables\Columns\TextColumn::make('account_limit')
                    ->label('حداکثر اکانت')
                    ->sortable(),
                Tables\Columns\TextColumn::make('discount_percent')
                    ->label('تخفیف')
                    ->suffix('%'),
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
                Tables\Filters\SelectFilter::make('type')
                    ->label('نوع پلن')
                    ->options([
                        'quota' => 'پلن محدود',
                        'pay_as_you_go' => 'پلن نامحدود',
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
            ->emptyStateHeading('هیچ پلنی یافت نشد')
            ->emptyStateDescription('پلن‌های نمایندگی جدید اضافه کنید تا نمایندگان بتوانند انتخاب کنند');
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
            'index' => Pages\ListResellerPlans::route('/'),
            'create' => Pages\CreateResellerPlan::route('/create'),
            'edit' => Pages\EditResellerPlan::route('/{record}/edit'),
        ];
    }
}