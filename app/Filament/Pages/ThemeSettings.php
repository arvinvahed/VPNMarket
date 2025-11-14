<?php

namespace App\Filament\Pages;

use App\Models\Inbound;
use App\Models\Setting;
use Filament\Forms\Components\FileUpload;
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

class ThemeSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string $view = 'filament.pages.theme-settings';
    protected static ?string $navigationLabel = 'تنظیمات سایت';
    protected static ?string $title = 'تنظیمات و محتوای سایت';

    public ?array $data = [];

//    public function mount(): void
//    {
//        $this->data = Setting::all()->pluck('value', 'key')->toArray();
//    }




//    public function mount(): void
//    {
//
//        $settings = Setting::all()->pluck('value', 'key')->toArray();
//
//
//        $defaultData = [
//            'panel_type' => 'marzban',
//            'xui_host' => null,
//            'xui_user' => null,
//            'xui_pass' => null,
//            'xui_default_inbound_id' => null,
//            'xui_link_type' => 'single',
//        ];

    public function mount(): void
    {
        // تمام تنظیمات ذخیره شده را از دیتابیس بخوان
        $settings = Setting::all()->pluck('value', 'key')->toArray();


        $this->form->fill(array_merge([
            'panel_type' => 'marzban',
            'xui_host' => '',
            'xui_user' => '',
            'xui_pass' => '',
            'xui_default_inbound_id' => null,
            'xui_link_type' => 'single',
            'marzban_host' => '',
            'marzban_sudo_username' => '',
            'marzban_sudo_password' => '',
        ], $settings));
    }


//
//        $this->data = array_merge($defaultData, $settings);
//
//
//    }
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
                            // --- تغییر کلیدی ۲: حذف ->required() از این فیلدها ---
                            Select::make('active_theme')->label('قالب اصلی سایت')->options([
                                'welcome' => 'قالب خوش‌آمدگویی',
//                                'cyberpunk' => 'قالب سایبرپانک',
//                                'dragon' => 'قالب اژدها',
//                                'arcane' => 'قالب آرکین (جادوی تکنولوژی)',
                                'rocket' => 'قالب RoketVPN (موشکی)',

                            ])->default('welcome')->live(),
                            Select::make('active_auth_theme')->label('قالب صفحات ورود/ثبت‌نام')->options([
                                'default' => 'قالب پیش‌فرض (Breeze)',
                                'cyberpunk' => 'قالب سایبرپانک',
//                                'dragon' => 'قالب اژدها',
                                'rocket' => 'قالب RoketVPN (موشکی)',

                            ])->default('cyberpunk')->live(),
