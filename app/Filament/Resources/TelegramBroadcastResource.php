<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TelegramBroadcastResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use App\Jobs\SendTelegramBroadcast;
use Illuminate\Support\Facades\Gate; // برای کنترل دسترسی

class TelegramBroadcastResource extends Resource
{
    protected static ?string $model = \App\Models\User::class; // فقط برای نمایش یک صفحه خالی
    protected static ?string $navigationIcon = 'heroicon-o-megaphone'; // آیکون بلندگو
    protected static ?string $navigationGroup = 'مدیریت کاربران';
    protected static ?string $navigationLabel = 'پیام همگانی تلگرام';
    protected static ?string $pluralModelLabel = 'پیام همگانی تلگرام';
    protected static ?string $modelLabel = 'پیام همگانی';

    // این Resource فقط برای یک اکشن است، فرم و جدول را خالی می‌گذاریم.
    public static function form(Form $form): Form
    {
        return $form
            ->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // هیچ ستونی نیاز نیست
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ]);
    }

    // استفاده از یک Global Page برای نمایش فرم
    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTelegramBroadcasts::route('/'),
        ];
    }
}
