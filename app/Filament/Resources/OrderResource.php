<?php

namespace App\Filament\Resources;

use App\Events\OrderPaid;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Inbound;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\Notification as UserNotification;
use App\Services\MarzbanService;
use App\Services\XUIService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\Laravel\Facades\Telegram;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'سفارشات';
    protected static ?string $modelLabel = 'سفارش';
    protected static ?string $pluralModelLabel = 'سفارشات';
    protected static ?string $navigationGroup = 'مدیریت سفارشات';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')->relationship('user', 'name')->label('کاربر')->disabled(),
                Forms\Components\Select::make('plan_id')->relationship('plan', 'name')->label('پلن')->disabled(),
                Forms\Components\Select::make('status')->label('وضعیت سفارش')->options(['pending' => 'در انتظار پرداخت', 'paid' => 'پرداخت شده', 'expired' => 'منقضی شده'])->required(),
                Forms\Components\Textarea::make('config_details')->label('اطلاعات کانفیگ سرویس')->rows(10),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('card_payment_receipt')->label('رسید')->disk('public')->toggleable()->size(60)->circular()->url(fn (Order $record): ?string => $record->card_payment_receipt ? Storage::disk('public')->url($record->card_payment_receipt) : null)->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('user.name')->label('کاربر')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('plan.name')->label('پلن / آیتم')->default(fn (Order $record): string => $record->plan_id ? $record->plan->name : "شارژ کیف پول")->description(function (Order $record): string {
                    if ($record->renews_order_id) return " (تمدید سفارش #" . $record->renews_order_id . ")";
                    if (!$record->plan_id) return number_format($record->amount) . ' تومان';
                    return '';
                })->color(fn(Order $record) => $record->renews_order_id ? 'primary' : 'gray'),
                IconColumn::make('source')->label('منبع')->icon(fn (?string $state): string => match ($state) { 'web' => 'heroicon-o-globe-alt', 'telegram' => 'heroicon-o-paper-airplane', default => 'heroicon-o-question-mark-circle' })->color(fn (?string $state): string => match ($state) { 'web' => 'primary', 'telegram' => 'info', default => 'gray' }),
                Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge()->color(fn (string $state): string => match ($state) { 'pending' => 'warning', 'paid' => 'success', 'expired' => 'danger', default => 'gray' })->formatStateUsing(fn (string $state): string => match ($state) { 'pending' => 'در انتظار پرداخت', 'paid' => 'پرداخت شده', 'expired' => 'منقضی شده', default => $state }),
                Tables\Columns\TextColumn::make('created_at')->label('تاریخ سفارش')->dateTime('Y-m-d')->sortable(),
                Tables\Columns\TextColumn::make('expires_at')->label('تاریخ انقضا')->dateTime('Y-m-d')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('وضعیت')->options(['pending' => 'در انتظار پرداخت', 'paid' => 'پرداخت شده', 'expired' => 'منقضی شده']),
                Tables\Filters\SelectFilter::make('source')->label('منبع')->options(['web' => 'وب‌سایت', 'telegram' => 'تلگرام']),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('approve')->label('تایید و اجرا')->icon('heroicon-o-check-circle')->color('success')->requiresConfirmation()->modalHeading('تایید پرداخت سفارش')->modalDescription('آیا از تایید این پرداخت اطمینان دارید؟')->visible(fn (Order $order): bool => $order->status === 'pending')
                    ->action(function (Order $order) {
                        DB::transaction(function () use ($order) {
                            $settings = Setting::all()->pluck('value', 'key');
                            $user = $order->user;
                            $plan = $order->plan;

                            if (!$plan) {
                                $order->update(['status' => 'paid']);
                                $user->increment('balance', $order->amount);
                                Transaction::create(['user_id' => $user->id, 'order_id' => $order->id, 'amount' => $order->amount, 'type' => 'deposit', 'status' => 'completed', 'description' => "شارژ کیف پول (تایید دستی فیش)"]);
                                $user->notifications()->create([
                                    'type' => 'wallet_charged_approved',
                                    'title' => 'کیف پول شما شارژ شد!',
                                    'message' => "مبلغ " . number_format($order->amount) . " تومان با موفقیت به کیف پول شما اضافه شد.",
                                    'link' => route('dashboard', ['tab' => 'order_history']),
                                ]);

                                Notification::make()->title('کیف پول کاربر با موفقیت شارژ شد.')->success()->send();

                                if ($user->telegram_chat_id) {
                                    try {
                                        $telegramMessage = "✅ کیف پول شما به مبلغ *" . number_format($order->amount) . " تومان* با موفقیت شارژ شد.\n\n";
                                        $telegramMessage .= "موجودی جدید شما: *" . number_format($user->fresh()->balance) . " تومان*";

                                        Telegram::setAccessToken($settings->get('telegram_bot_token'));
                                        Telegram::sendMessage([
                                            'chat_id' => $user->telegram_chat_id,
                                            'text' => $telegramMessage,
                                            'parse_mode' => 'Markdown'
                                        ]);
                                    } catch (\Exception $e) {
                                        Log::error('Failed to send wallet charge notification via Telegram: ' . $e->getMessage());
                                    }
                                }

                                return;
                            }

                            $panelType = $settings->get('panel_type');
                            $success = false;
                            $finalConfig = '';
                            $isRenewal = (bool)$order->renews_order_id;

                            $originalOrder = $isRenewal ? Order::find($order->renews_order_id) : null;
                            if ($isRenewal && !$originalOrder) {
                                Notification::make()->title('خطا')->body('سفارش اصلی جهت تمدید یافت نشد.')->danger()->send();
                                return;
                            }
                            $uniqueUsername = "user-{$user->id}-order-" . ($isRenewal ? $originalOrder->id : $order->id);
                             $newExpiresAt = $isRenewal
                                ? (new \DateTime($originalOrder->expires_at))->modify("+{$plan->duration_days} days")
                                : now()->addDays($plan->duration_days);

                            if ($panelType === 'marzban') {
                                $marzbanService = new MarzbanService(
                                    (string) $settings->get('marzban_host'),
                                    (string) $settings->get('marzban_sudo_username'),
                                    (string) $settings->get('marzban_sudo_password'),
                                    (string) $settings->get('marzban_node_hostname')
                                );

                                $userData = ['expire' => $newExpiresAt->getTimestamp(), 'data_limit' => $plan->volume_gb * 1073741824];
                                $response = $isRenewal ? $marzbanService->updateUser($uniqueUsername, $userData) : $marzbanService->createUser(array_merge($userData, ['username' => $uniqueUsername]));

                                if ($response && (isset($response['subscription_url']) || isset($response['username']))) {
                                    $finalConfig = $marzbanService->generateSubscriptionLink($response);
                                    $success = true;
                                } else {
                                    Notification::make()->title('خطا در ارتباط با مرزبان')->body($response['detail'] ?? 'پاسخ نامعتبر.')->danger()->send();
                                    return;
                                }
                            } elseif ($panelType === 'xui') {
                                $xuiService = new XUIService($settings->get('xui_host'), $settings->get('xui_user'), $settings->get('xui_pass'));
                                $defaultInboundId = $settings->get('xui_default_inbound_id');
                                $inbound = Inbound::where('inbound_data->id', $defaultInboundId)->first();

                                if (!$inbound || !$inbound->inbound_data) {
                                    Notification::make()->title('خطا')->body('اطلاعات اینباند پیش‌فرض برای X-UI یافت نشد.')->danger()->send();
                                    return;
                                }
                                if (!$xuiService->login()) {
                                    Notification::make()->title('خطا')->body('خطا در لاگین به پنل X-UI.')->danger()->send();
                                    return;
                                }

                                $inboundData = is_string($inbound->inbound_data)
                                    ? json_decode($inbound->inbound_data, true)
                                    : $inbound->inbound_data;

                                // استفاده از getTimestamp() به جای timestamp
                                $clientData = ['email' => $uniqueUsername, 'total' => $plan->volume_gb * 1073741824, 'expiryTime' => $newExpiresAt->getTimestamp() * 1000];

                                if ($isRenewal) {
                                    // ----- THIS IS THE FIXED CODE FOR RENEWAL -----
                                    $originalOrder = Order::find($order->renews_order_id);
                                    if (!$originalOrder || !$originalOrder->config_details) {
                                        Notification::make()->title('خطا')->body('اطلاعات سرویس اصلی یافت نشد.')->danger()->send();
                                        return;
                                    }

                                    $linkType = $settings->get('xui_link_type', 'single');
                                    $originalConfig = $originalOrder->config_details;
                                    $clientId = null;
                                    $subId = null;

                                    if ($linkType === 'subscription') {
                                        preg_match('/\/sub\/([a-zA-Z0-9]+)/', $originalConfig, $matches);
                                        $subId = $matches[1] ?? null;

                                        if (!$subId) {
                                            Notification::make()->title('خطا')->body('شناسه اشتراک (subId) در کانفیگ قبلی یافت نشد.')->danger()->send();
                                            return;
                                        }

                                        $clientData['subId'] = $subId;
                                        $clients = $xuiService->getClients($inboundData['id']);

                                        if (!empty($clients)) {
                                            $client = collect($clients)->firstWhere('subId', $subId);
                                            if (!$client) {
                                                $client = collect($clients)->firstWhere('email', $uniqueUsername);
                                            }
                                            $clientId = $client['id'] ?? null;
                                        }

                                        if (!$clientId) {
                                            Log::warning('Client not found, attempting to create new', [
                                                'inbound_id' => $inboundData['id'],
                                                'email' => $uniqueUsername,
                                                'subId' => $subId,
                                            ]);

                                            $addResponse = $xuiService->addClient($inboundData['id'], array_merge($clientData, ['subId' => $subId]));

                                            if ($addResponse && isset($addResponse['success']) && $addResponse['success']) {
                                                $subBaseUrl = rtrim($settings->get('xui_subscription_url_base'), '/');
                                                $newSubId = $addResponse['generated_subId'];
                                                if ($subBaseUrl && $newSubId) {
                                                    $finalConfig = $subBaseUrl . '/sub/' . $newSubId;
                                                    $success = true;
                                                } else {
                                                    Notification::make()->title('خطا')->body('خطا در ساخت لینک سابسکریپشن جدید.')->danger()->send();
                                                    return;
                                                }
                                            } else {
                                                $errorMsg = $addResponse['msg'] ?? 'خطای نامشخص';

                                                if (strpos($errorMsg, 'Duplicate email') !== false) {
                                                    Log::critical('CRITICAL: getClients returned empty but client exists!', [
                                                        'inbound_id' => $inboundData['id'],
                                                        'email' => $uniqueUsername,
                                                        'subId' => $subId,
                                                    ]);
                                                    Notification::make()->title('خطای سیستمی')->body('سرویس X-UI به درستی کار نمی‌کند.')->danger()->send();
                                                    return;
                                                }

                                                Notification::make()->title('خطا')->body('خطا در ساخت کلاینت: ' . $errorMsg)->danger()->send();
                                                return;
                                            }
                                        } else {
                                            $clientData['id'] = $clientId;
                                            $response = $xuiService->updateClient($inboundData['id'], $clientId, $clientData);

                                            if ($response && isset($response['success']) && $response['success']) {
                                                $finalConfig = $originalConfig;
                                                $success = true;
                                            } else {
                                                Notification::make()->title('خطا')->body('خطا در بروزرسانی کلاینت: ' . ($response['msg'] ?? 'خطای نامشخص'))->danger()->send();
                                                return;
                                            }
                                        }
                                    } else {
                                        // منطق برای لینک single
                                        preg_match('/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})/i', $originalConfig, $matches);
                                        $clientId = $matches[1] ?? null;

                                        if (!$clientId) {
                                            Notification::make()->title('خطا')->body('UUID در کانفیگ یافت نشد.')->danger()->send();
                                            return;
                                        }

                                        $clientData['id'] = $clientId;
                                        $clients = $xuiService->getClients($inboundData['id']);

                                        if (!empty($clients)) {
                                            $client = collect($clients)->firstWhere('id', $clientId);
                                            if (!$client) {
                                                $client = collect($clients)->firstWhere('email', $uniqueUsername);
                                            }
                                        }

                                        if (empty($clients) || !$client) {
                                            $addResponse = $xuiService->addClient($inboundData['id'], $clientData);

                                            if ($addResponse && isset($addResponse['success']) && $addResponse['success']) {
                                                $uuid = $addResponse['generated_uuid'];
                                                $streamSettings = $inboundData['streamSettings'] ?? [];
                                                if (is_string($streamSettings)) {
                                                    $streamSettings = json_decode($streamSettings, true) ?? [];
                                                }

                                                $parsedUrl = parse_url($settings->get('xui_host'));
                                                $serverIpOrDomain = !empty($inboundData['listen']) ? $inboundData['listen'] : $parsedUrl['host'];
                                                $port = $inboundData['port'];
                                                $remark = $inboundData['remark'];

                                                $paramsArray = [
                                                    'type' => $streamSettings['network'] ?? null,
                                                    'security' => $streamSettings['security'] ?? null,
                                                    'path' => $streamSettings['wsSettings']['path'] ?? ($streamSettings['grpcSettings']['serviceName'] ?? null),
                                                    'sni' => $streamSettings['tlsSettings']['serverName'] ?? null,
                                                    'host' => $streamSettings['wsSettings']['headers']['Host'] ?? null
                                                ];

                                                $params = http_build_query(array_filter($paramsArray));
                                                $fullRemark = $uniqueUsername . '|' . $remark;
                                                $finalConfig = "vless://{$uuid}@{$serverIpOrDomain}:{$port}?{$params}#" . urlencode($fullRemark);
                                                $success = true;
                                            } else {
                                                Notification::make()->title('خطا')->body('خطا در ساخت کلاینت: ' . ($addResponse['msg'] ?? 'خطای نامشخص'))->danger()->send();
                                                return;
                                            }
                                        } else {
                                            $response = $xuiService->updateClient($inboundData['id'], $clientId, $clientData);

                                            if ($response && isset($response['success']) && $response['success']) {
                                                $finalConfig = $originalConfig;
                                                $success = true;
                                            } else {
                                                Notification::make()->title('خطا')->body('خطا در بروزرسانی کلاینت: ' . ($response['msg'] ?? 'خطای نامشخص'))->danger()->send();
                                                return;
                                            }
                                        }
                                    }
                                    // ----- END OF FIXED CODE -----
                                } else {
                                    // سفارش جدید - منطق قبلی برای X-UI
                                    $response = $xuiService->addClient($inboundData['id'], $clientData);

                                    if ($response && isset($response['success']) && $response['success']) {
                                        $linkType = $settings->get('xui_link_type', 'single');
                                        if ($linkType === 'subscription') {
                                            $subId = $response['generated_subId'];
                                            $subBaseUrl = rtrim($settings->get('xui_subscription_url_base'), '/');
                                            if ($subBaseUrl && $subId) {
                                                $finalConfig = $subBaseUrl . '/sub/' . $subId;
                                                $success = true;
                                            }
                                        } else {
                                            $uuid = $response['generated_uuid'];
                                            $streamSettings = json_decode($inboundData['streamSettings'], true);
                                            $parsedUrl = parse_url($settings->get('xui_host'));
                                            $serverIpOrDomain = !empty($inboundData['listen']) ? $inboundData['listen'] : $parsedUrl['host'];
                                            $port = $inboundData['port'];
                                            $remark = $inboundData['remark'];
                                            $paramsArray = ['type' => $streamSettings['network'] ?? null, 'security' => $streamSettings['security'] ?? null, 'path' => $streamSettings['wsSettings']['path'] ?? ($streamSettings['grpcSettings']['serviceName'] ?? null), 'sni' => $streamSettings['tlsSettings']['serverName'] ?? null, 'host' => $streamSettings['wsSettings']['headers']['Host'] ?? null];
                                            $params = http_build_query(array_filter($paramsArray));
                                            $fullRemark = $uniqueUsername . '|' . $remark;
                                            $finalConfig = "vless://{$uuid}@{$serverIpOrDomain}:{$port}?{$params}#" . urlencode($fullRemark);
                                            $success = true;
                                        }
                                    } else {
                                        $errorMsg = $response['msg'] ?? 'پاسخ نامعتبر';
                                        Notification::make()->title('خطا')->body('خطا در ساخت کاربر در پنل سنایی: ' . $errorMsg)->danger()->send();
                                        return;
                                    }
                                }
                            } else {
                                Notification::make()->title('خطا')->body('نوع پنل در تنظیمات مشخص نشده است.')->danger()->send();

                                $user->notifications()->create([
                                    'type' => 'panel_type_error_admin',
                                    'title' => 'خطا در فعال‌سازی سرویس!',
                                    'message' => "نوع پنل در تنظیمات سیستم به درستی مشخص نشده است. لطفاً به پشتیبانی اطلاع دهید.",
                                    'link' => route('dashboard', ['tab' => 'support']),
                                ]);
                                return;
                            }

                            if ($success) {
                                if($isRenewal) {
                                    $originalOrder->update([
                                        'config_details' => $finalConfig,
                                        'expires_at' => $newExpiresAt->format('Y-m-d H:i:s'),
                                        'panel_username' => $uniqueUsername
                                    ]);

                                    $user->update(['show_renewal_notification' => true]);

                                    $user->notifications()->create([
                                        'type' => 'service_renewed_admin',
                                        'title' => 'سرویس شما تمدید شد!',
                                        'message' => "تمدید سرویس {$originalOrder->plan->name} توسط مدیر تایید و فعال شد. لطفاً لینک اشتراک خود را به‌روزرسانی کنید.",
                                        'link' => route('dashboard', ['tab' => 'my_services']),
                                    ]);
                                } else {
                                    $order->update([
                                        'config_details' => $finalConfig,
                                        'expires_at' => $newExpiresAt,
                                        'panel_username' => $uniqueUsername
                                    ]);
                                    $user->notifications()->create([
                                        'type' => 'service_activated_admin',
                                        'title' => 'سرویس شما فعال شد!',
                                        'message' => "خرید سرویس {$plan->name} توسط مدیر تایید و فعال شد.",
                                        'link' => route('dashboard', ['tab' => 'my_services']),
                                    ]);
                                }

                                $order->update(['status' => 'paid']);
                                $description = ($isRenewal ? "تمدید سرویس" : "خرید سرویس") . " {$plan->name}";
                                Transaction::create(['user_id' => $user->id, 'order_id' => $order->id, 'amount' => $plan->price, 'type' => 'purchase', 'status' => 'completed', 'description' => $description]);
                                OrderPaid::dispatch($order);
                                Notification::make()->title('عملیات با موفقیت انجام شد.')->success()->send();

                                if ($user->telegram_chat_id) {
                                    try {
                                        $telegramMessage = $isRenewal
                                            ? "✅ سرویس شما (*{$plan->name}*) با موفقیت تمدید شد.\n\n❗️*نکته مهم:* لینک اشتراک شما تغییر کرده است. لطفاً لینک جدید زیر را کپی و در نرم‌افزار خود آپدیت کنید:\n\n`" . $finalConfig . "`"
                                            : "✅ سرویس شما (*{$plan->name}*) با موفقیت فعال شد.\n\nاطلاعات کانفیگ شما:\n`" . $finalConfig . "`\n\nمی‌توانید لینک بالا را کپی کرده و در نرم‌افزار خود import کنید.";
                                        Telegram::setAccessToken($settings->get('telegram_bot_token'));
                                        Telegram::sendMessage(['chat_id' => $user->telegram_chat_id, 'text' => $telegramMessage, 'parse_mode' => 'Markdown']);
                                    } catch (\Exception $e) {
                                        Log::error('Failed to send Telegram notification: ' . $e->getMessage());
                                    }
                                }
                            }
                        });
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array { return []; }
    public static function getPages(): array {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
