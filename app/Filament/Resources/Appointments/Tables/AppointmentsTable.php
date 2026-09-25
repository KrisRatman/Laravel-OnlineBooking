<?php

namespace App\Filament\Resources\Appointments\Tables;

use App\Enums\AppointmentStatus;
use App\Filament\Actions\AppointmentActions;
use App\Models\Appointment;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AppointmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at')
            ->modifyQueryUsing(fn ($query) => $query->with(['client', 'service', 'staff']))
            ->columns([
                TextColumn::make('starts_at')
                    ->label('Когда')
                    ->dateTime('d.m.Y, H:i')
                    ->description(fn (Appointment $record) => $record->localStartsAt()->isoFormat('dd').', до '.$record->localEndsAt()->format('H:i'))
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Клиент')
                    ->description(fn (Appointment $record) => $record->client->formattedPhone())
                    ->searchable(['name', 'phone']),
                TextColumn::make('service.name')
                    ->label('Услуга'),
                TextColumn::make('staff.name')
                    ->label('Мастер'),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge(),
                TextColumn::make('price')
                    ->label('Цена')
                    ->formatStateUsing(fn (int $state) => money_rub($state))
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(AppointmentStatus::class)
                    ->multiple(),
                SelectFilter::make('staff')
                    ->label('Мастер')
                    ->relationship('staff', 'name'),
                SelectFilter::make('service')
                    ->label('Услуга')
                    ->relationship('service', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make(AppointmentActions::all()),
            ]);
    }
}
