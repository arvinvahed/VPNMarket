<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\Setting;
use Modules\TelegramBot\Http\Controllers\WebhookController;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'مدیریت کاربران';

    protected static ?string $navigationLabel = 'کاربران سایت';
    protected static ?string $pluralModelLabel = 'کاربران سایت';
    protected static ?string $modelLabel = 'کاربر';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('نام')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('ایمیل')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->label('رمز عبور جدید')
                    ->password()
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $context): bool => $context === 'create')
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_admin')
                    ->label('کاربر ادمین است؟'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('نام')->searchable(),
                Tables\Columns\TextColumn::make('email')->label('ایمیل')->searchable(),
                Tables\Columns\IconColumn::make('is_admin')->label('ادمین')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('تاریخ ثبت‌نام')->dateTime('Y-m-d')->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),





                // اکشن ارسال پیام به تلگرام
                Action::make('send_telegram_message')
                    ->label('پیام تلگرام')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('info')
                    ->modalHeading(fn (User $record) => 'ارسال پیام به ' . $record->name)
                    ->visible(fn (User $record): bool => (bool)$record->telegram_chat_id)
                    ->form([
                        Textarea::make('message')
                            ->label('متن پیام')
                            ->required()
                            ->rows(5)
                            ->maxLength(4096),
                    ])
                    ->action(function (User $record, array $data) {
                        $chatId = $record->telegram_chat_id;
                        if (!$chatId) {
                            Notification::make()->title('خطا')->body('کاربر Chat ID تلگرام ندارد.')->danger()->send();
                            return;
                        }
                        $webhookController = new WebhookController();
                        $success = $webhookController->sendSingleMessageToUser($chatId, $data['message']);
                        if ($success) {
                            Notification::make()->title('موفقیت')->body('پیام با موفقیت به تلگرام کاربر ارسال شد.')->success()->send();
                        } else {
                            Notification::make()->title('خطا در ارسال')->body('ارسال پیام به تلگرام ناموفق بود. (چک کردن لاگ‌ها)')->danger()->send();
                        }
                    }),



                Tables\Actions\Action::make('adjust_wallet')
                    ->label('تنظیم کیف پول')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('warning')
                    ->modalHeading(fn (User $record) => "تنظیم کیف پول: {$record->name}")
                    ->modalDescription('موجودی کیف پول کاربر را افزایش یا کاهش دهید')
                    ->modalSubmitActionLabel('اعمال تغییر')
                    ->modalWidth('lg')
                    ->form([
                        Forms\Components\Placeholder::make('current_balance')
                            ->label('موجودی فعلی')
                            ->content(fn (User $record) => '💰 ' . number_format($record->balance ?? 0) . ' تومان')
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('amount')
                                    ->label('مبلغ تغییر')
                                    ->numeric()
                                    ->required()
                                    ->prefix('﷼')
                                    ->suffix('تومان')
                                    ->helperText('مثال: +100000 یا -50000')
                                    ->hint('عدد منفی برای کاهش')
                                    ->rules(['required', 'numeric', 'not_in:0'])
                                    ->live(onBlur: true),

                                Forms\Components\Placeholder::make('new_balance_preview')
                                    ->label('موجودی جدید')
                                    ->content(function (callable $get, User $record) {
                                        $amount = (int) $get('amount');
                                        if ($amount === 0 || empty($get('amount'))) return '—';
                                        $newBalance = ($record->balance ?? 0) + $amount;
                                        $emoji = $amount > 0 ? '⬆️' : '⬇️';
                                        return "{$emoji} " . number_format($newBalance) . ' تومان';
                                    }),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('دلیل تغییر')
                            ->required()
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('این توضیحات در تراکنش ثبت و به اطلاع کاربر می‌رسد')
                            ->placeholder('مثال: هدیه ویژه، جبران خسارت، یا تغییر دستی...'),
                    ])
                    ->action(function (User $record, array $data) {
                        $amount = (int) $data['amount'];
                        $description = $data['description'];

                        DB::transaction(function () use ($record, $amount, $description) {
                            $oldBalance = $record->balance;
                            $record->increment('balance', $amount);

                            Transaction::create([
                                'user_id' => $record->id,
                                'order_id' => null,
                                'amount' => $amount,
                                'type' => $amount > 0 ? 'deposit' : 'withdraw',
                                'status' => 'completed',
                                'description' => "تنظیم دستی توسط ادمین: {$description}",
                                'payment_method' => 'manual_admin',
                            ]);

                            if ($record->telegram_chat_id) {
                                $webhookController = new WebhookController();
                                $action = $amount >= 0 ? 'افزوده شد' : 'کسر شد';
                                $emoji = $amount > 0 ? '✅' : '⚠️';

                                $message = "{$emoji} *تغییر موجودی کیف پول*\n\n";
                                $message .= "▫️ مبلغ: *" . number_format(abs($amount)) . "* تومان {$action}\n";
                                $message .= "▫️ موجودی جدید: *" . number_format($record->balance) . "* تومان\n\n";
                                $message .= "💬 توضیحات: _{$description}_\n\n";
                                $message .= "👤 توسط: *مدیریت*";

                                $webhookController->sendSingleMessageToUser($record->telegram_chat_id, $message);
                            }
                        });

                        Notification::make()
                            ->title('موفقیت')
                            ->body("کیف پول کاربر {$record->name} با موفقیت به‌روزرسانی شد ✅")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-exclamation-triangle')
                    ->modalIconColor('warning')
                    ->modalSubmitActionLabel('بله، اعمال شود'),


            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),

        ];
    }
}
