<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InboundResource\Pages;
use App\Models\Inbound;
use App\Services\XUIService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class InboundResource extends Resource
{
    protected static ?string $model = Inbound::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationLabel = 'اینباندها (سنایی/X-UI)';
    protected static ?string $modelLabel = 'اینباند';
    protected static ?string $pluralModelLabel = 'اینباندها';
    protected static ?string $navigationGroup = 'مدیریت پنل';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('عنوان دلخواه برای اینباند')
                    ->required()
                    ->helperText('یک نام مشخص برای این اینباند انتخاب کنید (مثلاً: VLESS WS آلمان).'),

                Forms\Components\Textarea::make('inbound_data')
                    ->label('اطلاعات JSON اینباند')
                    ->required()
                    ->json() // ولیدیشن برای اطمینان از صحت ساختار JSON
                    ->rows(20)
                    ->helperText('اطلاعات کامل اینباند را از پنل سنایی کپی کنید یا از دکمه "Sync از X-UI" استفاده کنید.')


                    ->afterStateHydrated(function (Forms\Components\Textarea $component, $state) {
                        if (is_array($state)) {
                            $component->state(json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                        }
                    })

                    ->dehydrateStateUsing(function ($state) {
                        if (is_string($state)) {
                            return json_decode($state, true);
                        }
                        return $state;
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('عنوان')
                    ->searchable(),

                Tables\Columns\TextColumn::make('panel_id')
                    ->label('ID در پنل')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('remark')
                    ->label('Remark')
                    ->searchable(),

                Tables\Columns\TextColumn::make('inbound_data.protocol')
                    ->label('پروتکل')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('inbound_data.port')
                    ->label('پورت'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('وضعیت')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('آخرین بروزرسانی')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Action::make('syncFromXUI')
                    ->label('Sync از X-UI')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Sync اینباندها از X-UI')
                    ->modalDescription('این عمل تمام اینباندها را از پنل X-UI دریافت و ذخیره میکند. آیا ادامه میدهید؟')
                    ->action(function () {
                        try {
                            $settings = \App\Models\Setting::all()->pluck('value', 'key');

                            if ($settings->get('panel_type') !== 'xui') {
                                Notification::make()
                                    ->title('خطا')
                                    ->body('پنل فعال X-UI نیست!')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $xui = new XUIService(
                                $settings->get('xui_host'),
                                $settings->get('xui_user'),
                                $settings->get('xui_pass')
                            );

                            if (!$xui->login()) {
                                Notification::make()
                                    ->title('خطا در لاگین')
                                    ->body('اطلاعات ورود به پنل نادرست است.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $inbounds = $xui->getInbounds();

                            if (is_null($inbounds)) {
                                Notification::make()
                                    ->title('خطا')
                                    ->body('اینباندها دریافت نشدند یا خطایی در ارتباط با پنل رخ داده است.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $synced = 0;
                            foreach ($inbounds as $inbound) {
                                $existing = Inbound::where('inbound_data->id', $inbound['id'])->first();

                                if ($existing) {
                                    $existing->update([
                                        'title' => $existing->title ?: ($inbound['remark'] ?? "Inbound {$inbound['id']}"),
                                        'inbound_data' => $inbound
                                    ]);
                                } else {
                                    Inbound::create([
                                        'title' => $inbound['remark'] ?? "Inbound {$inbound['id']}",
                                        'inbound_data' => $inbound
                                    ]);
                                }
                                $synced++;
                            }

                            Cache::forget('inbounds_dropdown');

                            Notification::make()
                                ->title('موفقیت')
                                ->body("{$synced} اینباند با موفقیت Sync شد. لطفاً صفحه را رفرش کنید (F5).")
                                ->success()
                                ->send();

                            return redirect(request()->header('Referer'));

                        } catch (\Exception $e) {
                            Log::error('XUI Sync failed: ' . $e->getMessage());
                            Notification::make()
                                ->title('خطا')
                                ->body('خطایی در Sync رخ داد: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInbounds::route('/'),
//          'create' => Pages\CreateInbound::route('/create'),
            'edit' => Pages\EditInbound::route('/{record}/edit'),
        ];
    }
}
