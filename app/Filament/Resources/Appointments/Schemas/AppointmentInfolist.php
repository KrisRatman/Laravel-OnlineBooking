<?php

namespace App\Filament\Resources\Appointments\Schemas;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\Appointment;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AppointmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Запись')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status')->label('Статус')->badge(),
                        TextEntry::make('starts_at')
                            ->label('Когда')
                            ->state(fn (Appointment $record) => $record->localStartsAt()->isoFormat('D MMMM YYYY (dd), HH:mm').'–'.$record->localEndsAt()->format('H:i')),
                        TextEntry::make('service.name')->label('Услуга'),
                        TextEntry::make('staff.name')->label('Мастер'),
                        TextEntry::make('price')->label('Стоимость')->formatStateUsing(fn (int $state) => money_rub($state)),
                        TextEntry::make('buffer_minutes')->label('Буфер после')->suffix(' мин'),
                        TextEntry::make('comment')->label('Комментарий клиента')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('cancel_reason')->label('Причина отмены')->visible(fn (Appointment $record) => filled($record->cancel_reason))->columnSpanFull(),
                    ]),
                Section::make('Клиент')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('client.name')
                            ->label('Имя')
                            ->url(fn (Appointment $record) => ClientResource::getUrl('view', ['record' => $record->client])),
                        TextEntry::make('client.phone')
                            ->label('Телефон')
                            ->formatStateUsing(fn (Appointment $record) => $record->client->formattedPhone())
                            ->url(fn (Appointment $record) => 'tel:'.$record->client->phone)
                            ->copyable(),
                        TextEntry::make('client.email')->label('Email')->placeholder('—'),
                        TextEntry::make('telegram')
                            ->label('Telegram')
                            ->state(fn (Appointment $record) => $record->client->telegram_chat_id ? 'Подключён' : 'Не подключён'),
                    ]),
                Section::make('История')
                    ->columnSpanFull()
                    ->columns(4)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('created_at')->label('Создана')->dateTime('d.m.Y H:i'),
                        TextEntry::make('confirmed_at')->label('Подтверждена')->dateTime('d.m.Y H:i')->placeholder('—'),
                        TextEntry::make('reminder_day_sent_at')->label('Напоминание за сутки')->dateTime('d.m.Y H:i')->placeholder('—'),
                        TextEntry::make('reminder_hours_sent_at')->label('Напоминание за 2 часа')->dateTime('d.m.Y H:i')->placeholder('—'),
                    ]),
            ]);
    }
}
