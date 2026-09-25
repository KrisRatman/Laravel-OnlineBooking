<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Actions\BookAppointment;
use App\Actions\BookingRequest;
use App\Exceptions\SlotUnavailableException;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAppointment extends CreateRecord
{
    protected static string $resource = AppointmentResource::class;

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $appointment = app(BookAppointment::class)->handle(new BookingRequest(
                serviceId: (int) $data['service_id'],
                staffId: (int) $data['staff_id'],
                startsAt: CarbonImmutable::createFromTimestampUTC((int) $data['slot']),
                name: $data['name'],
                phone: Phone::normalize($data['phone']),
                email: $data['email'] ?: null,
                comment: $data['comment'] ?: null,
                confirmed: (bool) ($data['confirm'] ?? false),
                fromAdmin: true,
            ));
        } catch (SlotUnavailableException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            $this->halt();
        }

        return $appointment;
    }

    protected function getRedirectUrl(): string
    {
        return AppointmentResource::getUrl('view', ['record' => $this->record]);
    }
}
