<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageTrialSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'مدیریت کاربران';
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationLabel = 'تنظیمات اکانت تست';
    protected static string $view = 'filament.pages.manage-trial-settings';


    protected static ?string $title = 'مدیریت تنظیمات اکانت تست';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();
        $this->form->fill([
            'trial_enabled' => $settings['trial_enabled'] ?? false,
            'trial_volume_mb' => $settings['trial_volume_mb'] ?? 500,
            'trial_duration_hours' => $settings['trial_duration_hours'] ?? 24,
            'trial_limit_per_user' => $settings['trial_limit_per_user'] ?? 1,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('تنظیمات اصلی اکانت تست')
                    ->description('در این بخش می‌توانید قابلیت اکانت تست را فعال کرده و مقادیر پیش‌فرض آن را تعیین کنید.')
                    ->schema([
                        Toggle::make('trial_enabled')
                            ->label('فعال‌سازی اکانت تست')
                            ->helperText('اگر فعال باشد، کاربران می‌توانند از ربات اکانت تست دریافت کنند.'),

                        TextInput::make('trial_volume_mb')
                            ->label('حجم اکانت تست (مگابایت)')
                            ->numeric()
                            ->required()
                            ->helperText('حجمی که به کاربر تست اختصاص داده می‌شود. مثلا: 500'),

                        TextInput::make('trial_duration_hours')
                            ->label('مدت زمان اکانت تست (ساعت)')
                            ->numeric()
                            ->required()
                            ->helperText('اکانت تست پس از چند ساعت منقضی می‌شود. مثلا: 24 برای یک روز.'),

                        TextInput::make('trial_limit_per_user')
                            ->label('محدودیت هر کاربر')
                            ->numeric()
                            ->required()
                            ->helperText('هر کاربر چند بار مجاز به دریافت اکانت تست است؟ مثلا: 1'),
                    ])
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Notification::make()->title('تنظیمات با موفقیت ذخیره شد.')->success()->send();
    }
}
