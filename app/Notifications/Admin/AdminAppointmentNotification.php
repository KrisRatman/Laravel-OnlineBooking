<?php

namespace App\Notifications\Admin;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Уведомление в колокольчике админки Filament.
 */
abstract class AdminAppointmentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Appointment $appointment,
    ) {
        $this->afterCommit();
    }

    abstract protected function title(): string;

    abstract protected function icon(): string;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $appointment = $this->appointment;

        return FilamentNotification::make()
            ->title($this->title())
            ->icon($this->icon())
            ->body(sprintf(
                '%s, %s · %s · %s',
                $appointment->client->name,
                $appointment->client->formattedPhone(),
                $appointment->service->name,
                $appointment->localStartsAt()->locale('ru')->isoFormat('D MMM, HH:mm'),
            ))
            ->actions([
                Action::make('view')
                    ->label('Открыть')
                    ->url(AppointmentResource::getUrl('view', ['record' => $appointment])),
            ])
            ->getDatabaseMessage();
    }
}
