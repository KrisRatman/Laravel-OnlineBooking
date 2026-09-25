<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Actions\AppointmentActions;
use App\Filament\Resources\Appointments\AppointmentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAppointment extends ViewRecord
{
    protected static string $resource = AppointmentResource::class;

    public function getTitle(): string
    {
        return 'Запись: '.$this->record->client->name;
    }

    protected function getHeaderActions(): array
    {
        return AppointmentActions::all();
    }
}
