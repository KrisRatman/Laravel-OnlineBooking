<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Support\BusinessTime;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Новая запись'),
        ];
    }

    public function getTabs(): array
    {
        [$todayFrom, $todayTo] = BusinessTime::dayBounds(BusinessTime::today());

        return [
            'upcoming' => Tab::make('Предстоящие')
                ->modifyQueryUsing(fn ($query) => $query->active()->where('starts_at', '>=', now())),
            'today' => Tab::make('Сегодня')
                ->modifyQueryUsing(fn ($query) => $query->where('starts_at', '>=', $todayFrom)->where('starts_at', '<', $todayTo)),
            'new' => Tab::make('Ждут подтверждения')
                ->modifyQueryUsing(fn ($query) => $query->where('status', AppointmentStatus::New)->where('starts_at', '>=', now()))
                ->badge(fn () => AppointmentResource::getNavigationBadge())
                ->badgeColor('warning'),
            'past' => Tab::make('Прошедшие')
                ->modifyQueryUsing(fn ($query) => $query->where('starts_at', '<', now())->reorder('starts_at', 'desc')),
            'all' => Tab::make('Все'),
        ];
    }
}
