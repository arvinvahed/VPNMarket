<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AgentResource\Pages;
use App\Models\Agent;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Modules\TelegramBot\Http\Controllers\WebhookController;

class AgentResource extends Resource
{
    protected static ?string $model = Agent::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'مدیریت نمایندگی';
    protected static ?string $navigationLabel = 'نمایندگان';
    protected static ?string $modelLabel = 'نماینده';
    protected static ?string $pluralModelLabel = 'نمایندگان';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('اطلاعات کاربر')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->required()
                            ->searchable()
                            ->label('کاربر'),
                    ]),

                Forms\Components\Section::make('وضعیت نمایندگی')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => '⏳ در انتظار بررسی',
                                'approved' => '✅ تایید شده',
                                'rejected' => '❌ رد شده',
                                'suspended' => '🚫 تعلیق شده',
                            ])
                            ->required()
                            ->label('وضعیت')
                            ->live(),

                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('دلیل رد / توضیحات تعلیق')
                            ->visible(fn (Forms\Get $get) => in_array($get('status'), ['rejected', 'suspended'])),
                    ]),

                Forms\Components\Section::make('اطلاعات تماس')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('شماره تماس')
                            ->required(),
                        Forms\Components\TextInput::make('telegram_id')
                            ->label('آیدی تلگرام'),
                        Forms\Components\Textarea::make('address')
                            ->label('آدرس'),
                    ]),

                Forms\Components\Section::make('تنظیمات مالی')
                    ->schema([
                        Forms\Components\TextInput::make('max_accounts')
                            ->numeric()
                            ->default(16)
                            ->label('حداکثر اکانت قابل ساخت'),
                        Forms\Components\TextInput::make('server_cost_per_account')
                            ->numeric()
                            ->default(30000)
                            ->label('هزینه هر اکانت (تومان)'),
                        Forms\Components\TextInput::make('agent_balance')
                            ->numeric()
                            ->default(0)
                            ->label('موجودی کیف پول نماینده'),
                    ]),

                Forms\Components\Section::make('اطلاعات پرداخت')
                    ->schema([
                        Forms\Components\TextInput::make('payment_amount')
                            ->numeric()
                            ->label('مبلغ واریز شده'),
                        Forms\Components\FileUpload::make('payment_receipt_path')
                            ->image()
                            ->directory('agent_receipts')
                            ->label('رسید پرداخت'),
                    ]),

                Forms\Components\Section::make('تاریخ‌ها')
                    ->schema([
                        Forms\Components\DateTimePicker::make('approved_at')
                            ->label('زمان تایید'),
                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('زمان ثبت')
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('نام کاربر')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('شماره تماس')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('وضعیت')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'secondary' => 'suspended',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => '⏳ در انتظار',
                        'approved' => '✅ تایید شده',
                        'rejected' => '❌ رد شده',
                        'suspended' => '🚫 تعلیق',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('agent_balance')
                    ->label('موجودی')
                    ->money('IRR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_accounts_count')
                    ->label('اکانت‌های ساخته'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ ثبت')
                    ->dateTime('Y/m/d')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'در انتظار',
                        'approved' => 'تایید شده',
                        'rejected' => 'رد شده',
                        'suspended' => 'تعلیق شده',
                    ])
                    ->label('وضعیت'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('✅ تایید')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Agent $record) => $record->status === 'pending')
                    ->action(function (Agent $record) {
                        $record->update([
                            'status' => 'approved',
                            'approved_at' => now(),
                        ]);

                        $user = $record->user;
                        if ($user && $user->telegram_chat_id) {
                            try {
                                $controller = app(WebhookController::class);
                                $message = "🎉 *درخواست نمایندگی شما تایید شد*\n\n";
                                $message .= "اکنون می‌توانید از طریق منوی نمایندگی، سرور و اکانت برای مشتریان خود بسازید.\n\n";
                                $message .= "برای شروع، در ربات روی دکمه «📱 پنل نمایندگی» کلیک کنید.";
                                $controller->sendSingleMessageToUser((string) $user->telegram_chat_id, $message);
                            } catch (\Throwable $e) {
                                Log::error('Failed to send agent approval Telegram message', [
                                    'agent_id' => $record->id,
                                    'user_id' => $user->id ?? null,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('❌ رد')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('دلیل رد')
                            ->required(),
                    ])
                    ->visible(fn (Agent $record) => $record->status === 'pending')
                    ->action(function (Agent $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'rejection_reason' => $data['reason'],
                        ]);

                        $user = $record->user;
                        if ($user && $user->telegram_chat_id) {
                            try {
                                $controller = app(WebhookController::class);
                                $reason = $data['reason'] ?? 'مشخص نشده';
                                $message = "❌ *درخواست نمایندگی شما تایید نشد*\n\n";
                                $message .= "دلیل: {$reason}\n\n";
                                $message .= "اگر سوالی دارید، می‌توانید یک تیکت پشتیبانی ثبت کنید.";

                                $controller->sendSingleMessageToUser((string) $user->telegram_chat_id, $message);

                                $supportMessage = "برای ثبت تیکت پشتیبانی، در ربات روی گزینه «💬 پشتیبانی» بزنید و سپس «📝 ایجاد تیکت جدید» را انتخاب کنید.";
                                $controller->sendSingleMessageToUser((string) $user->telegram_chat_id, $supportMessage);
                            } catch (\Throwable $e) {
                                Log::error('Failed to send agent rejection Telegram message', [
                                    'agent_id' => $record->id,
                                    'user_id' => $user->id ?? null,
                                    'error' => $e->getMessage(),
                                ]);
                            }
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
            // RelationManagers\ServerRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgents::route('/'),
            'create' => Pages\CreateAgent::route('/create'),
            'edit' => Pages\EditAgent::route('/{record}/edit'),
        ];
    }
}
