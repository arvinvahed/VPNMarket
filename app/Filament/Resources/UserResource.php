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

                // اکشن ایجاد اکانت تست
                Action::make('create_trial')
                    ->label('ایجاد اکانت تست')
                    ->icon('heroicon-o-sparkles')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('ایجاد اکانت تست برای کاربر')
                    ->modalDescription('آیا مطمئن هستید که می‌خواهید یک اکانت تست برای این کاربر ایجاد کنید؟ این عمل از محدودیت کاربر کسر خواهد کرد.')
                    ->action(function (User $record) {
                        $settings = Setting::all()->pluck('value', 'key');

                        if (($settings->get('trial_enabled') ?? '0') !== '1') {
                            Notification::make()->title('خطا')->body('قابلیت اکانت تست در تنظیمات غیرفعال است.')->warning()->send();
                            return;
                        }

                        $limit = (int) $settings->get('trial_limit_per_user', 1);
                        if ($record->trial_accounts_taken >= $limit) {
                            Notification::make()->title('خطا')->body('این کاربر قبلاً به حداکثر تعداد مجاز اکانت تست خود رسیده است.')->warning()->send();
                            return;
                        }

                        if (!$record->telegram_chat_id) {
                            Notification::make()->title('توجه')->body('این کاربر به ربات متصل نیست و پیام دریافت نخواهد کرد، اما اکانت ساخته می‌شود.')->warning()->send();
                        }

                        try {
                            $volumeMB = (int) $settings->get('trial_volume_mb', 500);
                            $durationHours = (int) $settings->get('trial_duration_hours', 24);

                            $uniqueUsername = "trial-{$record->id}-" . ($record->trial_accounts_taken + 1);
                            $expiresAt = now()->addHours($durationHours);
                            $dataLimitBytes = $volumeMB * 1024 * 1024;

                            $marzbanService = new \App\Services\MarzbanService($settings->get('marzban_host'), $settings->get('marzban_sudo_username'), $settings->get('marzban_sudo_password'), $settings->get('marzban_node_hostname'));

                            $response = $marzbanService->createUser([
                                'username' => $uniqueUsername,
                                'expire' => $expiresAt->timestamp,
                                'data_limit' => $dataLimitBytes,
                            ]);

                            if ($response && !empty($response['subscription_url'])) {
                                $record->increment('trial_accounts_taken');
                                $configLink = $response['subscription_url'];

                                if ($record->telegram_chat_id) {
                                    $botController = new WebhookController();
                                    $message = "🎁 یک اکانت تست توسط ادمین برای شما فعال شد!\n\n";
                                    $message .= "📦 حجم: *{$volumeMB} مگابایت*\n";
                                    $message .= "⏳ اعتبار: *{$durationHours} ساعت*\n\n";
                                    $message .= "🔗 لینک اتصال:\n`{$configLink}`";

                                    \Telegram\Bot\Laravel\Facades\Telegram::setAccessToken($settings->get('telegram_bot_token'));
                                    \Telegram\Bot\Laravel\Facades\Telegram::sendMessage([
                                        'chat_id' => $record->telegram_chat_id,
                                        'text' => $botController->escape($message),
                                        'parse_mode' => 'MarkdownV2'
                                    ]);
                                }

                                Notification::make()->title('موفقیت')->body('اکانت تست با موفقیت برای کاربر ساخته شد.')->success()->send();

                            } else {
                                throw new \Exception('خطا در ساخت کاربر تست در پنل مرزبان.');
                            }

                        } catch (\Exception $e) {
                            Log::error('Admin Trial Creation Failed: ' . $e->getMessage());
                            Notification::make()->title('خطا')->body('ساخت اکانت تست با خطا مواجه شد: ' . $e->getMessage())->danger()->send();
                        }
                    }),
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
