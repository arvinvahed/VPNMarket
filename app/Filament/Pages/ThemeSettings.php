<?php

namespace App\Filament\Pages;

use App\Models\Inbound;
use App\Models\Setting;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ThemeSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string $view = 'filament.pages.theme-settings';
    protected static ?string $navigationLabel = 'تنظیمات سایت';
    protected static ?string $title = 'تنظیمات و محتوای سایت';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();


        foreach ($settings as $key => $value) {
            if ($value === '') {
                $settings[$key] = null;
            }
            if ($key === 'xui_default_inbound_id' && $value !== null) {
                $settings[$key] = (string) $value;
            }
        }

        $this->form->fill(array_merge([
            'panel_type' => 'marzban',
            'xui_host' => null,
            'xui_user' => null,
            'xui_pass' => null,
            'xui_default_inbound_id' => null,
            'xui_link_type' => 'single',
            'marzban_host' => null,
            'marzban_sudo_username' => null,
            'marzban_sudo_password' => null,
            'site_login_url' => null,
        ], $settings));
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('Tabs')
                ->id('main-tabs')
                ->persistTab()
                ->tabs([
                    Tabs\Tab::make('تنظیمات قالب')
                        ->icon('heroicon-o-swatch')
                        ->schema([
                            Select::make('active_theme')->label('قالب اصلی سایت')->options([
                                'welcome' => 'قالب خوش‌آمدگویی',
                                'rocket' => 'قالب RoketVPN (موشکی)',
                            ])->default('welcome')->live(),
                            Select::make('active_auth_theme')->label('قالب صفحات ورود/ثبت‌نام')->options([
                                'default' => 'قالب پیش‌فرض (Breeze)',
                                'cyberpunk' => 'قالب سایبرپانک',
                                'rocket' => 'قالب RoketVPN (موشکی)',
                            ])->default('cyberpunk')->live(),
                        ]),

                    Tabs\Tab::make('محتوای قالب RoketVPN (موشکی)')
                        ->icon('heroicon-o-rocket-launch')
                        ->visible(fn(Get $get) => $get('active_theme') === 'rocket')
                        ->schema([
                            Section::make('عمومی')->schema([
                                TextInput::make('rocket_navbar_brand')->label('نام برند در Navbar'),
                                TextInput::make('rocket_footer_text')->label('متن فوتر'),
                            ])->columns(2),
                            Section::make('بخش اصلی (Hero Section)')->schema([
                                TextInput::make('rocket_hero_title')->label('تیتر اصلی'),
                                Textarea::make('rocket_hero_subtitle')->label('زیرتیتر')->rows(2),
                                TextInput::make('rocket_hero_button_text')->label('متن دکمه اصلی'),
                            ]),
                            Section::make('بخش قیمت‌گذاری (Pricing)')->schema([
                                TextInput::make('rocket_pricing_title')->label('عنوان بخش'),
                            ]),
                            Section::make('بخش سوالات متداول (FAQ)')->schema([
                                TextInput::make('rocket_faq_title')->label('عنوان بخش'),
                                TextInput::make('rocket_faq1_q')->label('سوال اول'),
                                Textarea::make('rocket_faq1_a')->label('پاسخ اول')->rows(2),
                                TextInput::make('rocket_faq2_q')->label('سوال دوم'),
                                Textarea::make('rocket_faq2_a')->label('پاسخ دوم')->rows(2),
                            ]),
                            Section::make('لینک‌های اجتماعی')->schema([
                                TextInput::make('telegram_link')->label('لینک تلگرام (کامل)'),
                                TextInput::make('instagram_link')->label('لینک اینستاگرام (کامل)'),
                            ])->columns(2),
                        ]),

                    Tabs\Tab::make('محتوای قالب سایبرپانک')->icon('heroicon-o-bolt')->visible(fn(Get $get) => $get('active_theme') === 'cyberpunk')->schema([
                        Section::make('عمومی')->schema([
                            TextInput::make('cyberpunk_navbar_brand')->label('نام برند در Navbar')->placeholder('VPN Market'),
                            TextInput::make('cyberpunk_footer_text')->label('متن فوتر')->placeholder('© 2025 Quantum Network. اتصال برقرار شد.'),
                        ])->columns(2),
                        Section::make('بخش اصلی (Hero Section)')->schema([
                            TextInput::make('cyberpunk_hero_title')->label('تیتر اصلی')->placeholder('واقعیت را هک کن'),
                            Textarea::make('cyberpunk_hero_subtitle')->label('زیرتیتر')->rows(3),
                            TextInput::make('cyberpunk_hero_button_text')->label('متن دکمه اصلی')->placeholder('دریافت دسترسی'),
                        ]),
                        Section::make('بخش ویژگی‌ها (Features)')->schema([
                            TextInput::make('cyberpunk_features_title')->label('عنوان بخش')->placeholder('سیستم‌عامل آزادی دیجیتال شما'),
                            TextInput::make('cyberpunk_feature1_title')->label('عنوان ویژگی ۱')->placeholder('پروتکل Warp'),
                            Textarea::make('cyberpunk_feature1_desc')->label('توضیح ویژگی ۱')->rows(2),
                            TextInput::make('cyberpunk_feature2_title')->label('عنوان ویژگی ۲')->placeholder('حالت Ghost'),
                            Textarea::make('cyberpunk_feature2_desc')->label('توضیح ویژگی ۲')->rows(2),
                            TextInput::make('cyberpunk_feature3_title')->label('عنوان ویژگی ۳')->placeholder('اتصال پایدار'),
                            Textarea::make('cyberpunk_feature3_desc')->label('توضیح ویژگی ۳')->rows(2),
                            TextInput::make('cyberpunk_feature4_title')->label('عنوان ویژگی ۴')->placeholder('پشتیبانی Elite'),
                            Textarea::make('cyberpunk_feature4_desc')->label('توضیح ویژگی ۴')->rows(2),
                        ])->columns(2),
                        Section::make('بخش قیمت‌گذاری (Pricing)')->schema([
                            TextInput::make('cyberpunk_pricing_title')->label('عنوان بخش')->placeholder('انتخاب پلن اتصال'),
                        ]),
                        Section::make('بخش سوالات متداول (FAQ)')->schema([
                            TextInput::make('cyberpunk_faq_title')->label('عنوان بخش')->placeholder('اطلاعات طبقه‌بندی شده'),
                            TextInput::make('cyberpunk_faq1_q')->label('سوال اول')->placeholder('آیا اطلاعات کاربران ذخیره می‌شود؟'),
                            Textarea::make('cyberpunk_faq1_a')->label('پاسخ اول')->rows(2),
                            TextInput::make('cyberpunk_faq2_q')->label('سوال دوم')->placeholder('چگونه می‌توانم سرویس را روی چند دستگاه استفاده کنم؟'),
                            Textarea::make('cyberpunk_faq2_a')->label('پاسخ دوم')->rows(2),
                        ]),
                    ]),

                    Tabs\Tab::make('محتوای صفحات ورود')->icon('heroicon-o-key')->schema([
                        Section::make('متن‌های عمومی')->schema([
                            TextInput::make('auth_brand_name')->label('نام برند')->placeholder('VPNMarket'),
                        ]),
                        Section::make('صفحه ورود (Login)')->schema([
                            TextInput::make('auth_login_title')->label('عنوان فرم ورود'),
                            TextInput::make('auth_login_email_placeholder')->label('متن داخل فیلد ایمیل'),
                            TextInput::make('auth_login_password_placeholder')->label('متن داخل فیلد رمز عبور'),
                            TextInput::make('auth_login_remember_me_label')->label('متن "مرا به خاطر بسپار"'),
                            TextInput::make('auth_login_forgot_password_link')->label('متن لینک "فراموشی رمز"'),
                            TextInput::make('auth_login_submit_button')->label('متن دکمه ورود'),
                            TextInput::make('auth_login_register_link')->label('متن لینک ثبت‌نام'),
                        ])->columns(2),
                        Section::make('صفحه ثبت‌نام (Register)')->schema([
                            TextInput::make('auth_register_title')->label('عنوان فرم ثبت‌نام'),
                            TextInput::make('auth_register_name_placeholder')->label('متن داخل فیلد نام'),
                            TextInput::make('auth_register_password_confirm_placeholder')->label('متن داخل فیلد تکرار رمز'),
                            TextInput::make('auth_register_submit_button')->label('متن دکمه ثبت‌نام'),
                            TextInput::make('auth_register_login_link')->label('متن لینک ورود'),
                        ])->columns(2),
                    ]),

                    Tabs\Tab::make('تنظیمات پنل V2Ray')
                        ->icon('heroicon-o-server-stack')
                        ->schema([
                            Section::make('🌍 سیستم مولتی سرور')
                                ->description('تمامی سرورها (مرزبان، سنایی و...) را از طریق منوی «مولتی سرور» در نوار کناری مدیریت کنید.')
                                ->schema([
                                    Placeholder::make('multiserver_info')
                                        ->content('✅ سیستم به طور کامل روی حالت مولتی سرور تنظیم شده است. لطفاً برای افزودن سرور جدید به منوی «مولتی سرور > سرورها» مراجعه کنید.')
                                        ->columnSpanFull(),
                                ]),
                                
                            // Hidden fields to maintain backward compatibility if needed, or forced values
                            Hidden::make('panel_type')->default('xui'), 
                            Hidden::make('enable_multilocation')->default(true),
                        ]),

                    Tabs\Tab::make('تنظیمات پرداخت')->icon('heroicon-o-credit-card')->schema([
                        Section::make('پرداخت کارت به کارت')->schema([
                            TextInput::make('payment_card_number')
                                ->label('شماره کارت')
                                ->mask('9999-9999-9999-9999')
                                ->placeholder('XXXX-XXXX-XXXX-XXXX')
                                ->helperText('شماره کارت ۱۶ رقمی خود را وارد کنید.')
                                ->numeric(false)
                                ->validationAttribute('شماره کارت'),
                            TextInput::make('payment_card_holder_name')->label('نام صاحب حساب'),
                            Textarea::make('payment_card_instructions')->label('توضیحات اضافی')->rows(3),
                        ]),
                    ]),

                    Tabs\Tab::make('تنظیمات ربات تلگرام')->icon('heroicon-o-paper-airplane')->schema([
                        Section::make('اطلاعات اتصال ربات')->schema([
                            TextInput::make('telegram_bot_token')->label('توکن ربات تلگرام')->password(),
                            TextInput::make('telegram_admin_chat_id')->label('چت آی‌دی ادمین')->numeric(),
                            TextInput::make('site_login_url')
                                ->label('آدرس ورود به سایت (استفاده در دکمه اطلاعات ورود)')
                                ->placeholder('https://example.com/login')
                                ->helperText('در صورت خالی بودن، آدرس پیش‌فرض سیستم نمایش داده می‌شود.')
                                ->url(),
                        ]),
                        Section::make('اجبار به عضویت در کانال')
                            ->description('کاربران باید قبل از استفاده از ربات، در کانال عضو شوند.')
                            ->schema([
                                Toggle::make('force_join_enabled')
                                    ->label('فعالسازی اجبار به عضویت')
                                    ->reactive()
                                    ->default(false),
                                TextInput::make('telegram_required_channel_id')
                                    ->label('آی‌دی کانال (Username یا Chat ID)')
                                    ->placeholder('@mychannel یا -100123456789')
                                    ->hint('اگر کانال عمومی است @username و اگر خصوصی است Chat ID (مثل -100123456789) را وارد کنید.')
                                    ->required(fn (Get $get): bool => $get('force_join_enabled') === true)
                                    ->maxLength(100),
                            ]),
                    ]),

                    Tabs\Tab::make('سیستم دعوت از دوستان')
                        ->icon('heroicon-o-gift')
                        ->schema([
                            Section::make('تنظیمات پاداش دعوت')
                                ->description('مبالغ پاداش را به تومان وارد کنید.')
                                ->schema([
                                    TextInput::make('referral_welcome_gift')
                                        ->label('هدیه خوش‌آمدگویی')
                                        ->numeric()
                                        ->default(0)
                                        ->helperText('مبلغی که بلافاصله پس از ثبت‌نام با کد معرف، به کیف پول کاربر جدید اضافه می‌شود.'),
                                    TextInput::make('referral_referrer_reward')
                                        ->label('پاداش معرف')
                                        ->numeric()
                                        ->default(0)
                                        ->helperText('مبلغی که پس از اولین خرید موفق کاربر جدید، به کیف پول معرف او اضافه می‌شود.'),
                                ]),
                        ]),

                ])->columnSpanFull(),
        ])->statePath('data');
    }

    public function submit(): void
    {
        $this->form->validate();
        $formData = $this->form->getState();

        foreach ($formData as $key => $value) {
            // حذف تنظیمات خالی
            if ($value === '' || $value === null) {
                \App\Models\Setting::where('key', $key)->delete();
                Cache::forget("setting.{$key}");
                continue;
            }

            // 🔥 مهم: تبدیل xui_default_inbound_id به string ساده
            if ($key === 'xui_default_inbound_id') {
                $value = (string) $value;
            }

            // ذخیره مستقیم
            \App\Models\Setting::updateOrCreate(
                ['key' => $key],
                ['value' => is_array($value) || is_object($value) ? json_encode($value) : $value]
            );

            Cache::forget("setting.{$key}");
        }

        // پاک کردن کش‌های مرتبط
        Cache::forget('inbounds_dropdown');
        Cache::forget('settings');

        Notification::make()
            ->title('تنظیمات با موفقیت ذخیره شد.')
            ->success()
            ->send();
    }
}
