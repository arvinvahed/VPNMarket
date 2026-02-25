<?php

namespace App\Filament\Pages;

use App\Models\TelegramBotSetting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageResellerSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'مدیریت نمایندگی';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'تنظیمات نمایندگی';

    protected static ?string $title = 'تنظیمات نمایندگی';

    protected static string $view = 'filament.pages.manage-reseller-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = TelegramBotSetting::all()->pluck('value', 'key')->toArray();

        $this->form->fill([
            'agent_deposit_card_number' => $settings['agent_deposit_card_number'] ?? '',
            'agent_deposit_card_name' => $settings['agent_deposit_card_name'] ?? '',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('تنظیمات شارژ نماینده')
                    ->description('شماره کارت و نام صاحب کارت برای شارژ کیف پول نمایندگان در مینی‌اپ.')
                    ->schema([
                        TextInput::make('agent_deposit_card_number')
                            ->label('شماره کارت برای شارژ نمایندگان')
                            ->placeholder('6037 1234 5678 9999'),
                        TextInput::make('agent_deposit_card_name')
                            ->label('نام صاحب کارت')
                            ->placeholder('به نام مدیریت پنل'),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            TelegramBotSetting::updateOrCreate(['key' => $key], ['value' => $value ?? '']);
        }

        Notification::make()
            ->title('تنظیمات با موفقیت ذخیره شد.')
            ->success()
            ->send();
    }
}

