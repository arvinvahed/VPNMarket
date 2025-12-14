<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DiscountCodeResource\Pages;
use App\Models\DiscountCode;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class DiscountCodeResource extends Resource
{
    protected static ?string $model = DiscountCode::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'کدهای تخفیف';
    protected static ?string $pluralLabel = 'کدهای تخفیف';
    protected static ?string $navigationGroup = 'فروش و تخفیف';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('اطلاعات اصلی')->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('کد تخفیف')
                        ->unique(ignoreRecord: true)
                        ->required()
                        ->maxLength(20)
                        ->helperText('مثلاً: YALDA1404')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('name')
                        ->label('نام کمپین')
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\Textarea::make('description')
                        ->label('توضیحات')
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(2),

                Forms\Components\Section::make('نوع و مقدار تخفیف')->schema([
                    Forms\Components\Select::make('type')
                        ->label('نوع تخفیف')
                        ->options([
                            'percent' => 'درصدی (%)',
                            'fixed' => 'مبلغ ثابت (تومان)',
                        ])
                        ->reactive()
                        ->required()
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('value')
                        ->label(fn($get) => $get('type') === 'percent' ? 'درصد تخفیف' : 'مبلغ تخفیف')
                        ->numeric()
                        ->required()
                        ->suffix(fn($get) => $get('type') === 'percent' ? '%' : 'تومان')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('max_discount_amount')
                        ->label('حداکثر تخفیف (تومان)')
                        ->numeric()
                        ->nullable()
                        ->helperText('فقط برای تخفیف درصدی')
                        ->visible(fn($get) => $get('type') === 'percent')
                        ->columnSpan(2),
                ])->columns(2),

                Forms\Components\Section::make('محدودیت‌ها')->schema([
                    Forms\Components\TextInput::make('usage_limit')
                        ->label('حداکثر تعداد استفاده کل')
                        ->numeric()
                        ->nullable()
                        ->helperText('خالی = بدون محدودیت')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('usage_limit_per_user')
                        ->label('حداکثر استفاده هر کاربر')
                        ->numeric()
                        ->default(1)
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('min_order_amount')
                        ->label('حداقل مبلغ سفارش')
                        ->numeric()
                        ->suffix('تومان')
                        ->nullable()
                        ->columnSpan(1),
                ])->columns(3),

                Forms\Components\Section::make('اعمال روی')->schema([
                    Forms\Components\Toggle::make('applies_to_wallet')
                        ->label('روی شارژ کیف پول هم کار کند؟')
                        ->default(false)
                        ->columnSpan(1),

                    Forms\Components\Toggle::make('applies_to_renewal')
                        ->label('روی تمدید سرویس هم کار کند؟')
                        ->default(true)
                        ->columnSpan(1),



                    Forms\Components\Select::make('plan_ids')
                        ->label('فقط روی این پلن‌ها')
                        ->multiple()
                        ->options(\App\Models\Plan::where('is_active', true)->pluck('name', 'id'))
                        ->preload()
                        ->columnSpan(2),
                ])->columns(2),

                Forms\Components\Section::make('زمان‌بندی')->schema([
                    Forms\Components\DateTimePicker::make('starts_at')
                        ->label('شروع از')
                        ->columnSpan(1),

                    Forms\Components\DateTimePicker::make('expires_at')
                        ->label('انقضا در')
                        ->columnSpan(1),

                    Forms\Components\Toggle::make('is_active')
                        ->label('فعال')
                        ->default(true)
                        ->columnSpan(2),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('کد')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('name')
                    ->label('نام کمپین')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('نوع')
                    ->formatStateUsing(fn($state) => $state === 'percent' ? 'درصدی' : 'مبلغی')
                    ->colors([
                        'success' => 'percent',
                        'info' => 'fixed',
                    ]),

                Tables\Columns\TextColumn::make('value')
                    ->label('مقدار')
                    ->formatStateUsing(fn($record) =>
                    $record->type === 'percent' ?
                        "{$record->value}%" :
                        number_format($record->value) . ' تومان'
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('used_count')
                    ->label('استفاده شده')
                    ->suffix(fn($record) => $record->usage_limit ? " / {$record->usage_limit}" : '')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('وضعیت')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('انقضا')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ ایجاد')
                    ->dateTime('Y/m/d H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('وضعیت فعال'),

                Tables\Filters\SelectFilter::make('type')
                    ->label('نوع تخفیف')
                    ->options([
                        'percent' => 'درصدی',
                        'fixed' => 'مبلغی',
                    ]),

                Tables\Filters\Filter::make('expires_at')
                    ->form([
                        Forms\Components\DatePicker::make('expires_from')->label('انقضا از'),
                        Forms\Components\DatePicker::make('expires_to')->label('انقضا تا'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['expires_from'], fn($q, $date) => $q->whereDate('expires_at', '>=', $date))
                            ->when($data['expires_to'], fn($q, $date) => $q->whereDate('expires_at', '<=', $date));
                    }),

                Tables\Filters\Filter::make('has_remaining')
                    ->label('کدهای قابل استفاده')
                    ->query(fn($query) =>
                    $query->where(function($q) {
                        $q->whereNull('usage_limit')
                            ->orWhereRaw('usage_count < usage_limit');
                    })
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('toggleStatus')
                    ->label(fn($record) => $record->is_active ? '⏸️ غیرفعال' : '✅ فعال')
                    ->icon(fn($record) => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn($record) => $record->is_active ? 'warning' : 'success')
                    ->action(function ($record) {
                        $record->update(['is_active' => !$record->is_active]);

                        Notification::make()
                            ->title($record->is_active ? 'کد فعال شد' : 'کد غیرفعال شد')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->button(),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),

                Tables\Actions\BulkAction::make('deactivate')
                    ->label('غیرفعال کردن انتخاب‌شده‌ها')
                    ->icon('heroicon-o-no-symbol')
                    ->color('warning')
                    ->action(fn(Collection $records) => $records->each->update(['is_active' => false]))
                    ->requiresConfirmation(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDiscountCodes::route('/'),
            'create' => Pages\CreateDiscountCode::route('/create'),
            'edit' => Pages\EditDiscountCode::route('/{record}/edit'),
        ];
    }
}
