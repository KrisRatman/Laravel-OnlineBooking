<?php

namespace App\Actions;

use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\Admin\AppointmentCancelledByClient;
use App\Notifications\AppointmentCancelled;
use App\Notifications\AppointmentConfirmed;
use App\Notifications\AppointmentRescheduled;
use App\Services\Slots\SlotService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Жизненный цикл записи: новая → подтверждена → выполнена, либо отменена.
 */
class AppointmentWorkflow
{
    public function __construct(
        private readonly SlotService $slots,
    ) {}

    public function confirm(Appointment $appointment): void
    {
        $this->transition($appointment, AppointmentStatus::Confirmed, [AppointmentStatus::New], [
            'confirmed_at' => now(),
        ]);

        $appointment->client->notify(new AppointmentConfirmed($appointment));
    }

    public function cancel(Appointment $appointment, ?string $reason = null, bool $byClient = false): void
    {
        $this->transition($appointment, AppointmentStatus::Cancelled, AppointmentStatus::active(), [
            'cancelled_at' => now(),
            'cancel_reason' => $reason ?: ($byClient ? 'Отменена клиентом' : null),
        ]);

        $appointment->client->notify(new AppointmentCancelled($appointment, $byClient));

        if ($byClient) {
            Notification::send(User::all(), new AppointmentCancelledByClient($appointment));
        }
    }

    public function complete(Appointment $appointment): void
    {
        $this->transition($appointment, AppointmentStatus::Completed, AppointmentStatus::active());
    }

    /**
     * Перенос на другое время или к другому мастеру. Проверка — как при новой записи.
     *
     * @throws SlotUnavailableException
     */
    public function reschedule(Appointment $appointment, int $staffId, CarbonImmutable $startsAt): void
    {
        if (! $appointment->status->isActive()) {
            throw new InvalidStatusTransitionException($appointment->status, $appointment->status);
        }

        DB::transaction(function () use ($appointment, $staffId, $startsAt) {
            $staff = Staff::query()->whereKey($staffId)->lockForUpdate()->firstOrFail();

            if (! $this->slots->isAvailable($appointment->service, $staff, $startsAt, ignoreAppointmentId: $appointment->id)) {
                throw new SlotUnavailableException;
            }

            $offer = $staff->offer($appointment->service);

            $appointment->update([
                'staff_id' => $staff->id,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addMinutes($offer->durationMinutes),
                'price' => $offer->price,
                'buffer_minutes' => $offer->bufferMinutes,
                // Время изменилось — напоминания нужно отправить заново.
                'reminder_day_sent_at' => null,
                'reminder_hours_sent_at' => null,
            ]);
        });

        $appointment->client->notify(new AppointmentRescheduled($appointment->fresh()));
    }

    /**
     * @param  list<AppointmentStatus>  $allowedFrom
     * @param  array<string, mixed>  $attributes
     */
    private function transition(Appointment $appointment, AppointmentStatus $to, array $allowedFrom, array $attributes = []): void
    {
        if (! in_array($appointment->status, $allowedFrom, true)) {
            throw new InvalidStatusTransitionException($appointment->status, $to);
        }

        $appointment->update(['status' => $to, ...$attributes]);
    }
}
