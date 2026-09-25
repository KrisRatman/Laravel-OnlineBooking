<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Models\Client;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Имя')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->formatStateUsing(fn (Client $record) => $record->formattedPhone())
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('—')
                    ->searchable(),
                IconColumn::make('telegram_chat_id')
                    ->label('Telegram')
                    ->boolean()
                    ->state(fn (Client $record) => filled($record->telegram_chat_id)),
                TextColumn::make('appointments_count')
                    ->label('Записей')
                    ->counts('appointments')
                    ->sortable(),
                TextColumn::make('appointments_max_starts_at')
                    ->label('Последняя запись')
                    ->max('appointments', 'starts_at')
                    ->dateTime('d.m.Y')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
