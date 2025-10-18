<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromoCodeResource\Pages;
use App\Filament\Resources\PromoCodeResource\RelationManagers;
use App\Models\PromoCode;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PromoCodeResource extends Resource
{
    protected static ?string $model = PromoCode::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'بازاریابی';

    protected static ?string $navigationLabel = 'کدهای تخفیف';
    protected static ?string $pluralModelLabel = 'کدهای تخفیف';
    protected static ?string $modelLabel = 'کد تخفیف';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('اطلاعات اصلی')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('کد تخفیف')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->helperText('کد به صورت خودکار به حروف بزرگ تبدیل می‌شود')
                            ->columnSpan(1),
                        Forms\Components\Textarea::make('description')
                            ->label('توضیحات')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('تنظیمات تخفیف')
                    ->schema([
                        Forms\Components\Select::make('discount_type')
                            ->label('نوع تخفیف')
                            ->required()
                            ->options([
                                'percent' => 'درصدی',
                                'fixed' => 'مبلغ ثابت',
                            ])
                            ->default('percent')
                            ->reactive()
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('discount_value')
                            ->label('مقدار تخفیف')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->helperText(fn ($get) => $get('discount_type') === 'percent' ? 'عدد بین 0 تا 100' : 'مبلغ به تومان')
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('currency')
                            ->label('واحد پول')
                            ->default('تومان')
                            ->visible(fn ($get) => $get('discount_type') === 'fixed')
                            ->columnSpan(1),
                    ])->columns(2),

                Forms\Components\Section::make('محدودیت‌های استفاده')
                    ->schema([
                        Forms\Components\TextInput::make('max_uses')
                            ->label('حداکثر تعداد استفاده کل')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('خالی بگذارید برای استفاده نامحدود')
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('max_uses_per_user')
                            ->label('حداکثر تعداد استفاده هر کاربر')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('خالی بگذارید برای استفاده نامحدود')
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('uses_count')
                            ->label('تعداد استفاده شده')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan(1),
                    ])->columns(3),

                Forms\Components\Section::make('تاریخ اعتبار')
                    ->schema([
                        Forms\Components\DateTimePicker::make('start_at')
                            ->label('تاریخ شروع')
                            ->helperText('خالی بگذارید برای شروع فوری')
                            ->columnSpan(1),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('تاریخ انقضا')
                            ->helperText('خالی بگذارید برای عدم انقضا')
                            ->columnSpan(1),
                    ])->columns(2),

                Forms\Components\Section::make('قابلیت اعمال')
                    ->schema([
                        Forms\Components\Select::make('applies_to')
                            ->label('قابل اعمال به')
                            ->required()
                            ->options([
                                'all' => 'همه پلن‌ها',
                                'plan' => 'پلن خاص',
                            ])
                            ->default('all')
                            ->reactive()
                            ->columnSpan(1),
                        Forms\Components\Select::make('plan_id')
                            ->label('پلن')
                            ->options(\App\Models\Plan::pluck('name', 'id'))
                            ->visible(fn ($get) => $get('applies_to') === 'plan')
                            ->required(fn ($get) => $get('applies_to') === 'plan')
                            ->columnSpan(1),
                    ])->columns(2),

                Forms\Components\Section::make('وضعیت')
                    ->schema([
                        Forms\Components\Toggle::make('active')
                            ->label('فعال')
                            ->default(true)
                            ->columnSpan(1),
                    ]),
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
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('description')
                    ->label('توضیحات')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('discount_display')
                    ->label('تخفیف')
                    ->getStateUsing(function (PromoCode $record) {
                        if ($record->discount_type === 'percent') {
                            return $record->discount_value . '%';
                        }
                        return number_format($record->discount_value) . ' تومان';
                    }),
                Tables\Columns\TextColumn::make('applies_to_display')
                    ->label('قابل اعمال به')
                    ->getStateUsing(function (PromoCode $record) {
                        if ($record->applies_to === 'all') {
                            return 'همه';
                        }
                        if ($record->applies_to === 'plan' && $record->plan) {
                            return $record->plan->name;
                        }
                        return '-';
                    }),
                Tables\Columns\TextColumn::make('usage_display')
                    ->label('استفاده')
                    ->getStateUsing(function (PromoCode $record) {
                        $used = $record->uses_count;
                        $max = $record->max_uses ?? '∞';
                        return "{$used}/{$max}";
                    })
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('انقضا')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->placeholder('نامحدود'),
                Tables\Columns\IconColumn::make('active')
                    ->label('فعال')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ ایجاد')
                    ->dateTime('Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->label('فعال')
                    ->placeholder('همه')
                    ->trueLabel('فعال')
                    ->falseLabel('غیرفعال'),
                Tables\Filters\Filter::make('expires_soon')
                    ->label('منقضی شده یا به زودی منقضی')
                    ->query(fn (Builder $query) => $query->where(function ($q) {
                        $q->whereNotNull('expires_at')
                            ->where('expires_at', '<=', now()->addDays(7));
                    })),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (PromoCode $record) => $record->active ? 'غیرفعال کردن' : 'فعال کردن')
                    ->icon('heroicon-o-power')
                    ->color(fn (PromoCode $record) => $record->active ? 'danger' : 'success')
                    ->action(fn (PromoCode $record) => $record->update(['active' => !$record->active])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListPromoCodes::route('/'),
            'create' => Pages\CreatePromoCode::route('/create'),
            'edit' => Pages\EditPromoCode::route('/{record}/edit'),
        ];
    }
}
