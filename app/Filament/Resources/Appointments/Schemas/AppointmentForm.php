<?php

namespace App\Filament\Resources\Appointments\Schemas;

use App\Filament\Support\SlotFields;
use App\Support\Phone;
use Closure;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Запись из админки (клиент позвонил или пришёл): те же проверки слотов, что и онлайн.
 */
class AppointmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Услуга и время')
                    ->columnSpanFull()
                    ->schema(SlotFields::make()),
                Section::make('Клиент')
                    ->description('Если клиент с таким телефоном уже есть, запись добавится к нему.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('phone')
                            ->label('Телефон')
                            ->tel()
                            ->placeholder('+7 (999) 123-45-67')
                            ->required()
                            ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) {
                                if (Phone::normalize((string) $value) === null) {
                                    $fail('Введите номер в формате +7 (999) 123-45-67.');
                                }
                            }),
                        TextInput::make('name')
                            ->label('Имя')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('email')
                            ->label('Email')
                            ->email(),
                        Toggle::make('confirm')
                            ->label('Сразу подтвердить')
                            ->default(true)
                            ->inline(false),
                        Textarea::make('comment')
                            ->label('Комментарий')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
