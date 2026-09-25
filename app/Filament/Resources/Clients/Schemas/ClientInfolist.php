<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Enums\AppointmentStatus;
use App\Models\Client;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name')->label('Имя'),
                        TextEntry::make('phone')
                            ->label('Телефон')
                            ->formatStateUsing(fn (Client $record) => $record->formattedPhone())
                            ->url(fn (Client $record) => 'tel:'.$record->phone)
                            ->copyable(),
                        TextEntry::make('email')->label('Email')->placeholder('—'),
                        TextEntry::make('telegram')
                            ->label('Telegram')
                            ->state(fn (Client $record) => $record->telegram_chat_id ? 'Подключён' : 'Не подключён'),
                        TextEntry::make('visits')
                            ->label('Визитов')
                            ->state(fn (Client $record) => $record->appointments()->where('status', AppointmentStatus::Completed)->count()),
                        TextEntry::make('spent')
                            ->label('Потратил')
                            ->state(fn (Client $record) => money_rub((int) $record->appointments()->where('status', AppointmentStatus::Completed)->sum('price'))),
                        TextEntry::make('cancelled')
                            ->label('Отмен')
                            ->state(fn (Client $record) => $record->appointments()->where('status', AppointmentStatus::Cancelled)->count()),
                        TextEntry::make('created_at')->label('Клиент с')->date('d.m.Y'),
                        TextEntry::make('notes')->label('Заметки')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}
