<?php

namespace Modules\Reseller\Filament\Resources;

use Modules\Reseller\Filament\Resources\ResellerRequestResource\Pages;
use Modules\Reseller\Models\ResellerRequest;
use Modules\Reseller\Models\Reseller;
use Modules\Reseller\Models\ResellerWallet;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Agent;
use Modules\TelegramBot\Http\Controllers\WebhookController;

class ResellerRequestResource extends Resource
{
    protected static ?string $model = ResellerRequest::class;

    protected static ?string $navigationGroup = 'مدیریت نمایندگی';

    protected static ?string $navigationLabel = 'درخواست‌های نمایندگی';

    protected static ?string $pluralLabel = 'درخواست‌های نمایندگی';

    protected static ?string $label = 'درخواست نمایندگی';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('اطلاعات درخواست')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->label('کاربر')
                            ->disabled(),
                        Forms\Components\Select::make('plan_id')
                            ->relationship('plan', 'name')
                            ->label('پلن درخواستی')
                            ->disabled(),
                        Forms\Components\Select::make('status')
                            ->label('وضعیت')
                            ->options([
                                'pending' => 'در انتظار بررسی',
                                'approved' => 'تأیید شده',
                                'rejected' => 'رد شده',
                            ])
                            ->required()
                            ->helperText('وضعیت بررسی درخواست'),
                        Forms\Components\TextInput::make('payment_amount')
                            ->label('مبلغ پرداخت شده')
                            ->disabled()
                            ->prefix('تومان'),
                        Forms\Components\FileUpload::make('payment_receipt_path')
                            ->label('فیش پرداخت')
                            ->image()
                            ->disk('public')
                            ->directory('receipts')
                            ->visibility('public')
                            ->downloadable()
                            ->openable()
                            ->helperText('تصویر فیش پرداخت کاربر'),
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('دلیل رد شدن')
                            ->visible(fn (Forms\Get $get) => $get('status') === 'rejected')
                            ->placeholder('دلیل رد شدن درخواست را بنویسید...'),
                        Forms\Components\Textarea::make('description')
                            ->label('توضیحات کاربر')
                            ->disabled()
                            ->placeholder('توضیحات اضافی توسط کاربر'),
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
                    ->label('پلن درخواستی')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved', 
                        'danger' => 'rejected',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'در انتظار بررسی',
                        'approved' => 'تأیید شده',
                        'rejected' => 'رد شده',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('payment_amount')
                    ->label('مبلغ پرداخت')
                    ->money('IRR', divideBy: 1)
                    ->sortable(),
                Tables\Columns\ImageColumn::make('payment_receipt_path')
                    ->label('فیش پرداخت')
                    ->circular()
                    ->size(50),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ درخواست')
                    ->date('Y/m/d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options([
                        'pending' => 'در انتظار بررسی',
                        'approved' => 'تأیید شده',
                        'rejected' => 'رد شده',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('تأیید درخواست')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('تأیید درخواست نمایندگی')
                    ->modalDescription('آیا از تأیید این درخواست اطمینان دارید؟')
                    ->modalSubmitActionLabel('بله، تأیید شود')
                    ->modalCancelActionLabel('خیر')
                    ->visible(fn (ResellerRequest $record) => $record->status === 'pending')
                    ->action(function (ResellerRequest $record) {
                        DB::transaction(function () use ($record) {
                            $record->update(['status' => 'approved']);

                            $reseller = Reseller::create([
                                'user_id' => $record->user_id,
                                'plan_id' => $record->plan_id,
                                'status' => 'active',
                                'max_accounts' => $record->plan->account_limit ?? 0,
                            ]);
                            
                            $wallet = ResellerWallet::create([
                                'reseller_id' => $reseller->id,
                                'balance' => 0,
                            ]);
                            
                            if ($record->plan->type === 'pay_as_you_go' && $record->payment_amount > 0) {
                                $wallet->increment('balance', $record->payment_amount);
                                $wallet->transactions()->create([
                                    'type' => 'deposit',
                                    'amount' => $record->payment_amount,
                                    'description' => 'شارژ اولیه کیف پول',
                                ]);
                            }

                            $agent = Agent::where('user_id', $record->user_id)->first();
                            if ($agent) {
                                $agentData = [
                                    'status' => 'approved',
                                    'approved_at' => now(),
                                ];

                                if (isset($wallet)) {
                                    $agentData['agent_balance'] = $wallet->balance;
                                }

                                $agent->update($agentData);
                            }
                        });

                        Notification::make()
                            ->title('درخواست با موفقیت تأیید شد')
                            ->success()
                            ->body('کاربر به لیست نمایندگان اضافه شد و کیف پول او ایجاد شد.')
                            ->send();

                        $user = $record->user;
                        if ($user && $user->telegram_chat_id) {
                            try {
                                $controller = app(WebhookController::class);
                                $controller->sendResellerRequestApprovedMessage($user);
                            } catch (\Throwable $e) {
                                Log::error('Failed to send reseller request approval Telegram message', [
                                    'request_id' => $record->id,
                                    'user_id' => $user->id ?? null,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('رد درخواست')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('رد درخواست نمایندگی')
                    ->modalDescription('لطفاً دلیل رد درخواست را وارد کنید و سپس تایید نمایید.')
                    ->modalSubmitActionLabel('بله، رد شود')
                    ->modalCancelActionLabel('خیر')
                    ->visible(fn (ResellerRequest $record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('دلیل رد درخواست')
                            ->required()
                            ->rows(3)
                            ->helperText('این دلیل برای کاربر در تلگرام ارسال می‌شود.'),
                    ])
                    ->action(function (ResellerRequest $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'rejection_reason' => $data['rejection_reason'],
                        ]);

                        $agent = Agent::where('user_id', $record->user_id)->first();
                        if ($agent) {
                            $agent->update([
                                'status' => 'rejected',
                                'rejection_reason' => $record->rejection_reason,
                            ]);
                        }

                        Notification::make()
                            ->title('درخواست رد شد')
                            ->danger()
                            ->body('درخواست نمایندگی این کاربر رد شد.')
                            ->send();

                        $user = $record->user;
                        if ($user && $user->telegram_chat_id) {
                            try {
                                $controller = app(WebhookController::class);
                                $controller->sendResellerRequestRejectedMessage($user, $record->rejection_reason);
                            } catch (\Throwable $e) {
                                Log::error('Failed to send reseller request rejection Telegram message', [
                                    'request_id' => $record->id,
                                    'user_id' => $user->id ?? null,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }),
                Tables\Actions\ViewAction::make()
                    ->label('مشاهده جزئیات'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('حذف انتخاب شده‌ها'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('هیچ درخواستی یافت نشد')
            ->emptyStateDescription('درخواست‌های نمایندگی کاربران در این بخش نمایش داده می‌شوند');
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
            'index' => Pages\ListResellerRequests::route('/'),
            'view' => Pages\ViewResellerRequest::route('/{record}'),
        ];
    }
}
