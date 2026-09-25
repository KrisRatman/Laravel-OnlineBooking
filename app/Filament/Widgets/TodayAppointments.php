<?php

namespace App\Filament\Widgets;

use App\Filament\Actions\AppointmentActions;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use App\Support\BusinessTime;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TodayAppointments extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Записи на сегодня';

    public function table(Table $table): Table
    {
        [$from, $to] = BusinessTime::dayBounds(BusinessTime::today());

        return $table
            ->query(fn () => Appointment::query()
                ->active()
                ->with(['client', 'service', 'staff'])
                ->where('starts_at', '>=', $from)
                ->where('starts_at', '<', $to))
            ->defaultSort('starts_at')
            ->paginated(false)
            ->emptyStateHeading('Сегодня записей нет')
            ->recordUrl(fn (Appointment $record) => AppointmentResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('starts_at')
                    ->label('Время')
                    ->dateTime('H:i')
                    ->description(fn (Appointment $record) => 'до '.$record->localEndsAt()->format('H:i')),
                TextColumn::make('client.name')
                    ->label('Клиент')
                    ->description(fn (Appointment $record) => $record->client->formattedPhone()),
                TextColumn::make('service.name')->label('Услуга'),
                TextColumn::make('staff.name')->label('Мастер'),
                TextColumn::make('status')->label('Статус')->badge(),
            ])
            ->recordActions([
                ActionGroup::make(AppointmentActions::all()),
            ]);
    }
}
