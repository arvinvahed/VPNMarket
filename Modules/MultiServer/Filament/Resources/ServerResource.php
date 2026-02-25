<?php

namespace Modules\MultiServer\Filament\Resources;

use Modules\MultiServer\Filament\Resources\ServerResource\Pages;
use Modules\MultiServer\Models\Server;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Services\XUIService;
use Filament\Notifications\Notification;

class ServerResource extends Resource
{
    protected static ?string $model = Server::class;
    protected static ?string $navigationIcon = 'heroicon-o-server';
    protected static ?string $navigationGroup = 'مولتی سرور';
    protected static ?string $label = 'سرور';
    protected static ?string $pluralLabel = 'سرورها';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('اطلاعات اتصال پنل')
                    ->description('اطلاعات ورود به پنل سنایی/X-UI یا مرزبان سرور مقصد را وارد کنید.')
                    ->schema([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Select::make('location_id')
                                ->relationship('location', 'name')
                                ->label('لوکیشن (کشور)')
                                ->preload()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')->required()->label('نام کشور'),
                                    Forms\Components\TextInput::make('slug')->required()->label('شناسه'),
                                    Forms\Components\TextInput::make('flag')->label('پرچم'),
                                ])
                                ->required(),

                            Forms\Components\Select::make('type')
                                ->label('نوع پنل')
                                ->options([
                                    'xui' => 'X-UI / Sanaei',
                                    'marzban' => 'Marzban',
                                ])
                                ->default('xui')
                                ->required()
                                ->live(),
                        ]),

                        Forms\Components\TextInput::make('name')
                            ->label('نام سرور')
                            ->required()
                            ->placeholder('مثال: Server Germany 1'),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('ip_address')
                                ->label('آدرس IP یا دامنه')
                                ->required()
                                ->placeholder('مثال: sub.domain.com (بدون http/https)'),

                            Forms\Components\TextInput::make('port')
                                ->label('پورت پنل')
                                ->numeric()
                                ->placeholder('پیش‌فرض: 80/443')
                                ->helperText('اگر خالی باشد، پورت پیش‌فرض (۸۰ یا ۴۴۳) استفاده می‌شود.'),
                        ]),

                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('username')
                                ->label('نام کاربری پنل')
                                ->required(),

                            Forms\Components\TextInput::make('password')
                                ->label('رمز عبور پنل')
                                ->password()
                                ->revealable()
                                ->required(),
                        ]),

                        Forms\Components\TextInput::make('path')
                            ->label('URL Path')
                            ->default('/')
                            ->placeholder('/')
                            ->helperText('اگر پنل روی ساب‌فولدر است (مثلاً /panel/) وارد کنید.'),

                        Forms\Components\Toggle::make('is_https')
                            ->label('اتصال امن (SSL/HTTPS)')
                            ->default(false)
                            ->inline(false),

                        Forms\Components\TextInput::make('marzban_node_hostname')
                            ->label('هاست نیم نود (Node Hostname)')
                            ->placeholder('مثال: node1.example.com')
                            ->helperText('فقط در صورت نیاز به ساخت کاربر روی نود خاص (اختیاری)')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'marzban'),

                        // ====================================================
                        // 🚀 انتخاب هوشمند اینباند (روش جدید و تضمینی)
                        // ====================================================
                        Forms\Components\TextInput::make('inbound_id')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'xui')
                            ->label('شناسه اینباند (Inbound ID)')
                            ->required(fn (Forms\Get $get) => $get('type') === 'xui')
                            ->numeric()
                            ->helperText('برای دریافت لیست، دکمه سمت چپ را بزنید.')
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('selectInbound')
                                    ->icon('heroicon-o-list-bullet')
                                    ->label('انتخاب از لیست')
                                    ->color('primary')
                                    ->modalHeading('لیست اینباندهای موجود در سرور')
                                    ->modalSubmitActionLabel('تایید و انتخاب')
                                    ->form(function (Forms\Get $get) {
                                        $rawIp = $get('ip_address');
                                        $cleanIp = str_replace(['http://', 'https://', '/'], '', $rawIp);

                                        $protocol = $get('is_https') ? 'https' : 'http';
                                        $port = $get('port');
                                        $path = $get('path');

                                        $host = "{$protocol}://{$cleanIp}:{$port}{$path}";

                                        $user = $get('username');
                                        $pass = $get('password');

                                        if (!$user || !$pass || !$cleanIp) {
                                            return [
                                                Forms\Components\Placeholder::make('error')
                                                    ->content('❌ لطفاً ابتدا فیلدهای آدرس، پورت، نام کاربری و رمز عبور را پر کنید.')
                                                    ->extraAttributes(['class' => 'text-danger-600'])
                                            ];
                                        }

                                        try {
                                            $xui = new \App\Services\XUIService($host, $user, $pass);
                                            if (!$xui->login()) {
                                                throw new \Exception('اتصال به پنل ناموفق بود. نام کاربری یا رمز عبور اشتباه است.');
                                            }

                                            $inbounds = $xui->getInbounds();
                                            if (empty($inbounds)) {
                                                throw new \Exception('هیچ اینباندی در این سرور یافت نشد.');
                                            }

                                            $options = [];
                                            foreach ($inbounds as $inbound) {
                                                $id = $inbound['id'];
                                                $remark = $inbound['remark'] ?? 'بدون نام';
                                                $protocol = strtoupper($inbound['protocol'] ?? 'UNKNOWN');
                                                $port = $inbound['port'] ?? '?';

                                                $options[$id] = "ID: {$id}  |  {$remark}  |  {$protocol} : {$port}";
                                            }

                                            return [
                                                Forms\Components\Radio::make('selected_inbound')
                                                    ->label('یکی از اینباندها را انتخاب کنید:')
                                                    ->options($options)
                                                    ->required()
                                                    ->columns(1)
                                            ];

                                        } catch (\Exception $e) {
                                            return [
                                                Forms\Components\Placeholder::make('error')
                                                    ->content('خطا در دریافت لیست: ' . $e->getMessage())
                                                    ->extraAttributes(['class' => 'text-danger-600 bg-danger-50 p-3 rounded'])
                                            ];
                                        }
                                    })
                                    ->action(function (array $data, Forms\Set $set) {
                                        if (isset($data['selected_inbound'])) {
                                            $set('inbound_id', $data['selected_inbound']);
                                            Notification::make()->title('اینباند انتخاب شد')->success()->send();
                                        }
                                    })
                            ),
                        // ====================================================

                        Forms\Components\Toggle::make('is_active')
                            ->label('سرور فعال است')
                            ->default(true)
                            ->inline(false),
                    ]),


                Forms\Components\Section::make('تنظیمات لینک خروجی')
                    ->description('نوع لینک تحویلی به کاربر برای این سرور خاص')
                    ->schema([
                        Forms\Components\Radio::make('link_type')
                            ->label('نوع لینک')
                            ->options([
                                'single' => '🔸 لینک تکی (Single Config)',
                                'subscription' => '🔹 لینک سابسکریپشن (Subscription URL)',
                                'tunnel' => '🚇 لینک تانل شده (Tunneled)', // 🔥 گزینه سوم
                            ])
                            ->default('single')
                            ->required()
                            ->inline()
                            ->inlineLabel(false)
                            ->live(), // 🔥 مهم: برای نمایش فیلدهای شرطی

                        // 🔥 بخش ۱: وقتی سابسکریپشن انتخاب شد
                        Forms\Components\Grid::make(2)
                            ->visible(fn (Forms\Get $get) => $get('link_type') === 'subscription')
                            ->schema([
                                Forms\Components\TextInput::make('subscription_domain')
                                    ->label('دامنه/آدرس سابسکریپشن')
                                    ->placeholder('sub.example.com')
                                    ->helperText('مثال: sub.domain.com یا 1.2.3.4 (بدون http/https)')
                                    ->prefix(fn (Forms\Get $get) => $get('is_https') ? 'https://' : 'http://')
                                    ->required(),

                                Forms\Components\TextInput::make('subscription_path')
                                    ->label('مسیر (Path) سابسکریپشن')
                                    ->placeholder('/sub/')
                                    ->default('/sub/')
                                    ->helperText('معمولاً /sub/ یا /api/ است'),

                                Forms\Components\TextInput::make('subscription_port')
                                    ->label('پورت سابسکریپشن')
                                    ->numeric()
                                    ->default(2053)
                                    ->placeholder('2053'),
                            ]),

                        // 🔥 بخش ۲: وقتی تانل شده انتخاب شد
                        Forms\Components\Grid::make(2)
                            ->visible(fn (Forms\Get $get) => $get('link_type') === 'tunnel')
                            ->schema([
                                Forms\Components\TextInput::make('tunnel_address')
                                    ->label('آدرس IP/دامنه تانل')
                                    ->placeholder('77.237.70.163 یا tunnel.domain.com')
                                    ->helperText('📌 آدرسی که کاربر در لینک کانفیگ می‌بیند (آدرس سرور میانی/تانل)')
                                    ->required(),

                                Forms\Components\TextInput::make('tunnel_port')
                                    ->label('پورت تانل')
                                    ->numeric()
                                    ->default(443)
                                    ->placeholder('443')
                                    ->helperText('پورتی که روی سرور تانل باز شده (معمولاً 443 یا 8080)'),

                                Forms\Components\Toggle::make('tunnel_is_https')
                                    ->label('اتصال امن (HTTPS) برای تانل')
                                    ->default(false)
                                    ->inline(false),
                            ]),

                        // 🔥 بخش ۳: توضیحات برای لینک تکی (اختیاری)
                        Forms\Components\Placeholder::make('single_info')
                            ->content('✅ لینک تکی مستقیماً با آدرس IP/دامنه اصلی پنل ساخته می‌شود.')
                            ->visible(fn (Forms\Get $get) => $get('link_type') === 'single')
                            ->columnSpanFull(),

                    ])->columns(1),


                Forms\Components\Section::make('مدیریت ظرفیت')->schema([
                    Forms\Components\TextInput::make('capacity')
                        ->numeric()
                        ->default(100)
                        ->label('ظرفیت کل')
                        ->helperText('حداکثر تعداد کاربر مجاز'),

                    Forms\Components\TextInput::make('current_users')
                        ->numeric()
                        ->default(0)
                        ->label('کاربران فعلی')
                        ->disabled(),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('نام سرور')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('نوع پنل')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'xui' => 'primary',
                        'marzban' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('location.name')
                    ->label('لوکیشن')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('آدرس IP')
                    ->copyable(),

                Tables\Columns\TextColumn::make('link_type')
                    ->label('نوع لینک')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'single' => 'gray',
                        'subscription' => 'success',
                        'tunnel' => 'warning',
                        default => 'gray',
                    }),


                Tables\Columns\TextColumn::make('current_users')
                    ->label('وضعیت ظرفیت')
                    ->formatStateUsing(fn ($record) => "{$record->current_users} / {$record->capacity}")
                    ->color(fn ($record) => $record->current_users >= $record->capacity ? 'danger' : 'success')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('وضعیت')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListServers::route('/'),
            'create' => Pages\CreateServer::route('/create'),
            'edit' => Pages\EditServer::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        try {
            $settings = \App\Models\Setting::all()->pluck('value', 'key');
            $isMultiEnabled = filter_var(
                $settings->get('enable_multilocation', false),
                FILTER_VALIDATE_BOOLEAN
            );

            // Show if Multi-Location is enabled, regardless of panel type
            return $isMultiEnabled;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected static function generateDefaultSubUrl(Forms\Get $get): string
    {
        $ip = $get('ip_address');
        $isHttps = $get('is_https');
        $port = $get('subscription_port') ?? '';

        if (empty($ip)) {
            return 'https://example.com/sub/';
        }

        $protocol = $isHttps ? 'https://' : 'http://';
        // Assuming default path is /sub/
        return "{$protocol}{$ip}/sub/";
    }

}
