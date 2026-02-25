<?php

namespace Modules\Reseller\Filament\Resources;

use Modules\Reseller\Filament\Resources\ResellerResource\Pages;
use Modules\Reseller\Models\Reseller;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Modules\Reseller\Models\ResellerTransaction;
use Telegram\Bot\Laravel\Facades\Telegram;

class ResellerResource extends Resource
{
    protected static ?string $model = Reseller::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'مدیریت نمایندگی';

    protected static ?string $navigationLabel = 'نمایندگان';

    protected static ?string $pluralLabel = 'نمایندگان';

    protected static ?string $label = 'نماینده';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('اطلاعات نماینده')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->label('کاربر')
                            ->required()
                            ->searchable()
                            ->helperText('کاربری که به عنوان نماینده انتخاب می‌شود'),
                        Forms\Components\Select::make('plan_id')
                            ->relationship('plan', 'name')
                            ->label('پلن نمایندگی')
                            ->required()
                            ->helperText('پلن نمایندگی کاربر'),
                        Forms\Components\Select::make('status')
                            ->label('وضعیت')
                            ->options([
                                'active' => 'فعال',
                                'inactive' => 'غیرفعال',
                                'suspended' => 'تعلیق شده',
                            ])
                            ->required()
                            ->helperText('وضعیت فعلی نماینده'),
                        Forms\Components\TextInput::make('telegram_username')
                            ->label('نام کاربری تلگرام')
                            ->maxLength(255)
                            ->placeholder('مثال: @username')
                            ->helperText('نام کاربری تلگرام نماینده'),
                        Forms\Components\TextInput::make('phone')
                            ->label('شماره تماس')
                            ->tel()
                            ->maxLength(255)
                            ->placeholder('09xxxxxxxxx')
                            ->helperText('شماره تماس نماینده'),
                        Forms\Components\TextInput::make('max_accounts')
                            ->label('حداکثر تعداد اکانت')
                            ->numeric()
                            ->default(0)
                            ->helperText('0 یعنی نامحدود (پلن پرداخت به ازای هر اکانت)'),
                        Forms\Components\TextInput::make('discount_percent')
                            ->label('درصد تخفیف')
                            ->numeric()
                            ->suffix('%')
                            ->default(0)
                            ->helperText('درصد تخفیف برای نماینده'),
                        Forms\Components\Textarea::make('description')
                            ->label('توضیحات')
                            ->columnSpanFull()
                            ->placeholder('توضیحات اضافی درباره این نماینده...'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('نام کاربر')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('plan.name')
                    ->label('پلن نمایندگی')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'danger' => 'suspended',
                        'gray' => 'inactive',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'فعال',
                        'suspended' => 'تعلیق شده',
                        'inactive' => 'غیرفعال',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('telegram_username')
                    ->label('تلگرام')
                    ->icon('heroicon-o-chat-bubble-oval-left-ellipsis')
                    ->formatStateUsing(fn (?string $state): string => $state ? "@{$state}" : '-'),
                Tables\Columns\TextColumn::make('wallet.balance')
                    ->label('موجودی کیف پول')
                    ->money('IRR', divideBy: 1)
                    ->sortable()
                    ->default(0),
                Tables\Columns\TextColumn::make('accounts_count')
                    ->label('تعداد اکانت‌ها')
                    ->counts('accounts')
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_accounts')
                    ->label('حداکثر اکانت')
                    ->formatStateUsing(fn (?int $state): string => $state === 0 ? 'نامحدود' : number_format($state)),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ عضویت')
                    ->date('Y/m/d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options([
                        'active' => 'فعال',
                        'inactive' => 'غیرفعال',
                        'suspended' => 'تعلیق شده',
                    ]),
                Tables\Filters\SelectFilter::make('plan')
                    ->label('پلن نمایندگی')
                    ->relationship('plan', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('ویرایش'),
                Tables\Actions\ViewAction::make()
                    ->label('مشاهده'),
                Action::make('charge_wallet')
                    ->label('شارژ کیف پول')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('مبلغ (تومان)')
                            ->required()
                            ->numeric()
                            ->suffix('تومان')
                            ->helperText('برای کسر موجودی، مبلغ منفی وارد کنید.'),
                        Forms\Components\Textarea::make('description')
                            ->label('توضیحات')
                            ->placeholder('مثال: پاداش فروش، اصلاح موجودی و...'),
                        Forms\Components\Toggle::make('notify_user')
                            ->label('ارسال پیامک/تلگرام به نماینده')
                            ->default(true),
                    ])
                    ->action(function (Reseller $record, array $data): void {
                        $amount = (float) $data['amount'];
                        $description = $data['description'] ?? 'شارژ دستی توسط مدیریت';
                        
                        // Ensure wallet exists
                        $wallet = $record->wallet()->firstOrCreate([
                            'reseller_id' => $record->id
                        ], [
                            'balance' => 0
                        ]);

                        // Determine transaction type
                        $type = $amount >= 0 ? 'deposit' : 'withdrawal';

                        // Update balance
                        if ($amount > 0) {
                            $wallet->increment('balance', $amount);
                        } else {
                            $wallet->decrement('balance', abs($amount));
                        }

                        // Create transaction
                        ResellerTransaction::create([
                            'wallet_id' => $wallet->id,
                            'type' => $type,
                            'amount' => $amount,
                            'description' => $description,
                        ]);

                        Notification::make()
                            ->title('موجودی کیف پول با موفقیت بروزرسانی شد')
                            ->success()
                            ->send();

                        // Send Telegram Notification
                        if ($data['notify_user'] && $record->user && $record->user->telegram_chat_id) {
                            try {
                                $settings = \App\Models\Setting::all()->pluck('value', 'key');
                                $botToken = $settings->get('telegram_bot_token');
                                
                                if ($botToken) {
                                    Telegram::setAccessToken($botToken);
                                    
                                    $emoji = $amount >= 0 ? '💰' : '💸';
                                    $actionText = $amount >= 0 ? 'افزایش' : 'کاهش';
                                    $formattedAmount = number_format(abs($amount));
                                    $newBalance = number_format($wallet->refresh()->balance);
                                    
                                    $message = "{$emoji} *تغییر موجودی کیف پول*\n\n";
                                    $message .= "همکار گرامی، موجودی کیف پول شما مبلغ {$formattedAmount} تومان {$actionText} یافت.\n\n";
                                    $message .= "📝 *توضیحات:* {$description}\n";
                                    $message .= "💵 *موجودی فعلی:* {$newBalance} تومان\n";
                                    
                                    Telegram::sendMessage([
                                        'chat_id' => $record->user->telegram_chat_id,
                                        'text' => $message,
                                        'parse_mode' => 'Markdown',
                                    ]);
                                } else {
                                    Notification::make()
                                        ->title('توکن ربات تلگرام تنظیم نشده است')
                                        ->warning()
                                        ->send();
                                }
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('خطا در ارسال پیام تلگرام')
                                    ->body($e->getMessage())
                                    ->warning()
                                    ->send();
                            }
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('حذف انتخاب شده‌ها'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('هیچ نماینده‌ای یافت نشد')
            ->emptyStateDescription('نمایندگان فعال در این بخش نمایش داده می‌شوند');
    }

    public static function getRelations(): array
    {
        return [
            // می‌توان مدیر کیف پول و تراکنش‌ها را اینجا اضافه کرد
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResellers::route('/'),
            'create' => Pages\CreateReseller::route('/create'),
            'edit' => Pages\EditReseller::route('/{record}/edit'),
            'view' => Pages\ViewReseller::route('/{record}'),
        ];
    }
}