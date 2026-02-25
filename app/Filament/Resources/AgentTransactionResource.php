<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AgentTransactionResource\Pages;
use App\Models\AgentTransaction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Modules\TelegramBot\Http\Controllers\WebhookController;

class AgentTransactionResource extends Resource
{
    protected static ?string $model = AgentTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'مدیریت نمایندگی';

    protected static ?string $navigationLabel = 'تراکنش‌های نماینده';

    protected static ?string $modelLabel = 'تراکنش نماینده';

    protected static ?string $pluralModelLabel = 'تراکنش‌های نماینده';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('شناسه')
                    ->sortable(),
                Tables\Columns\TextColumn::make('agent.user.name')
                    ->label('نماینده')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('مبلغ')
                    ->money('IRR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('receipt_path')
                    ->label('رسید')
                    ->formatStateUsing(function (?string $state, AgentTransaction $record): string {
                        if (!$record->receipt_path) {
                            return '-';
                        }

                        $url = '/storage/' . ltrim($record->receipt_path, '/');

                        return '<a href="'.$url.'" target="_blank">مشاهده رسید</a>';
                    })
                    ->html(),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('نوع')
                    ->colors([
                        'primary' => 'deposit',
                        'success' => 'server_purchase',
                        'warning' => 'account_sale',
                        'danger' => 'withdraw',
                        'gray' => 'manual',
                        'info' => 'renewal',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'deposit' => 'واریز',
                        'server_purchase' => 'خرید سرور',
                        'account_sale' => 'فروش اکانت',
                        'withdraw' => 'برداشت',
                        'manual' => 'دستی',
                        'renewal' => 'تمدید اکانت',
                        default => $state,
                    }),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('وضعیت')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'completed',
                        'danger' => 'failed',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'در انتظار',
                        'completed' => 'تکمیل شده',
                        'failed' => 'ناموفق',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاریخ ثبت')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options([
                        'pending' => 'در انتظار',
                        'completed' => 'تکمیل شده',
                        'failed' => 'ناموفق',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->label('نوع تراکنش')
                    ->options([
                        'deposit' => 'واریز',
                        'server_purchase' => 'خرید سرور',
                        'account_sale' => 'فروش اکانت',
                        'withdraw' => 'برداشت',
                        'manual' => 'دستی',
                        'renewal' => 'تمدید اکانت',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('تایید و شارژ کیف پول')
                    ->color('success')
                    ->visible(fn (AgentTransaction $record) => $record->status === 'pending' && $record->type === 'deposit')
                    ->requiresConfirmation()
                    ->action(function (AgentTransaction $record) {
                        $agent = $record->agent;
                        $user = $record->user;

                        if ($agent) {
                            $agent->increment('agent_balance', $record->amount);
                        }

                        $record->update([
                            'status' => 'completed',
                        ]);

                        if ($user && $user->telegram_chat_id) {
                            try {
                                $controller = app(WebhookController::class);
                                $amount = number_format((int) $record->amount);
                                $balance = $agent ? number_format((int) $agent->agent_balance) : null;

                                $message = "✅ *شارژ کیف پول نمایندگی تایید شد*\n\n";
                                $message .= "مبلغ: {$amount} تومان\n";
                                if ($balance !== null) {
                                    $message .= "موجودی جدید: {$balance} تومان\n";
                                }

                                $controller->sendSingleMessageToUser((string) $user->telegram_chat_id, $message);
                            } catch (\Throwable $e) {
                                Log::error('Failed to send agent deposit approved Telegram message', [
                                    'transaction_id' => $record->id,
                                    'agent_id' => $agent->id ?? null,
                                    'user_id' => $user->id ?? null,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('رد تراکنش')
                    ->color('danger')
                    ->visible(fn (AgentTransaction $record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('دلیل رد تراکنش')
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->action(function (AgentTransaction $record, array $data) {
                        $user = $record->user;

                        $record->update([
                            'status' => 'failed',
                        ]);

                        if ($user && $user->telegram_chat_id) {
                            try {
                                $controller = app(WebhookController::class);
                                $reason = $data['reason'] ?? 'مشخص نشده';
                                $amount = number_format((int) $record->amount);

                                $message = "❌ *درخواست شارژ کیف پول نمایندگی شما رد شد*\n\n";
                                $message .= "مبلغ: {$amount} تومان\n";
                                $message .= "دلیل: {$reason}";

                                $controller->sendSingleMessageToUser((string) $user->telegram_chat_id, $message);
                            } catch (\Throwable $e) {
                                Log::error('Failed to send agent deposit rejected Telegram message', [
                                    'transaction_id' => $record->id,
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgentTransactions::route('/'),
        ];
    }
}
