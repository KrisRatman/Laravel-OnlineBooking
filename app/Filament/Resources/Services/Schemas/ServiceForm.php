<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Filament\Support\MoneyInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Описание')
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('duration_minutes')
                            ->label('Длительность')
                            ->helperText('Сколько длится сама услуга.')
                            ->numeric()
                            ->integer()
                            ->minValue(5)
                            ->maxValue(720)
                            ->step(5)
                            ->suffix('мин')
                            ->required(),
                        TextInput::make('buffer_minutes')
                            ->label('Буфер после')
                            ->helperText('Уборка и подготовка: мастер занят, но клиент не ждёт.')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(120)
                            ->step(5)
                            ->default(0)
                            ->suffix('мин')
                            ->required(),
                        MoneyInput::make('price')
                            ->label('Цена')
                            ->required(),
                        TextInput::make('sort')
                            ->label('Порядок в каталоге')
                            ->numeric()
                            ->integer()
                            ->default(0),
                        Select::make('staff')
                            ->label('Мастера')
                            ->helperText('Кто оказывает услугу. Свою цену или длительность мастера можно задать в карточке мастера.')
                            ->relationship('staff', 'name')
                            ->multiple()
                            ->preload()
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Показывать клиентам')
                            ->default(true),
                    ]),
            ]);
    }
}
