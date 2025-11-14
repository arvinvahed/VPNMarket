<?php

namespace App\Filament\Resources\TelegramBroadcastResource\Pages;

use App\Filament\Resources\TelegramBroadcastResource;
use Filament\Actions;
use Filament\Resources\Pages\Page;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use App\Jobs\SendTelegramBroadcast;

class ManageTelegramBroadcasts extends Page
{
    protected static string $resource = TelegramBroadcastResource::class;
    protected static ?string $title = 'ارسال پیام همگانی';
    protected static string $view = 'filament.resources.telegram-broadcast.manage-telegram-broadcast';

    // --- تعریف یک اکشن برای ارسال پیام ---
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('send_broadcast')
                ->label('ارسال پیام همگانی جدید')
                ->color('danger') // رنگ متمایز
                ->icon('heroicon-o-arrow-path')
                ->modalHeading('ارسال پیام همگانی به همه کاربران')
                ->form([
                    Textarea::make('message')
                        ->label('متن پیام همگانی (برای تلگرام)')
                        ->helperText('این پیام برای تمام کاربرانی که چت آی‌دی تلگرام دارند، ارسال می‌شود.')
                        ->required()
                        ->rows(8)
                        ->maxLength(4096),
                ])
                ->action(function (array $data) {

                    // 1. Job را به صف می‌فرستد
                    SendTelegramBroadcast::dispatch($data['message']);

                    // 2. نمایش نوتیفیکیشن موفقیت
                    Notification::make()
                        ->title('در حال ارسال...')
                        ->body('عملیات ارسال پیام همگانی در پس‌زمینه (Queue) آغاز شد. ممکن است تکمیل آن زمان ببرد.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
