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




    public function mount(): void
    {

        $settings = Setting::all()->pluck('value', 'key')->toArray();


        $defaultData = [];


        $this->data = array_merge($defaultData, $settings);


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
                            // --- تغییر کلیدی ۲: حذف ->required() از این فیلدها ---
                            Select::make('active_theme')->label('قالب اصلی سایت')->options([
                                'welcome' => 'قالب خوش‌آمدگویی',
                                'cyberpunk' => 'قالب سایبرپانک',
                                'dragon' => 'قالب اژدها',
                                'arcane' => 'قالب آرکین (جادوی تکنولوژی)',
                            ])->default('welcome')->live(),
                            Select::make('active_auth_theme')->label('قالب صفحات ورود/ثبت‌نام')->options([
                                'default' => 'قالب پیش‌فرض (Breeze)',
                                'cyberpunk' => 'قالب سایبرپانک',
                                'dragon' => 'قالب اژدها',
                            ])->default('cyberpunk')->live(),
//                            FileUpload::make('site_logo')->label('لوگوی سایت')->image()->directory('logos')->visibility('public'),

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
