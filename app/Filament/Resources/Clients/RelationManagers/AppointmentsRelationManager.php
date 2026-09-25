<?php

namespace App\Filament\Resources\Clients\RelationManagers;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * История записей клиента.
 */
class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'appointments';

    protected static ?string $title = 'История записей';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['service', 'staff']))
            ->columns([
                TextColumn::make('starts_at')->label('Когда')->dateTime('d.m.Y, H:i'),
                TextColumn::make('service.name')->label('Услуга'),
                TextColumn::make('staff.name')->label('Мастер'),
                TextColumn::make('status')->label('Статус')->badge(),
                TextColumn::make('price')->label('Цена')->formatStateUsing(fn (int $state) => money_rub($state)),
            ])
            ->recordActions([
                ViewAction::make()->url(fn (Appointment $record) => AppointmentResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
