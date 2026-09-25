<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Models\Client;
use App\Support\Phone;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClientForm
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
                            ->label('Имя')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('phone')
                            ->label('Телефон')
                            ->tel()
                            ->required()
                            ->dehydrateStateUsing(fn (?string $state) => Phone::normalize((string) $state) ?? $state)
                            // Уникальность проверяем по нормализованному номеру: «8 999…» и «+7 999…» — один клиент.
                            ->rule(fn (?Client $record) => function (string $attribute, mixed $value, \Closure $fail) use ($record) {
                                $phone = Phone::normalize((string) $value);

                                if ($phone === null) {
                                    $fail('Введите номер в формате +7 (999) 123-45-67.');
                                } elseif (Client::query()->where('phone', $phone)->whereKeyNot($record?->getKey())->exists()) {
                                    $fail('Клиент с таким телефоном уже есть.');
                                }
                            }),
                        TextInput::make('email')
                            ->label('Email')
                            ->email(),
                        TextInput::make('telegram_chat_id')
                            ->label('Telegram chat ID')
                            ->helperText('Заполняется сам, когда клиент открывает бота по ссылке со страницы записи.')
                            ->disabled(),
                        Textarea::make('notes')
                            ->label('Заметки администратора')
                            ->helperText('Клиент их не видит.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