//                            FileUpload::make('site_logo')->label('لوگوی سایت')->image()->directory('logos')->visibility('public'),

                        ]),


                    Tabs\Tab::make('محتوای قالب RoketVPN (موشکی)')
                        ->icon('heroicon-o-rocket-launch')
                        ->visible(fn(Get $get) => $get('active_theme') === 'rocket') // توجه: کلید 'rocket'
                        ->schema([
                            Section::make('عمومی')->schema([
                                TextInput::make('rocket_navbar_brand')->label('نام برند در Navbar')->placeholder('Roket VPN'),
                                TextInput::make('rocket_footer_text')->label('متن فوتر')->placeholder('© 2025 Roket VPN. پرتاب به آزادی.'),
                            ])->columns(2),
                            Section::make('بخش اصلی (Hero Section)')->schema([
                                TextInput::make('rocket_hero_title')->label('تیتر اصلی')->placeholder('پرتاب به آزادی اینترنت'),
                                Textarea::make('rocket_hero_subtitle')->label('زیرتیتر')->rows(2)->placeholder('با Roket VPN، مرزهای دیجیتال را بشکنید و با سرعت نور، به هر محتوایی دسترسی پیدا کنید.'),
                                TextInput::make('rocket_hero_button_text')->label('متن دکمه اصلی')->placeholder('شلیک به آسمان'),
                            ]),
                            Section::make('بخش ویژگی‌ها (Features)')->schema([
                                TextInput::make('rocket_features_title')->label('عنوان بخش')->placeholder('موتورهای قدرتمند ما'),
                                TextInput::make('rocket_feature1_title')->label('عنوان ویژگی ۱')->placeholder('سرعت مافوق صوت'),
                                Textarea::make('rocket_feature1_desc')->label('توضیح ویژگی ۱')->rows(2),
                                TextInput::make('rocket_feature2_title')->label('عنوان ویژگی ۲')->placeholder('امنیت فضایی'),
                                Textarea::make('rocket_feature2_desc')->label('توضیح ویژگی ۲')->rows(2),
                                TextInput::make('rocket_feature3_title')->label('عنوان ویژگی ۳')->placeholder('عبور از هر مانع'),
                                Textarea::make('rocket_feature3_desc')->label('توضیح ویژگی ۳')->rows(2),
                            ])->columns(3),
                            Section::make('بخش قیمت‌گذاری (Pricing)')->schema([
                                TextInput::make('rocket_pricing_title')->label('عنوان بخش')->placeholder('مدار خود را انتخاب کنید'),
                            ]),
                            Section::make('بخش سوالات متداول (FAQ)')->schema([
                                TextInput::make('rocket_faq_title')->label('عنوان بخش')->placeholder('پرواز بدون پرسش؟'),
                                TextInput::make('rocket_faq1_q')->label('سوال اول')->placeholder('آیا Roket VPN داده‌های من را ردیابی می‌کند؟'),
                                Textarea::make('rocket_faq1_a')->label('پاسخ اول')->rows(2),
                                TextInput::make('rocket_faq2_q')->label('سوال دوم')->placeholder('چگونه می‌توانم طرح خود را ارتقا دهم؟'),
                                Textarea::make('rocket_faq2_a')->label('پاسخ دوم')->rows(2),
                            ]),
                        ]),

                    Tabs\Tab::make('محتوای قالب اژدها')->icon('heroicon-o-fire')->visible(fn(Get $get) => $get('active_theme') === 'dragon')->schema([
                        Section::make('عمومی')->schema([
                            TextInput::make('dragon_navbar_brand')->label('نام برند در Navbar')->placeholder('EZHDEHA VPN'),
                            TextInput::make('dragon_footer_text')->label('متن فوتر')->placeholder('© 2025 Ezhdeha Networks. قدرت آتشین.'),
                        ])->columns(2),
                        Section::make('بخش اصلی (Hero Section)')->schema([
                            TextInput::make('dragon_hero_title')->label('تیتر اصلی')->placeholder('مرزهای دیجیتال را بسوزان'),
                            Textarea::make('dragon_hero_subtitle')->label('زیرتیتر')->rows(2)->placeholder('سرعتی افسانه‌ای و امنیتی نفوذناپذیر. سلطه بر اینترنت.'),
                            TextInput::make('dragon_hero_button_text')->label('متن دکمه اصلی')->placeholder('فتح شبکه'),
                        ]),
                        Section::make('بخش ویژگی‌ها (Features)')->schema([
                            TextInput::make('dragon_features_title')->label('عنوان بخش')->placeholder('عناصر قدرت اژدها'),
                            TextInput::make('dragon_feature1_title')->label('عنوان ویژگی ۱')->placeholder('نفس آتشین (سرعت)'),
                            Textarea::make('dragon_feature1_desc')->label('توضیح ویژگی ۱')->rows(2),
                            TextInput::make('dragon_feature2_title')->label('عنوان ویژگی ۲')->placeholder('زره فلس‌دار (امنیت)'),
                            Textarea::make('dragon_feature2_desc')->label('توضیح ویژگی ۲')->rows(2),
                            TextInput::make('dragon_feature3_title')->label('عنوان ویژگی ۳')->placeholder('بینایی فراتر (آزادی)'),
                            Textarea::make('dragon_feature3_desc')->label('توضیح ویژگی ۳')->rows(2),
                        ])->columns(3),
                        Section::make('بخش قیمت‌گذاری (Pricing)')->schema([
                            TextInput::make('dragon_pricing_title')->label('عنوان بخش')->placeholder('پیمان خون'),
                        ]),
                        Section::make('بخش سوالات متداول (FAQ)')->schema([
                            TextInput::make('dragon_faq_title')->label('عنوان بخش')->placeholder('طومارهای باستانی'),
                            TextInput::make('dragon_faq1_q')->label('سوال اول')->placeholder('آیا این سرویس باستانی است؟'),
                            Textarea::make('dragon_faq1_a')->label('پاسخ اول')->rows(2),
                            TextInput::make('dragon_faq2_q')->label('سوال دوم')->placeholder('چگونه قدرت اژدها را فعال کنم؟'),
                            Textarea::make('dragon_faq2_a')->label('پاسخ دوم')->rows(2),
                        ]),
                    ]),

                    Tabs\Tab::make('محتوای قالب آرکین')->icon('heroicon-o-sparkles')->visible(fn(Get $get) => $get('active_theme') === 'arcane')->schema([
                        Section::make('عمومی')->schema([
                            TextInput::make('arcane_navbar_brand')->label('نام برند')->placeholder('ARCANE'),
                            TextInput::make('arcane_footer_text')->label('متن فوتر')->placeholder('© 2025 Arcane Networks'),
                        ]),
                        Section::make('بخش اصلی (Hero Section)')->schema([
                            TextInput::make('arcane_hero_title')->label('تیتر اصلی')->placeholder('کدگشایی اینترنت آزاد'),
                            Textarea::make('arcane_hero_subtitle')->label('زیرتیتر')->rows(2),
                            TextInput::make('arcane_hero_button')->label('متن دکمه')->placeholder('دسترسی به شبکه'),
                        ]),
                        Section::make('بخش ویژگی‌ها (Features)')->schema([
                            TextInput::make('arcane_features_title')->label('عنوان بخش')->placeholder('اصول جادوی دیجیتال'),
                            TextInput::make('arcane_feature1_title')->label('عنوان ویژگی ۱')->placeholder('پروتکل‌های کوانتومی'),
                            Textarea::make('arcane_feature1_desc')->label('توضیح ویژگی ۱')->rows(2),
                            TextInput::make('arcane_feature2_title')->label('عنوان ویژگی ۲')->placeholder('پنهان‌سازی هویت'),
                            Textarea::make('arcane_feature2_desc')->label('توضیح ویژگی ۲')->rows(2),
                            TextInput::make('arcane_feature3_title')->label('عنوان ویژگی ۳')->placeholder('اتصال بی‌پایان'),
                            Textarea::make('arcane_feature3_desc')->label('توضیح ویژگی ۳')->rows(2),
                        ])->columns(3),
                        Section::make('بخش قیمت‌گذاری (Pricing)')->schema([
                            TextInput::make('arcane_pricing_title')->label('عنوان بخش')->placeholder('انتخاب دسترسی'),
                        ]),
                        Section::make('بخش سوالات متداول (FAQ)')->schema([
                            TextInput::make('arcane_faq_title')->label('عنوان بخش')->placeholder('سوالات متداول'),
                            TextInput::make('arcane_faq1_q')->label('سوال اول')->placeholder('آیا اطلاعات کاربران ذخیره می‌شود؟'),
                            Textarea::make('arcane_faq1_a')->label('پاسخ اول')->rows(2),
                            TextInput::make('arcane_faq2_q')->label('سوال دوم')->placeholder('چگونه می‌توانم سرویس را روی چند دستگاه استفاده کنم؟'),
                            Textarea::make('arcane_faq2_a')->label('پاسخ دوم')->rows(2),
                        ]),
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
                        Section::make('متن‌های عمومی')->schema([TextInput::make('auth_brand_name')->label('نام برند')->placeholder('VPNMarket'),]),
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

                    Tabs\Tab::make('تنظیمات پنل V2Ray')->icon('heroicon-o-server-stack')->schema([

                        Radio::make('panel_type')->label('نوع پنل')->options(['marzban' => 'مرزبان', 'xui' => 'سنایی / X-UI'])->live()->required(),
                        Section::make('تنظیمات پنل مرزبان')->visible(fn (Get $get) => $get('panel_type') === 'marzban')->schema([
                            TextInput::make('marzban_host')->label('آدرس پنل مرزبان')->required(),
                            TextInput::make('marzban_sudo_username')->label('نام کاربری ادمین')->required(),
                            TextInput::make('marzban_sudo_password')->label('رمز عبور ادمین')->password()->required(),
                            TextInput::make('marzban_node_hostname')->label('آدرس دامنه/سرور برای کانفیگ')
                        ]),
                        Section::make('تنظیمات پنل سنایی / X-UI')
                            ->visible(fn(Get $get) => $get('panel_type') === 'xui')
                            ->schema([

                                TextInput::make('xui_host')->label('آدرس کامل پنل سنایی')
                                    ->required(fn(Get $get): bool => $get('panel_type') === 'xui'),
                                TextInput::make('xui_user')->label('نام کاربری')
                                    ->required(fn(Get $get): bool => $get('panel_type') === 'xui'),
                                TextInput::make('xui_pass')->label('رمز عبور')->password()
                                    ->required(fn(Get $get): bool => $get('panel_type') === 'xui'),
                                Select::make('xui_default_inbound_id')
                                    ->label('اینباند پیش‌فرض')
                                    ->options(function () {
                                        // این کد آرایه‌ای با فرمت [id => "title (ID: panel_id)"] می‌سازد
                                        return Inbound::all()->mapWithKeys(function ($inbound) {
                                            return [$inbound->id => "{$inbound->title} (ID: {$inbound->panel_id})"];
                                        });
                                    })
                                    ->searchable()
                                    ->requiredIf('panel_type', 'xui'),

                                Radio::make('xui_link_type')->label('نوع لینک تحویلی')->options(['single' => 'لینک تکی', 'subscription' => 'لینک سابسکریپشن'])->default('single')
                                    ->required(fn(Get $get): bool => $get('panel_type') === 'xui'),
                                TextInput::make('xui_subscription_url_base')->label('آدرس پایه لینک سابسکریپشن'),
                            ]),
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
            Setting::updateOrCreate(['key' => $key], ['value' => $value ?? '']);
        }

        Notification::make()->title('تنظیمات با موفقیت ذخیره شد.')->success()->send();
    }
}
