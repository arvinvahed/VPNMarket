<?php

namespace App\Filament\Resources;

use App\Events\OrderPaid;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Inbound;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Transaction;
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
use Telegram\Bot\Keyboard\Keyboard;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Support\Str;

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

                            // --- 1. شارژ کیف پول ---
                            if (!$plan) {
                                $order->update(['status' => 'paid']);
                                $user->increment('balance', $order->amount);
                                Transaction::create(['user_id' => $user->id, 'order_id' => $order->id, 'amount' => $order->amount, 'type' => 'deposit', 'status' => 'completed', 'description' => "شارژ کیف پول (تایید دستی فیش)"]);
                                $user->notifications()->create(['type' => 'wallet_charged_approved', 'title' => 'کیف پول شارژ شد', 'message' => "مبلغ " . number_format($order->amount) . " تومان اضافه شد.", 'link' => route('dashboard', ['tab' => 'order_history'])]);
                                Notification::make()->title('کیف پول شارژ شد.')->success()->send();
                                if ($user->telegram_chat_id) {
                                    try {
                                        $msg = "✅ کیف پول شما شارژ شد.\nمبلغ: " . number_format($order->amount) . " تومان\nموجودی: " . number_format($user->fresh()->balance) . " تومان";
                                        Telegram::setAccessToken($settings->get('telegram_bot_token'));
                                        Telegram::sendMessage(['chat_id' => $user->telegram_chat_id, 'text' => $msg, 'parse_mode' => 'Markdown']);
                                    } catch (\Exception $e) {}
                                }
                                return;
                            }

                            // --- 2. تمدید یا خرید سرویس ---
                            Log::info("Approving order {$order->id} for user {$user->id}");

                            $isRenewal = (bool)$order->renews_order_id;
                            $originalOrder = $isRenewal ? Order::find($order->renews_order_id) : null;

                            if ($isRenewal && !$originalOrder) {
                                Notification::make()->title('خطا')->body('سفارش اصلی یافت نشد.')->danger()->send(); return;
                            }

                            $uniqueUsername = $order->panel_username ?? "user-{$user->id}-order-" . ($isRenewal ? $originalOrder->id : $order->id);
                            $uniqueUsername = trim($uniqueUsername);

                            $newExpiresAt = $isRenewal ? (new \DateTime($originalOrder->expires_at))->modify("+{$plan->duration_days} days") : now()->addDays($plan->duration_days);

                            // --- تشخیص سرور (بخش اصلاح شده) ---
                            $isMultiLocationEnabled = filter_var($settings->get('enable_multilocation', false), FILTER_VALIDATE_BOOLEAN);
                            $panelType = $settings->get('panel_type');
                            $targetServer = null;

                            // مقادیر پیش‌فرض
                            $xuiHost = $settings->get('xui_host'); $xuiUser = $settings->get('xui_user'); $xuiPass = $settings->get('xui_pass'); $inboundId = (int)$settings->get('xui_default_inbound_id');

                            // 🔥 اصلاح مهم: پیدا کردن سرور اصلی در حالت تمدید
                            $targetServerId = $order->server_id;
                            if (!$targetServerId && $isRenewal && $originalOrder) {
                                $targetServerId = $originalOrder->server_id;
                            }

                            if ($isMultiLocationEnabled && class_exists('Modules\MultiServer\Models\Server') && $targetServerId) {
                                $targetServer = \Modules\MultiServer\Models\Server::find($targetServerId);
                                if ($targetServer && $targetServer->is_active) {
                                    $panelType = 'xui'; $xuiHost = $targetServer->full_host; $xuiUser = $targetServer->username; $xuiPass = $targetServer->password; $inboundId = $targetServer->inbound_id;

                                    // اگر تمدید است، سرور آیدی را روی سفارش جدید هم ست کن تا برای دفعه بعد گم نشود
                                    if ($isRenewal && !$order->server_id) {
                                        $order->server_id = $targetServerId;
                                        // ذخیره در انتهای تراکنش انجام می‌شود
                                    }
                                }
                            }

                            $success = false;
                            $finalConfig = '';
                            $finalUuid = null;
                            $finalSubId = null;

                            try {
                                if ($panelType === 'marzban') {
                                    $marzbanService = new MarzbanService(
                                        (string) $settings->get('marzban_host'),
                                        (string) $settings->get('marzban_sudo_username'),
                                        (string) $settings->get('marzban_sudo_password'),
                                        (string) $settings->get('marzban_node_hostname')
                                    );
                                    $userData = ['expire' => $newExpiresAt->getTimestamp(), 'data_limit' => $plan->volume_gb * 1073741824];
                                    if ($isRenewal) {
                                        $response = $marzbanService->updateUser($uniqueUsername, $userData);
                                        $marzbanService->resetUserTraffic($uniqueUsername);
                                    } else {
                                        $response = $marzbanService->createUser(array_merge($userData, ['username' => $uniqueUsername]));
                                    }
                                    if ($response && (isset($response['subscription_url']) || isset($response['username']))) {
                                        $finalConfig = $marzbanService->generateSubscriptionLink($response);
                                        $success = true;
                                    } else throw new \Exception('خطا در مرزبان');

                                } elseif ($panelType === 'xui') {
                                    $xui = new XUIService($xuiHost, $xuiUser, $xuiPass);
                                    if (!$xui->login()) throw new \Exception('خطا در لاگین X-UI');

                                    // اینباند
                                    $inboundData = null;
                                    if ($targetServer) {
                                        $inbounds = $xui->getInbounds();
                                        foreach ($inbounds as $i) if ($i['id'] == $inboundId) { $inboundData = $i; break; }
                                    } else {
                                        $im = Inbound::whereJsonContains('inbound_data->id', (int)$inboundId)->first();
                                        if ($im) $inboundData = is_string($im->inbound_data) ? json_decode($im->inbound_data, true) : $im->inbound_data;
                                    }
                                    if (!$inboundData) throw new \Exception('اینباند یافت نشد.');

                                    // نوع لینک (الان که سرور درست پیدا شده، این هم درست کار می‌کند)
                                    $linkType = $targetServer ? ($targetServer->link_type ?? 'single') : $settings->get('xui_link_type', 'single');
                                    $clientData = ['email' => $uniqueUsername, 'total' => $plan->volume_gb * 1073741824, 'expiryTime' => $newExpiresAt->getTimestamp() * 1000];

                                    // عملیات پنل
                                    if ($isRenewal) {
                                        $clients = $xui->getClients($inboundData['id']);
                                        $client = collect($clients)->first(function ($c) use ($uniqueUsername) {
                                            return strtolower(trim($c['email'])) === strtolower(trim($uniqueUsername));
                                        });

                                        if ($client) {
                                            $clientData['id'] = $client['id'];
                                            $clientData['subId'] = $client['subId'] ?? Str::random(16);
                                            $upRes = $xui->updateClient($inboundData['id'], $client['id'], $clientData);
                                            if ($upRes && ($upRes['success'] ?? false)) {
                                                $xui->resetClientTraffic($inboundData['id'], $uniqueUsername);
                                                $finalUuid = $client['id'];
                                                $finalSubId = $clientData['subId'];
                                            } else throw new \Exception('خطا در آپدیت کاربر');
                                        } else {
                                            throw new \Exception("کاربر {$uniqueUsername} یافت نشد.");
                                        }
                                    } else {
                                        // 🔥 خرید جدید - اول چک کن اگه وجود داشت آپدیت کن
                                        $clients = $xui->getClients($inboundData['id']);
                                        $existingClient = collect($clients)->first(function ($c) use ($uniqueUsername) {
                                            return strtolower(trim($c['email'])) === strtolower(trim($uniqueUsername));
                                        });

                                        if ($existingClient) {
                                            // کاربر وجود داره، آپدیتش کن
                                            $clientData['id'] = $existingClient['id'];
                                            $clientData['subId'] = $existingClient['subId'] ?? Str::random(16);
                                            $upRes = $xui->updateClient($inboundData['id'], $existingClient['id'], $clientData);
                                            if ($upRes && ($upRes['success'] ?? false)) {
                                                $xui->resetClientTraffic($inboundData['id'], $uniqueUsername);
                                                $finalUuid = $existingClient['id'];
                                                $finalSubId = $clientData['subId'];
                                                Log::info('Existing client updated: ' . $uniqueUsername);
                                            } else throw new \Exception('خطا در آپدیت کاربر موجود');
                                        } else {
                                            // کاربر جدیده، بسازش
                                            if ($linkType === 'subscription') $clientData['subId'] = Str::random(16);
                                            $addRes = $xui->addClient($inboundData['id'], $clientData);
                                            if ($addRes && ($addRes['success'] ?? false)) {
                                                $finalUuid = $addRes['generated_uuid'] ?? json_decode($addRes['obj']['settings'], true)['clients'][0]['id'];
                                                $finalSubId = $addRes['generated_subId'] ?? $clientData['subId'];
                                                if ($targetServer) $targetServer->increment('current_users');
                                            } else throw new \Exception('خطا در ساخت کاربر: ' . ($addRes['msg'] ?? 'Unknown error'));
                                        }
                                    }
                                    // ساخت لینک (با تنظیمات سرور درست)
                                    $stream = json_decode($inboundData['streamSettings'] ?? '{}', true);
                                    $proto = $inboundData['protocol'] ?? 'vless';
                                    $port = $inboundData['port'] ?? 443;

                                    switch ($linkType) {
                                        case 'subscription':
                                            $subUrl = $targetServer ? ($targetServer->subscription_domain ?? parse_url($xuiHost, PHP_URL_HOST)) : $settings->get('xui_subscription_url_base');
                                            $subPort = $targetServer ? ($targetServer->subscription_port ?? 2053) : '';
                                            $prot = ($targetServer && !$targetServer->is_https) ? 'http' : 'https';
                                            $base = rtrim($subUrl, '/');
                                            if($subPort && !Str::contains($base, ":$subPort")) $base .= ":$subPort";
                                            if(!Str::startsWith($base, 'http')) $base = "$prot://$base";
                                            $finalConfig = "$base" . ($targetServer->subscription_path ?? '/sub/') . $finalSubId;
                                            break;

                                        case 'tunnel':
                                            $tunAddr = $targetServer->tunnel_address;
                                            $tunPort = $targetServer->tunnel_port ?? 443;
                                            // اینجا چون سرور درست انتخاب شده، این تنظیمات درست اعمال میشن
                                            $tls = filter_var($targetServer->tunnel_is_https, FILTER_VALIDATE_BOOLEAN);

                                            $p = ['type' => $stream['network'] ?? 'tcp'];
                                            if ($tls) {
                                                $p['security'] = 'tls';
                                                $p['sni'] = $tunAddr;
                                            } else {
                                                $p['security'] = 'none';
                                                if($proto === 'vless') $p['encryption'] = 'none';
                                            }

                                            if (($p['type'] ?? '') === 'ws') {
                                                $p['path'] = $stream['wsSettings']['path'] ?? '/';
                                                $p['host'] = $stream['wsSettings']['headers']['Host'] ?? $tunAddr;
                                            }


                                            $remark = ($targetServer->location->flag ?? "🏳️") . "-" . $uniqueUsername;
                                            $qs = http_build_query($p);
                                            $finalConfig = "vless://{$finalUuid}@{$tunAddr}:{$tunPort}?{$qs}#" . rawurlencode($remark);
                                            break;

                                        default:
                                            if (!$finalUuid) throw new \Exception("UUID پیدا نشد");
                                            $p = ['type' => $stream['network'] ?? 'tcp', 'security' => $stream['security'] ?? 'none'];
                                            if ($p['security'] === 'tls') $p['sni'] = parse_url($xuiHost, PHP_URL_HOST);
                                            $qs = http_build_query(array_filter($p));
                                            $finalConfig = "vless://{$finalUuid}@" . parse_url($xuiHost, PHP_URL_HOST) . ":{$inboundId}?{$qs}#" . rawurlencode($plan->name);
                                    }
                                    $success = true;
                                }
                            } catch (\Exception $e) {
                                Log::error("Provisioning Exception for order {$order->id}: " . $e->getMessage() . "\nStack Trace:\n" . $e->getTraceAsString());
                                Notification::make()->title('خطا')->body($e->getMessage())->danger()->send();
                                return;
                            }

                                    // --- پایان ---
                                    if ($success) {
                                        Log::info("Service provisioned successfully for order {$order->id}");
                                        // ادامه لاجیک...
                                    } else {
                                        Log::error("Provisioning failed for order {$order->id}");
                                        Notification::make()->title('خطا در ایجاد سرویس')->body('عملیات ناموفق بود.')->danger()->send();
                                        return;
                                    }
                            if ($success) {
                                $dataToUpdate = [
                                    'config_details' => $finalConfig,
                                    'expires_at' => $newExpiresAt,
                                    'panel_username' => $uniqueUsername,
                                    'panel_client_id' => $finalUuid,
                                    'panel_sub_id' => $finalSubId
                                ];

                                if($isRenewal) {
                                    $originalOrder->update($dataToUpdate);
                                    $user->update(['show_renewal_notification' => true]);
                                    $user->notifications()->create(['type'=>'renew','title'=>'تمدید شد','message'=>"تمدید {$plan->name}",'link'=>route('dashboard')]);
                                } else {
                                    $order->update($dataToUpdate);
                                    $user->notifications()->create(['type'=>'activate','title'=>'فعال شد','message'=>"خرید {$plan->name}",'link'=>route('dashboard')]);
                                }

                                $order->update(['status' => 'paid']);
                                $description = ($isRenewal ? "تمدید سرویس" : "خرید سرویس") . " {$plan->name}";
                                Transaction::create(['user_id'=>$user->id, 'order_id'=>$order->id, 'amount'=>$plan->price, 'type'=>'purchase', 'status'=>'completed', 'description'=>$description]);

                                if (class_exists(OrderPaid::class)) {
                                    OrderPaid::dispatch($order);
                                }

                                Notification::make()->title('عملیات موفقیت‌آمیز بود.')->success()->send();

                                if ($user->telegram_chat_id) {
                                    try {
                                        Telegram::setAccessToken($settings->get('telegram_bot_token'));

                                        // انتخاب سفارش صحیح برای نمایش اطلاعات
                                        $displayOrder = $isRenewal ? $originalOrder : $order;

                                        $displayOrder->load(['server.location', 'plan']);

                                        $server = $displayOrder->server;
                                        $serverName = $server?->name ?? 'سرور اصلی';
                                        $locationFlag = $server?->location?->flag ?? '🏳️';
                                        $locationName = $server?->location?->name ?? 'نامشخص';

                                        $planModel = $displayOrder->plan;


                                        // تابع escape کمکی برای متن‌های معمولی
                                        $escape = function($text) {
                                            $chars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
                                            return str_replace($chars, array_map(fn($c) => '\\' . $c, $chars), $text);
                                        };

                                        // تابع escape برای داخل کد بلاک (فقط ` و \)
                                        $escapeCode = function($text) {
                                            return str_replace(['\\', '`'], ['\\\\', '\`'], $text);
                                        };

                                        // ساخت پیام کامل
                                        $msgTitle = $isRenewal ? "تمدید موفق\\!" : "خرید موفق\\!";
                                        $msgText = "✅ *" . $msgTitle . "*\n\n";
                                        $msgText .= "📦 *پلن:* `" . $escapeCode($planModel->name) . "`\n";

                                        // استفاده از escape برای متن‌های معمولی
                                        if (!$isRenewal) {
                                            $msgText .= "🌍 *موقعیت:* {$locationFlag} " . $escape($locationName) . "\n";
                                            $msgText .= "🖥 *سرور:* " . $escape($serverName) . "\n";
                                        }

                                        $msgText .= "💾 *حجم:* " . $escape($planModel->volume_gb . ' گیگابایت') . "\n";
                                        $msgText .= "📅 *مدت:* " . $escape($planModel->duration_days . ' روز') . "\n";
                                        $msgText .= "⏳ *انقضا:* `" . $displayOrder->expires_at->format('Y/m/d H:i') . "`\n";
                                        $msgText .= "👤 *یوزرنیم:* `" . $escapeCode($displayOrder->panel_username) . "`\n\n";
                                        $msgText .= "🔗 *لینک کانفیگ شما:*\n";
                                        $msgText .= "`" . $escapeCode($finalConfig) . "`\n\n";
                                        $msgText .= $escape("⚠️ روی لینک بالا کلیک کنید تا کپی شود");

                                        // ساخت کیبورد
                                        $keyboard = Keyboard::make()->inline()
                                            ->row([
                                                Keyboard::inlineButton(['text' => '📋 کپی لینک کانفیگ', 'callback_data' => "copy_link_{$displayOrder->id}"]),
                                                Keyboard::inlineButton(['text' => '📱 QR Code', 'callback_data' => "qrcode_order_{$displayOrder->id}"])
                                            ])
                                            ->row([
                                                Keyboard::inlineButton(['text' => '🛠 سرویس‌های من', 'callback_data' => '/my_services']),
                                                Keyboard::inlineButton(['text' => '🏠 منوی اصلی', 'callback_data' => '/start'])
                                            ]);

                                        Telegram::sendMessage([
                                            'chat_id' => $user->telegram_chat_id,
                                            'text' => $msgText,
                                            'parse_mode' => 'MarkdownV2',
                                            'reply_markup' => $keyboard
                                        ]);

                                    } catch (\Exception $e) {
                                        Log::error('Error sending TG success message (Admin Approve): ' . $e->getMessage(), [
                                            'order_id' => $order->id,
                                            'trace' => $e->getTraceAsString()
                                        ]);

                                        // ✅ Fallback با دکمه‌های کامل
                                        try {
                                            Telegram::setAccessToken($settings->get('telegram_bot_token'));

                                            $displayOrderId = $isRenewal ? $originalOrder->id : $order->id;

                                            $keyboard = Keyboard::make()->inline()
                                                ->row([
                                                    Keyboard::inlineButton(['text' => '📋 کپی لینک کانفیگ', 'callback_data' => "copy_link_{$displayOrderId}"]),
                                                    Keyboard::inlineButton(['text' => '📱 QR Code', 'callback_data' => "qrcode_order_{$displayOrderId}"])
                                                ])
                                                ->row([
                                                    Keyboard::inlineButton(['text' => '🛠 سرویس‌های من', 'callback_data' => '/my_services']),
                                                    Keyboard::inlineButton(['text' => '🏠 منوی اصلی', 'callback_data' => '/start'])
                                                ]);

                                            $simpleMsg = ($isRenewal ? "✅ سرویس تمدید شد." : "✅ سرویس فعال شد.") . "\n\n`{$finalConfig}`";

                                            Telegram::sendMessage([
                                                'chat_id' => $user->telegram_chat_id,
                                                'text' => $simpleMsg,
                                                'parse_mode' => 'Markdown',
                                                'reply_markup' => $keyboard
                                            ]);
                                        } catch (\Exception $e2) {
                                            Log::error('Fallback message also failed: ' . $e2->getMessage());
                                        }
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
    public static function getPages(): array { return ['index' => Pages\ListOrders::route('/'), 'create' => Pages\CreateOrder::route('/create'), 'edit' => Pages\EditOrder::route('/{record}/edit')]; }
}
