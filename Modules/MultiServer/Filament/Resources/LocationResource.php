<?php

namespace Modules\MultiServer\Filament\Resources;

use Modules\MultiServer\Filament\Resources\LocationResource\Pages;
use Modules\MultiServer\Models\Location;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;
    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationGroup = 'مولتی سرور';
    protected static ?string $label = 'لوکیشن';
    protected static ?string $pluralLabel = 'لوکیشن‌ها';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()->schema([
                    Forms\Components\TextInput::make('name')->label('نام کشور')->required(),
                    Forms\Components\TextInput::make('flag')->label('پرچم (اموجی)')->placeholder('🇩🇪'),
                    Forms\Components\TextInput::make('slug')->label('شناسه یکتا')->required()->unique(ignoreRecord: true),
                    Forms\Components\Toggle::make('is_active')->label('فعال')->default(true),
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('flag')->label('پرچم')->size(Forms\Components\Textarea::class),
                Tables\Columns\TextColumn::make('name')->label('کشور')->searchable(),
                Tables\Columns\TextColumn::make('servers_count')->counts('servers')->label('تعداد سرور'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('وضعیت'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}
